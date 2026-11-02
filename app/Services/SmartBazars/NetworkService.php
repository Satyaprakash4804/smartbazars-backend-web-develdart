<?php

namespace App\Services\SmartBazars;

use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use App\Models\NetworkMember;
use App\Models\NetworkRegistrationRequest;
use App\Models\NetworkFeePayment;
use App\Models\Seller;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use App\Notifications\SmartBazars\NetworkFeeNotification;
use App\Models\User;
use App\Notifications\SmartBazars\PartnerCredentialsNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * BSO / Vendor / BJO onboarding.
 *
 *  Admin  ──creates──▶ BSO (level 1, no referral needed, active immediately)
 *  BSO  ──requests─▶ BSO   (admin approves)             → becomes child of requesting agent
 *  BSO  ──requests─▶ Vendor  (admin approves)             → seller.agent_member_id = agent
 *  BSO  ──requests─▶ BJO  (vendor approves → admin)    → only for vendors this agent onboarded
 *  Vendor ──requests─▶ BJO  (vendor auto-approved → admin)
 *
 * On final approval the account is created and a password is emailed.
 */
class NetworkService
{
    public const PARTNER_PANEL = 'partner';
    public const PENDING = ['pending_payment', 'pending_seller', 'pending_admin'];

    public function __construct(protected NetworkFeeService $fees) {}

    // ------------------------------------------------------------------ direct creation (admin)

    public function adminCreateAgent(array $data, int $adminId, ?NetworkMember $parent = null): array
    {
        $this->assertUniqueContact($data['email'], $data['mobile']);

        return DB::transaction(function () use ($data, $adminId, $parent) {
            $password = $data['password'] ?? $this->randomPassword();
            $user = $this->createPartnerUser($data['name'], $data['email'], $data['mobile'], $password);
            $member = $this->createMember($user, NetworkMember::TYPE_AGENT, $parent, null, 'admin', $adminId);
            $mailed = $this->sendCredentials($user, $password, 'BSO', $member->referral_code, route('partner.login'), $member);
            return ['member' => $member, 'password' => $password, 'mailed' => $mailed, 'mail_error' => $this->lastMailError];
        });
    }

    // ------------------------------------------------------------------ requests

    /**
     * @param string $type agent|vendor|worker
     * @param NetworkMember|Seller $requester
     */
    public function submitRequest(string $type, array $data, NetworkMember|Seller $requester): NetworkRegistrationRequest
    {
        $this->assertUniqueContact($data['email'], $data['mobile']);

        $attrs = [
            'type' => $type,
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'mobile' => trim($data['mobile']),
            'meta' => $data['meta'] ?? null,
        ];

        if ($requester instanceof NetworkMember) {
            if (!$requester->isAgent() || !$requester->isActive()) {
                throw ValidationException::withMessages(['type' => 'Only active agents can create new IDs.']);
            }
            $attrs['requested_by_member_id'] = $requester->id;

            if ($type === 'worker') {
                $seller = Seller::find($data['seller_id'] ?? 0);
                if (!$seller || (int)$seller->agent_member_id !== (int)$requester->id) {
                    throw ValidationException::withMessages(['seller_id' => 'You can only add workers for vendors you onboarded.']);
                }
                $attrs['seller_id'] = $seller->id;
                $attrs['status'] = 'pending_seller';    // vendor must approve first
            } else {
                $attrs['status'] = 'pending_admin';
            }
        } else { // Seller (vendor)
            if ($type !== 'worker') {
                throw ValidationException::withMessages(['type' => 'Vendors can only request worker IDs.']);
            }
            $attrs['requested_by_seller_id'] = $requester->id;
            $attrs['seller_id'] = $requester->id;
            $attrs['status'] = 'pending_admin';
            $attrs['seller_approved_at'] = now();
        }

        if ($type === 'vendor') {
            foreach (['store_name', 'address', 'city', 'state', 'zipcode'] as $f) {
                if (empty($data['meta'][$f])) {
                    throw ValidationException::withMessages(["meta.$f" => ucfirst(str_replace('_', ' ', $f)) . ' is required.']);
                }
            }
        }

        $attrs['source'] = $requester instanceof NetworkMember ? 'agent' : 'vendor';
        $req = NetworkRegistrationRequest::create($attrs);

        // Joining fee: the creator (agent / vendor) pays first. Full amount to the company — no commission split.
        $returnUrl = $requester instanceof NetworkMember ? route('partner.requests') : route('seller.smartbazars.workers');
        $this->fees->startForRequest($req, 'id_creation', $returnUrl);

        return $req->fresh();
    }

