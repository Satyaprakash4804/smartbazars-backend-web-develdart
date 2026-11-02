<?php

namespace App\Http\Controllers\Admin\SmartBazars;

use App\Http\Controllers\Controller;
use App\Models\NetworkMember;
use App\Models\Seller;
use App\Services\SmartBazars\NetworkMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Admin > BSOs & BJOs > Network Mapping (drag & drop upline / downline management). */
class NetworkMappingController extends Controller
{
    public function __construct(protected NetworkMappingService $mapping) {}

    public function index()
    {
        return view('admin.smartbazars.network.mapping');
    }

    public function data(): JsonResponse
    {
        return response()->json($this->mapping->graph());
    }

    public function workers(Seller $seller): JsonResponse
    {
        return response()->json(['workers' => $this->mapping->workersOf($seller)]);
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json(['results' => $this->mapping->search((string)$request->query('q'))]);
    }

    /** type = agent  → target_id = parent agent id | null (top level)
     *  type = vendor → target_id = agent id        | null (unassigned)
     *  type = worker → target_id = vendor (seller) id */
    public function move(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:agent,vendor,worker',
            'id' => 'required|integer',
            'target_id' => 'nullable|integer',
        ]);
        $adminId = Auth::id();

        try {
            $log = match ($data['type']) {
                'agent' => $this->mapping->moveAgent(
                    NetworkMember::where('type', 'agent')->findOrFail($data['id']),
                    $data['target_id'] ? NetworkMember::where('type', 'agent')->findOrFail($data['target_id']) : null,
                    $adminId),
                'vendor' => $this->mapping->moveVendor(
                    Seller::findOrFail($data['id']),
                    $data['target_id'] ? NetworkMember::where('type', 'agent')->findOrFail($data['target_id']) : null,
                    $adminId),
                'worker' => $this->mapping->moveWorker(
                    NetworkMember::where('type', 'worker')->findOrFail($data['id']),
                    Seller::findOrFail((int)$data['target_id']),
                    $adminId),
            };
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => $e->validator->errors()->first()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Mapping updated.', 'log' => $log]);
    }

    /** Suspend / activate an agent or worker straight from the tree. */
    public function status(Request $request, NetworkMember $member): JsonResponse
    {
        $member->update(['status' => $member->status === 'active' ? 'suspended' : 'active']);
        return response()->json(['ok' => true, 'status' => $member->status]);
    }
}
