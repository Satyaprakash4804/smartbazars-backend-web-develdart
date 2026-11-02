<?php

namespace App\Services\SmartBazars;

use App\Models\PartnerDailyReport;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Daily reports of agents/workers: filtering + CSV export (daily or monthly). */
class DailyReportService
{
    public function query(array $f): Builder
    {
        $q = PartnerDailyReport::query()->with(['member.user', 'member.seller']);
        if (!empty($f['seller_id'])) {
            $q->whereHas('member', fn($m) => $m->where('type', 'worker')->where('seller_id', $f['seller_id']));
        }
        if (!empty($f['type'])) {
            $q->whereHas('member', fn($m) => $m->where('type', $f['type']));
        }
        if (!empty($f['member_id'])) {
            $q->where('network_member_id', $f['member_id']);
        }
        if (!empty($f['date'])) {
            $q->whereDate('report_date', $f['date']);
        } elseif (!empty($f['month'])) { // YYYY-MM
            [$y, $m] = array_pad(explode('-', $f['month']), 2, null);
            if ($y && $m) $q->whereYear('report_date', (int)$y)->whereMonth('report_date', (int)$m);
        }
        return $q->orderByDesc('report_date')->orderBy('network_member_id');
    }

    public function csv(Builder $q, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Hindi/₹ correctly
            fputcsv($out, ['Date', 'Name', 'Role', 'Code', 'Vendor / Upline', 'Customers met', 'Kits sold', 'Report', 'Submitted at']);
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    $m = $r->member;
                    fputcsv($out, [
                        $r->report_date?->format('Y-m-d'),
                        $m?->user?->name,
                        ucfirst($m?->type ?? ''),
                        $m?->referral_code,
                        $m?->isWorker() ? ($m->seller?->displayName() ?? '') : ($m?->parent?->user?->name ?? 'SmartBazars'),
                        $r->customers_met,
                        $r->kits_sold,
                        $r->content,
                        $r->updated_at?->format('Y-m-d H:i'),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