    /** Called once the joining fee is paid (or waived): releases the request into the normal approval flow. */
    public function afterPayment(NetworkRegistrationRequest $req): void
    {
        $awaitingVendor = $req->type === 'worker' && $req->requested_by_member_id && !$req->seller_approved_at;
        $req->update(['status' => $awaitingVendor ? 'pending_seller' : 'pending_admin']);

        if ($req->auto_approve) {          // admin created this ID and chose "collect payment"
            try {
                $this->adminApprove($req->fresh(), (int)($req->meta['admin_id'] ?? 0));
            } catch (\Throwable $e) {
                Log::error('SmartBazars auto-approve after payment failed: ' . $e->getMessage(), ['request_id' => $req->id]);
            }
        }
    }

    // ------------------------------------------------------------------ public "Apply now" (agent / worker)

    public function submitPublicApplication(array $data, array $files, ?string $retryUuid = null): NetworkRegistrationRequest
    {
        $this->assertUniqueContact($data['email'], $data['mobile']);

        // Referral code: an AGENT's code puts an applicant agent in that agent's downline (any level);
        // a VENDOR's code assigns an applicant worker to that vendor. For a worker, an agent's code is just noted as referrer.
        $seller = null;
        $referrer = null;
        $code = strtoupper(trim((string)($data['referral_code'] ?? $data['vendor_code'] ?? '')));
        if ($code !== '') {
            $referrer = NetworkMember::where('referral_code', $code)->where('type', NetworkMember::TYPE_AGENT)->where('status', 'active')->first();
            if (!$referrer) {
                $seller = Seller::where('referral_code', $code)->first();
            }
            if (!$referrer && !$seller) {
                throw ValidationException::withMessages(['referral_code' => 'This referral code was not found. Use the code of an active agent' . ($data['type'] === 'worker' ? ' or vendor.' : '.')]);
            }
            if ($data['type'] === 'agent' && !$referrer) {
                throw ValidationException::withMessages(['referral_code' => 'BSOs join under another agent. Please enter an agent\'s referral code.']);
            }
        }

        $previous = $retryUuid ? NetworkRegistrationRequest::where('uuid', $retryUuid)->where('status', 'rejected')->where('source', 'public')->first() : null;

        return DB::transaction(function () use ($data, $files, $seller, $previous, $code, $referrer) {
            $req = NetworkRegistrationRequest::create([
                'type' => $data['type'],
                'source' => 'public',
                'name' => $data['name'],
                'email' => strtolower(trim($data['email'])),
                'mobile' => trim($data['mobile']),
                'seller_id' => $seller?->id,
                'status' => 'pending_admin',
                'reapplied_from_id' => $previous?->id,
                'meta' => [
                    'referral_code' => $code ?: null,
                    'parent_id' => $data['type'] === 'agent' ? $referrer?->id : null,        // upline for an applicant agent
                    'referred_by_member_id' => $referrer?->id,
                    'address' => $data['address'], 'city' => $data['city'], 'state' => $data['state'], 'zipcode' => $data['zipcode'],
                    'qualification' => $data['qualification'], 'education_details' => $data['education_details'] ?? null,
                    'aadhar_number' => Crypt::encryptString(preg_replace('/\s+/', '', $data['aadhar_number'])),
                    'pan_number' => Crypt::encryptString(strtoupper($data['pan_number'])),
                ],
            ]);

            $docs = [];
            foreach (['aadhar', 'pan', 'education'] as $key) {
                /** @var UploadedFile $file */
                $file = $files[$key . '_doc'];
                $docs[$key] = $file->storeAs('smartbazars/applications/' . $req->uuid, $key . '.' . $file->getClientOriginalExtension(), 'local');
            }
            $req->update(['documents' => $docs]);

            $this->fees->startForRequest($req, 'application', route('partner.login'));
            return $req->fresh();
        });
    }

