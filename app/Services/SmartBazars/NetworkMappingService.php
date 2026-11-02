<?php

namespace App\Services\SmartBazars;

use App\Models\NetworkMappingLog;
use App\Models\NetworkMember;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Upline / downline mapping for the SmartBazars network.
 *
 *   Company ─▶ BSO ─▶ Sub-agent …            (network_members.parent_id / path / level)
 *                 └──▶ Vendor                  (sellers.agent_member_id)
 *                          └──▶ BJO         (network_members.seller_id, type=worker)
 *
 * Every move is validated, done in one transaction and written to network_mapping_logs.
 * Moving only changes FUTURE commission routing – credited commissions are never rewritten.
 */
class NetworkMappingService
{
    // ------------------------------------------------------------------ reads

    /** BSOs + vendors for the tree. BJOs are loaded lazily per vendor. */
    public function graph(): array
    {
        $agents = NetworkMember::with('user:id,name,email,mobile')
            ->where('type', NetworkMember::TYPE_AGENT)
            ->get(['id', 'user_id', 'parent_id', 'level', 'referral_code', 'status'])
            ->map(fn($m) => [
                'id' => $m->id,
                'name' => $m->user->name ?? ('BSO #' . $m->id),
                'email' => $m->user->email ?? '',
                'mobile' => $m->user->mobile ?? '',
                'code' => $m->referral_code,
                'parent_id' => $m->parent_id,
                'status' => $m->status,
            ])->values();

        $workerCounts = NetworkMember::where('type', NetworkMember::TYPE_WORKER)
            ->whereNotNull('seller_id')->select('seller_id', DB::raw('count(*) c'))
            ->groupBy('seller_id')->pluck('c', 'seller_id');

        $vendors = Seller::with('user:id,name,email,mobile')->get()->map(function ($s) use ($workerCounts) {
            $st = is_object($s->verification_status) ? ($s->verification_status->value ?? '') : (string)$s->verification_status;
            return [
                'id' => $s->id,
                'name' => method_exists($s, 'displayName') ? $s->displayName() : ($s->user->name ?? ('Vendor #' . $s->id)),
                'owner' => $s->user->name ?? '',
                'email' => $s->user->email ?? '',
                'mobile' => $s->user->mobile ?? '',
                'code' => $s->referral_code,
                'agent_id' => $s->agent_member_id,
                'status' => $st === 'approved' ? 'active' : ($st ?: 'pending'),
                'workers_count' => (int)($workerCounts[$s->id] ?? 0),
            ];
        })->values();

        return ['agents' => $agents, 'vendors' => $vendors, 'logs' => $this->recentLogs()];
    }

    public function workersOf(Seller $seller): array
    {
        return NetworkMember::with('user:id,name,email,mobile')->withCount('customers')
            ->where('type', NetworkMember::TYPE_WORKER)->where('seller_id', $seller->id)
            ->orderBy('id')->get()->map(fn($m) => [
                'id' => $m->id,
                'name' => $m->user->name ?? ('BJO #' . $m->id),
                'email' => $m->user->email ?? '',
                'mobile' => $m->user->mobile ?? '',
                'code' => $m->referral_code,
                'seller_id' => $m->seller_id,
                'status' => $m->status,
                'customers' => (int)$m->customers_count,
            ])->all();
    }

    public function search(string $q, int $limit = 8): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $userMatch = fn($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('mobile', 'like', $like);

        $members = NetworkMember::with('user:id,name,mobile')
            ->where(fn($w) => $w->where('referral_code', 'like', $like)->orWhereHas('user', $userMatch))
            ->limit($limit * 2)->get()->map(fn($m) => [
                'type' => $m->type, 'id' => $m->id, 'seller_id' => $m->seller_id,
                'label' => ($m->user->name ?? '#' . $m->id), 'sub' => $m->referral_code . ' · ' . ($m->user->mobile ?? ''),
            ]);

        $vendors = Seller::with('user:id,name,mobile')
            ->where(fn($w) => $w->where('referral_code', 'like', $like)->orWhereHas('user', $userMatch))
            ->limit($limit)->get()->map(fn($s) => [
                'type' => 'vendor', 'id' => $s->id, 'seller_id' => $s->id,
                'label' => method_exists($s, 'displayName') ? $s->displayName() : ($s->user->name ?? '#' . $s->id),
                'sub' => ($s->referral_code ?? '') . ' · ' . ($s->user->mobile ?? ''),
            ]);

        return $members->concat($vendors)->take($limit * 2)->values()->all();
    }

    public function recentLogs(int $limit = 15): array
    {
        return NetworkMappingLog::latest('id')->limit($limit)->get()->map(fn($l) => $this->logRow($l))->all();
    }

    protected function logRow(NetworkMappingLog $l): array
    {
        return [
            'type' => $l->entity_type, 'label' => $l->entity_label,
            'from' => $l->from_label, 'to' => $l->to_label,
            'affected' => $l->affected, 'at' => $l->created_at?->diffForHumans(),
        ];
    }

    // ------------------------------------------------------------------ moves