    // ------------------------------------------------------------------ admin direct creation (agent / worker / vendor)

    /**
     * @param bool $collectPayment true = account is created only after the fee is paid (link emailed);
     *                             false = admin waives the fee, account is created now.
     * @return array{request: NetworkRegistrationRequest, paid_link: ?string, result: ?array}
     */
    public function adminCreate(string $type, array $data, int $adminId, bool $collectPayment): array
    {
        $this->assertUniqueContact($data['email'], $data['mobile']);

        $meta = ['admin_id' => $adminId];
        if (in_array($type, ['agent', 'vendor'], true) && !empty($data['parent_id'])) $meta['parent_id'] = (int)$data['parent_id']; // agent: upline · vendor: onboarding agent
        if ($type === 'vendor') {
            foreach (['store_name', 'address', 'city', 'state', 'zipcode', 'landmark'] as $f) $meta[$f] = $data['meta'][$f] ?? null;
        }
        if ($type === 'worker' && !Seller::find($data['seller_id'] ?? 0)) {
            throw ValidationException::withMessages(['seller_id' => 'Select the vendor this worker works for.']);
        }

        $req = NetworkRegistrationRequest::create([
            'type' => $type, 'source' => 'admin', 'name' => $data['name'],
            'email' => strtolower(trim($data['email'])), 'mobile' => trim($data['mobile']),
            'seller_id' => $type === 'worker' ? (int)$data['seller_id'] : null,
            'meta' => $meta, 'status' => 'pending_admin', 'auto_approve' => true,
            'seller_approved_at' => $type === 'worker' ? now() : null,
        ]);

        if ($collectPayment && $this->fees->required($type)) {
            $payment = $this->fees->startForRequest($req, 'id_creation', route('partner.login'));
            if ($payment) {
                $this->fees->sendLink($payment, ucfirst($type));
                return ['request' => $req->fresh(), 'paid_link' => $payment->payUrl(), 'result' => null];
            }
        }

        $req->update(['payment_status' => $this->fees->enabled() ? 'waived' : 'not_required']);
        $result = $this->adminApprove($req->fresh(), $adminId);
        return ['request' => $req->fresh(), 'paid_link' => null, 'result' => $result];
    }

    public function sellerDecision(NetworkRegistrationRequest $req, Seller $seller, bool $approve, ?string $reason = null): void
    {
        if ($req->type !== 'worker' || (int)$req->seller_id !== (int)$seller->id || $req->status !== 'pending_seller') {
            throw ValidationException::withMessages(['request' => 'This request is not awaiting your approval.']);
        }
        $req->update($approve
            ? ['status' => 'pending_admin', 'seller_approved_at' => now()]
            : ['status' => 'rejected', 'rejection_reason' => $reason ?: 'Rejected by vendor']);
    }

    public function adminReject(NetworkRegistrationRequest $req, int $adminId, ?string $reason): void
    {
        if (in_array($req->status, ['approved', 'rejected'])) {
            throw ValidationException::withMessages(['request' => 'Request already processed.']);
        }
        $reason = trim((string)$reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Please give a reason for rejecting.']);
        }
        $req->update(['status' => 'rejected', 'approved_by' => $adminId, 'rejection_reason' => $reason]);

        if ($req->source === 'public') {   // applicant has no account — tell them why, and how to re-apply
            try {
                Notification::route('mail', $req->email)->notify(new NetworkFeeNotification(
                    'rejected', $req->name, ucfirst($req->type), route('partner.apply', ['retry' => $req->uuid]), null, $reason));
            } catch (\Throwable $e) {
                Log::error('SmartBazars rejection mail failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Final approval: creates the account and emails the password.
     * @return array{user: User, password: string, mailed: bool}
     */
    public function adminApprove(NetworkRegistrationRequest $req, int $adminId, ?int $sellerId = null): array
    {
        if ($req->status !== 'pending_admin') {
            throw ValidationException::withMessages(['request' => $req->status === 'pending_seller'
                ? 'The vendor has not approved this worker yet.'
                : 'Request already processed.']);
        }
        if (!$req->paymentSettled()) {
            throw ValidationException::withMessages(['request' => 'The joining fee has not been paid yet.']);
        }
        if ($req->type === 'worker' && !$req->seller_id) {
            if (!$sellerId || !Seller::find($sellerId)) {
                throw ValidationException::withMessages(['seller_id' => 'Assign a vendor to this worker before approving.']);
            }
            $req->update(['seller_id' => $sellerId]);
        }
        $this->assertUniqueContact($req->email, $req->mobile, $req->id);

        return DB::transaction(function () use ($req, $adminId) {
            $password = $this->randomPassword();
            $requester = $req->requestedByMember;
            $parentAgent = $requester ?: (!empty($req->meta['parent_id']) ? NetworkMember::where('type', 'agent')->find($req->meta['parent_id']) : null);
            [$cbType, $cbId] = $requester ? ['agent', $requester->id] : ['admin', $adminId];

            switch ($req->type) {
                case 'agent':
                    $user = $this->createPartnerUser($req->name, $req->email, $req->mobile, $password);
                    $member = $this->createMember($user, NetworkMember::TYPE_AGENT, $parentAgent, null, $cbType, $cbId);
                    $mailed = $this->sendCredentials($user, $password, 'BSO', $member->referral_code, route('partner.login'), $member);
                    break;

                case 'worker':
                    $user = $this->createPartnerUser($req->name, $req->email, $req->mobile, $password);
                    $member = $this->createMember(
                        $user, NetworkMember::TYPE_WORKER, null, $req->seller_id,
                        $req->requested_by_seller_id ? 'seller' : $cbType,
                        $req->requested_by_seller_id ?: $cbId
                    );
                    $mailed = $this->sendCredentials($user, $password, 'BJO', $member->referral_code, route('partner.login'), $member);
                    break;

                case 'vendor':
                    $user = User::create([
                        'name' => $req->name,
                        'email' => $req->email,
                        'mobile' => $req->mobile,
                        'password' => $password,
                        'status' => 'active',
                        'access_panel' => 'seller',
                    ]);
                    $meta = $req->meta ?? [];
                    Seller::create([
                        'user_id' => $user->id,
                        'agent_member_id' => $parentAgent?->id,
                        'address' => $meta['address'] ?? '',
                        'city' => $meta['city'] ?? '',
                        'landmark' => $meta['landmark'] ?? '',
                        'state' => $meta['state'] ?? '',
                        'zipcode' => $meta['zipcode'] ?? '',
                        'country' => $meta['country'] ?? 'India',
                        'country_code' => $meta['country_code'] ?? '91',
                        // Admin approving this request IS the verification. With NotApproved the vendor was
                        // logged straight out of the seller panel and could never set up a store.
                        'verification_status' => SellerVerificationStatusEnum::Approved(),
                        'visibility_status' => SellerVisibilityStatusEnum::Draft(),
                        'metadata' => ['store_name' => $meta['store_name'] ?? null, 'onboarded_via' => 'agent'],
                    ]);
                    $user->assignRole('seller');
                    // Vendor then logs in to the normal seller panel and completes documents/store/subscription
                    $mailed = $this->sendCredentials($user, $password, 'Vendor', null, route('seller.login'));
                    break;

                default:
                    throw ValidationException::withMessages(['type' => 'Unknown request type']);
            }

            $req->update([
                'status' => 'approved',
                'admin_approved_at' => now(),
                'approved_by' => $adminId,
                'created_user_id' => $user->id,
                'meta' => array_merge($req->meta ?? [], ['credentials_mail' => [
                    'status' => $mailed ? 'sent' : 'failed', 'error' => $this->lastMailError, 'at' => now()->toDateTimeString(),
                ]]),
            ]);

            return ['user' => $user, 'password' => $password, 'mailed' => $mailed, 'mail_error' => $this->lastMailError];
        });
    }

    // ------------------------------------------------------------------ helpers

    public function createMember(User $user, string $type, ?NetworkMember $parent, ?int $sellerId, string $createdByType, ?int $createdById): NetworkMember
    {
        $parent = $type === NetworkMember::TYPE_AGENT ? $parent : null;
        return NetworkMember::create([
            'user_id' => $user->id,
            'type' => $type,
            'parent_id' => $parent?->id,
            'seller_id' => $sellerId,
            'level' => $parent ? $parent->level + 1 : 1,
            'path' => $parent ? $parent->path . $parent->id . '/' : '/',
            'referral_code' => NetworkMember::generateReferralCode($type),
            'status' => 'active',
            'created_by_type' => $createdByType,
            'created_by_id' => $createdById,
        ]);
    }

    protected function createPartnerUser(string $name, string $email, string $mobile, string $password): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower(trim($email)),
            'mobile' => trim($mobile),
            'password' => $password,
            'status' => 'active',
            'access_panel' => self::PARTNER_PANEL,
        ]);
    }

    public function assertUniqueContact(string $email, string $mobile, ?int $ignoreRequestId = null): void
    {
        $email = strtolower(trim($email));
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'This email is already registered.']);
        }
        if (User::withTrashed()->where('mobile', trim($mobile))->exists()) {
            throw ValidationException::withMessages(['mobile' => 'This mobile number is already registered.']);
        }
        $pending = NetworkRegistrationRequest::whereIn('status', self::PENDING)
            ->when($ignoreRequestId, fn($q) => $q->where('id', '!=', $ignoreRequestId))
            ->where(fn($q) => $q->where('email', $email)->orWhere('mobile', trim($mobile)))->exists();
        if ($pending) {
            throw ValidationException::withMessages(['email' => 'A request with this email/mobile is already pending.']);
        }
    }

    protected function randomPassword(): string
    {
        return 'Sb@' . Str::random(8);
    }

    protected ?string $lastMailError = null;

    public function lastMailError(): ?string
    {
        return $this->lastMailError;
    }

    protected function sendCredentials(User $user, string $password, string $role, ?string $code, string $loginUrl, ?NetworkMember $member = null): bool
    {
        $this->lastMailError = null;
        try {
            $idCard = null;
            if ($member) {
                try {
                    $idCard = app(DocumentService::class)->idCardPdf($member);
                } catch (\Throwable $e) {
                    Log::warning('SmartBazars ID card PDF failed: ' . $e->getMessage());
                }
            }
            $user->notify(new PartnerCredentialsNotification($password, $role, $code, $loginUrl, $idCard));
            return true;
        } catch (\Throwable $e) {
            $this->lastMailError = mb_substr($e->getMessage(), 0, 300);
            Log::error('SmartBazars credentials mail failed: ' . $e->getMessage(), ['user_id' => $user->id, 'to' => $user->email]);
            return false;
        }
    }

    /**
     * Re-send login details for an approved request. Passwords are stored hashed, so a NEW password is issued.
     * @return array{password: string, mailed: bool, error: ?string}
     */
    public function resendCredentials(NetworkRegistrationRequest $req): array
    {
        $user = $req->created_user_id ? User::find($req->created_user_id) : null;
        if ($req->status !== 'approved' || !$user) {
            throw ValidationException::withMessages(['request' => 'This request has no created account to send credentials for.']);
        }

        $member = $req->type === 'vendor' ? null : NetworkMember::where('user_id', $user->id)->first();
        $res = $this->resendCredentialsFor($user, ucfirst($req->type), $member, $req->type === 'vendor' ? route('seller.login') : route('partner.login'));

        $req->update(['meta' => array_merge($req->meta ?? [], ['credentials_mail' => [
            'status' => $res['mailed'] ? 'sent' : 'failed', 'error' => $res['error'], 'at' => now()->toDateTimeString(),
        ]])]);

        return $res;
    }

    /**
     * Issue a NEW password (stored hashed, so the old one cannot be re-sent) and email the login details.
     * @return array{password: string, mailed: bool, error: ?string}
     */
    public function resendCredentialsFor(User $user, string $role, ?NetworkMember $member, string $loginUrl, ?string $code = null): array
    {
        $password = $this->randomPassword();
        $user->update(['password' => $password]);
        $mailed = $this->sendCredentials($user, $password, $role, $member?->referral_code ?? $code, $loginUrl, $member);
        return ['password' => $password, 'mailed' => $mailed, 'error' => $this->lastMailError];
    }

    /**
     * What the admin must see before deleting an agent / worker: money on the account and who is below it.
     */
    public function deletionPreview(NetworkMember $member): array
    {
        $pending = $member->withdrawals()->where('status', 'pending');
        $p = [
            'wallet_balance' => (float)$member->wallet_balance,
            'total_earned' => (float)$member->total_earned,
            'commissions' => $member->commissions()->count(),
            'withdrawals' => $member->withdrawals()->count(),
            'pending_withdrawals' => (clone $pending)->count(),
            'pending_amount' => (float)(clone $pending)->sum('amount'),
        ];
        if ($member->isAgent()) {
            $upline = $member->parent_id ? NetworkMember::with('user:id,name')->find($member->parent_id) : null;
            $p['upline'] = $upline ? ($upline->user->name ?? '#' . $upline->id) : 'SmartBazars (direct)';
            $p['sub_agents'] = $member->children()->count();
            $p['descendants'] = NetworkMember::where('type', NetworkMember::TYPE_AGENT)->where('path', 'like', $member->path . $member->id . '/%')->count();
            $p['vendors'] = $member->vendors()->count();
            $p['sample_agents'] = $member->children()->with('user:id,name')->limit(8)->get()->map(fn($m) => $m->user->name ?? '#' . $m->id)->all();
            $p['sample_vendors'] = $member->vendors()->with('user:id,name')->limit(8)->get()->map(fn($v) => $v->displayName())->all();
        } else {
            $vendor = $member->seller_id ? Seller::find($member->seller_id) : null;
            $p['upline'] = $vendor ? $vendor->displayName() : 'SmartBazars (direct)';
            $p['customers'] = $member->customers()->count();
        }
        return $p;
    }

    /**
     * Delete (archive) an agent / worker. Nothing financial is erased: the row is soft-deleted together with a
     * snapshot, so wallet balance, commissions and withdrawal history stay on record (Deleted Records page).
     * The downline is handed to the deleted ID's upline:
     *  - agent:  sub-agents (and their whole tree) and linked vendors move up; with no upline they become
     *            top level = connected to SmartBazars directly.
     *  - worker: customers who joined through the worker stay with the worker's vendor.
     */
    public function deleteMember(NetworkMember $member, ?int $adminId = null, ?string $note = null): void
    {
        try {
            DB::transaction(function () use ($member, $adminId, $note) {
                $member = NetworkMember::whereKey($member->id)->lockForUpdate()->firstOrFail();
                $user = User::withTrashed()->find($member->user_id);
                $label = $user->name ?? ('#' . $member->id);
                $upline = $member->parent_id ? NetworkMember::with('user:id,name')->find($member->parent_id) : null;
                $uplineLabel = $upline ? ($upline->user->name ?? '#' . $upline->id) : 'SmartBazars (direct)';
                $moved = 0;

                if ($member->isAgent()) {
                    $oldPrefix = $member->path . $member->id . '/';
                    // Sub-tree: drop this agent from every descendant's path, one level up.
                    $moved = NetworkMember::where('type', NetworkMember::TYPE_AGENT)
                        ->where('path', 'like', $oldPrefix . '%')
                        ->update([
                            'path' => DB::raw('CONCAT(' . DB::getPdo()->quote($member->path) . ', SUBSTRING(path, ' . (strlen($oldPrefix) + 1) . '))'),
                            'level' => DB::raw('level - 1'),
                        ]);
                    NetworkMember::where('type', NetworkMember::TYPE_AGENT)->where('parent_id', $member->id)
                        ->update(['parent_id' => $member->parent_id]);
                    // Vendors onboarded by this agent follow the upline (NULL = SmartBazars direct).
                    $moved += Seller::withTrashed()->where('agent_member_id', $member->id)
                        ->update(['agent_member_id' => $member->parent_id]);
                } else {
                    $customers = User::where('referred_by_worker_id', $member->id);
                    if ($member->seller_id) {
                        (clone $customers)->whereNull('referred_by_seller_id')->update(['referred_by_seller_id' => $member->seller_id]);
                    }
                    $moved = $customers->update(['referred_by_worker_id' => null]);
                    $vendor = $member->seller_id ? Seller::withTrashed()->find($member->seller_id) : null;
                    $uplineLabel = $vendor ? $vendor->displayName() : 'SmartBazars (direct)';
                }

                $snapshot = [
                    'name' => $user->name ?? null, 'email' => $user->email ?? null, 'mobile' => $user->mobile ?? null,
                    'type' => $member->type, 'referral_code' => $member->referral_code, 'status' => $member->status,
                    'wallet_balance' => (float)$member->wallet_balance, 'total_earned' => (float)$member->total_earned,
                    'commissions' => $member->commissions()->count(),
                    'withdrawals' => $member->withdrawals()->count(),
                    'pending_withdrawals' => $member->withdrawals()->where('status', 'pending')->count(),
                    'upline' => $uplineLabel, 'downline_moved' => $moved, 'note' => $note,
                ];

                \App\Models\NetworkMappingLog::create([
                    'admin_id' => $adminId, 'entity_type' => $member->type, 'entity_id' => $member->id,
                    'entity_label' => mb_substr($label, 0, 150), 'from_id' => $upline?->id ?? $member->seller_id,
                    'from_label' => mb_substr($uplineLabel, 0, 150), 'to_id' => null,
                    'to_label' => 'DELETED - downline moved to ' . mb_substr($uplineLabel, 0, 120), 'affected' => $moved,
                ]);

                $member->forceFill(['deleted_snapshot' => $snapshot, 'deleted_by' => $adminId])->save();
                $member->delete(); // soft delete: wallet, commissions and withdrawals stay

                if ($user) {
                    // Block login and free the email / mobile for reuse; the name stays for history pages.
                    $user->tokens()->delete();
                    $user->forceFill([
                        'email' => 'deleted-' . $user->id . '-' . time() . '@deleted.invalid',
                        'mobile' => 'D' . $user->id . '-' . dechex(time()),
                    ])->save();
                    $user->delete();
                }
            });
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('SmartBazars member delete failed: ' . $e->getMessage(), ['member_id' => $member->id]);
            throw ValidationException::withMessages(['member' => 'Could not delete this account. Use Suspend instead.']);
        }
    }

    /**
     * Delete (archive) a vendor. Its workers are handled as the admin chose:
     *  - 'move'   → to another vendor ($targetId)
     *  - 'direct' → SmartBazars direct (the house store; no vendor if there is none)
     *  - 'delete' → deleted too (archived like any member)
     * @return array{workers:int, action:string, target:?string}
     */
    public function deleteVendor(Seller $seller, string $action = 'direct', ?int $targetId = null, ?int $adminId = null): array
    {
        $target = null;
        if ($action === 'move') {
            $target = $targetId ? Seller::where('id', $targetId)->where('id', '!=', $seller->id)->first() : null;
            if (!$target) {
                throw ValidationException::withMessages(['target_seller_id' => 'Choose the vendor the workers should move to.']);
            }
        }

        return DB::transaction(function () use ($seller, $action, $target, $adminId) {
            $seller = Seller::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $seller->loadMissing('user:id,name,email,mobile');
            $label = $seller->displayName();
            $agent = $seller->agent_member_id ? NetworkMember::with('user:id,name')->find($seller->agent_member_id) : null;
            $agentLabel = $agent ? ($agent->user->name ?? '#' . $agent->id) : 'SmartBazars (direct)';

            $workers = NetworkMember::where('type', NetworkMember::TYPE_WORKER)->where('seller_id', $seller->id);
            $count = (clone $workers)->count();
            $targetLabel = null;

            if ($action === 'delete') {
                $targetLabel = 'BJOs deleted';
                (clone $workers)->chunkById(100, function ($chunk) use ($adminId, $label) {
                    foreach ($chunk as $w) {
                        $this->deleteMember($w, $adminId, 'Deleted together with vendor ' . $label);
                    }
                });
            } else {
                $destId = $action === 'move'
                    ? $target->id
                    : Seller::where('is_house_store', true)->where('id', '!=', $seller->id)->orderBy('id')->value('id');
                $targetLabel = $action === 'move' ? $target->displayName() : 'SmartBazars (direct)';
                (clone $workers)->update(['seller_id' => $destId]);
            }

            \App\Models\NetworkMappingLog::create([
                'admin_id' => $adminId, 'entity_type' => 'vendor', 'entity_id' => $seller->id,
                'entity_label' => mb_substr($label, 0, 150), 'from_id' => $agent?->id, 'from_label' => mb_substr($agentLabel, 0, 150),
                'to_id' => $target?->id, 'to_label' => mb_substr('DELETED - workers: ' . $targetLabel, 0, 150), 'affected' => $count,
            ]);

            $seller->forceFill([
                'deleted_by' => $adminId,
                'deleted_snapshot' => [
                    'name' => $seller->user->name ?? null, 'email' => $seller->user->email ?? null, 'mobile' => $seller->user->mobile ?? null,
                    'business' => $label, 'referral_code' => $seller->referral_code, 'upline' => $agentLabel,
                    'workers' => $count, 'workers_action' => $action, 'workers_target' => $targetLabel,
                ],
            ])->save();
            $seller->delete();

            return ['workers' => $count, 'action' => $action, 'target' => $targetLabel];
        });
    }

    /**
     * IDs created between $from and $to inside an agent's network: agents in the downline, vendors onboarded by
     * the agent or the downline, and those vendors' workers.
     * @return array{agents: \Illuminate\Support\Collection, vendors: \Illuminate\Support\Collection, workers: \Illuminate\Support\Collection}
     */
    public function addedBetween(NetworkMember $agent, \Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): array
    {
        $prefix = $agent->path . $agent->id . '/%';
        $downlineIds = NetworkMember::where('type', 'agent')->where('path', 'like', $prefix)->pluck('id')->push($agent->id);
        $sellerIds = Seller::whereIn('agent_member_id', $downlineIds)->pluck('id');

        return [
            'agents' => NetworkMember::with(['user:id,name,email,mobile', 'parent.user:id,name'])
                ->where('type', 'agent')->where('path', 'like', $prefix)->whereBetween('created_at', [$from, $to])->latest()->get(),
            'vendors' => Seller::with(['user:id,name,email,mobile', 'agent.user:id,name'])
                ->whereIn('id', $sellerIds)->whereBetween('created_at', [$from, $to])->latest()->get(),
            'workers' => NetworkMember::with(['user:id,name,email,mobile', 'seller.user:id,name'])
                ->where('type', 'worker')->whereIn('seller_id', $sellerIds)->whereBetween('created_at', [$from, $to])->latest()->get(),
        ];
    }

    /** BSO tree for admin/agent views. */
    public function tree(?NetworkMember $root = null, int $maxDepth = 6): array
    {
        $query = NetworkMember::with('user:id,name,email,mobile')
            ->withCount(['vendors', 'children'])
            ->where('type', NetworkMember::TYPE_AGENT);
        $all = $root
            ? $query->where(fn($q) => $q->where('id', $root->id)->orWhere('path', 'like', $root->path . $root->id . '/%'))->get()
            : $query->get();
        $byParent = $all->groupBy(fn($m) => $m->parent_id ?? 0);
        $build = function ($parentId, $depth) use (&$build, $byParent, $maxDepth) {
            if ($depth > $maxDepth) return [];
            return ($byParent[$parentId] ?? collect())->map(fn($m) => [
                'member' => $m,
                'children' => $build($m->id, $depth + 1),
            ])->values()->all();
        };
        if ($root) {
            return [['member' => $all->firstWhere('id', $root->id), 'children' => $build($root->id, 1)]];
        }
        return $build(0, 1);
    }
}