    /** Move an agent (with its whole downline) under another agent, or to the top level ($newParent = null). */
    public function moveAgent(NetworkMember $agent, ?NetworkMember $newParent, ?int $adminId): array
    {
        return DB::transaction(function () use ($agent, $newParent, $adminId) {
            $agent = NetworkMember::whereKey($agent->id)->lockForUpdate()->firstOrFail();
            if (!$agent->isAgent()) $this->fail('Only agents can be moved in the agent tree.');

            if ($newParent) {
                $newParent = NetworkMember::whereKey($newParent->id)->lockForUpdate()->firstOrFail();
                if (!$newParent->isAgent()) $this->fail('The new upline must be an agent.');
                if ($newParent->id === $agent->id) $this->fail('An agent cannot be placed under itself.');
                if (str_contains($newParent->path, '/' . $agent->id . '/')) $this->fail('Cannot move an agent under its own downline.');
            }
            if ((int)$agent->parent_id === (int)($newParent?->id)) $this->fail('Already under this upline.');

            $oldParent = $agent->parent_id ? NetworkMember::with('user:id,name')->find($agent->parent_id) : null;
            $oldPrefix = $agent->path . $agent->id . '/';
            $newPath = $newParent ? $newParent->path . $newParent->id . '/' : '/';
            $newPrefix = $newPath . $agent->id . '/';
            $newLevel = $newParent ? $newParent->level + 1 : 1;
            $delta = $newLevel - $agent->level;

            // sub-tree first (their paths still start with the old prefix)
            $affected = NetworkMember::where('type', NetworkMember::TYPE_AGENT)
                ->where('path', 'like', $oldPrefix . '%')
                ->update([
                    'path' => DB::raw('CONCAT(' . DB::getPdo()->quote($newPrefix) . ', SUBSTRING(path, ' . (strlen($oldPrefix) + 1) . '))'),
                    'level' => DB::raw('level + ' . (int)$delta),
                ]);

            $agent->update(['parent_id' => $newParent?->id, 'path' => $newPath, 'level' => $newLevel]);

            $agent->loadMissing('user:id,name');
            $newParent?->loadMissing('user:id,name');
            return $this->log($adminId, 'agent', $agent->id, $agent->user->name ?? '#' . $agent->id,
                $oldParent?->id, $oldParent ? ($oldParent->user->name ?? '#' . $oldParent->id) : 'SmartBazars (top level)',
                $newParent?->id, $newParent ? ($newParent->user->name ?? '#' . $newParent->id) : 'SmartBazars (top level)', $affected);
        });
    }

    /** Link a vendor to an agent, or unlink it ($agent = null). */
    public function moveVendor(Seller $seller, ?NetworkMember $agent, ?int $adminId): array
    {
        if ($agent && (!$agent->isAgent())) $this->fail('A vendor can only be linked to an agent.');
        if ($agent && !$agent->isActive()) $this->fail('This agent is suspended. Activate the agent first.');
        if ((int)$seller->agent_member_id === (int)($agent?->id)) $this->fail('Already linked to this agent.');

        return DB::transaction(function () use ($seller, $agent, $adminId) {
            $old = $seller->agent_member_id ? NetworkMember::with('user:id,name')->find($seller->agent_member_id) : null;
            $seller->loadMissing('user:id,name');
            $seller->forceFill(['agent_member_id' => $agent?->id])->save();
            $agent?->loadMissing('user:id,name');
            $label = method_exists($seller, 'displayName') ? $seller->displayName() : ($seller->user->name ?? '#' . $seller->id);
            return $this->log($adminId, 'vendor', $seller->id, $label,
                $old?->id, $old ? ($old->user->name ?? '#' . $old->id) : 'Unassigned',
                $agent?->id, $agent ? ($agent->user->name ?? '#' . $agent->id) : 'Unassigned', 0);
        });
    }

    /** Move a worker to another vendor. */
    public function moveWorker(NetworkMember $worker, Seller $seller, ?int $adminId): array
    {
        if (!$worker->isWorker()) $this->fail('Only workers can be moved between vendors.');
        if ((int)$worker->seller_id === (int)$seller->id) $this->fail('Already working for this vendor.');

        return DB::transaction(function () use ($worker, $seller, $adminId) {
            $old = $worker->seller_id ? Seller::with('user:id,name')->find($worker->seller_id) : null;
            $worker->loadMissing('user:id,name');
            $seller->loadMissing('user:id,name');
            $worker->update(['seller_id' => $seller->id]);
            $name = fn(?Seller $s) => $s ? (method_exists($s, 'displayName') ? $s->displayName() : ($s->user->name ?? '#' . $s->id)) : 'Unassigned';
            return $this->log($adminId, 'worker', $worker->id, $worker->user->name ?? '#' . $worker->id,
                $old?->id, $name($old), $seller->id, $name($seller), 0);
        });
    }

    protected function log(?int $adminId, string $type, int $id, string $label, $fromId, string $fromLabel, $toId, string $toLabel, int $affected): array
    {
        $row = NetworkMappingLog::create([
            'admin_id' => $adminId, 'entity_type' => $type, 'entity_id' => $id, 'entity_label' => mb_substr($label, 0, 150),
            'from_id' => $fromId, 'from_label' => mb_substr($fromLabel, 0, 150),
            'to_id' => $toId, 'to_label' => mb_substr($toLabel, 0, 150), 'affected' => $affected,
        ]);
        return $this->logRow($row);
    }

    protected function fail(string $msg): never
    {
        throw ValidationException::withMessages(['move' => $msg]);
    }
}
