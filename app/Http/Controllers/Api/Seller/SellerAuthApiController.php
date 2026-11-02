<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use App\Http\Requests\Seller\StoreSellerRequest;
use App\Services\SellerService;
use App\Traits\AuthTrait;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Models\Setting;

#[Group('Seller Authentication')]
class SellerAuthApiController
{
    use AuthTrait;

    protected string $role = 'seller';

    protected $sellerService;

    public function __construct(SellerService $sellerService)
    {
        $this->sellerService = $sellerService;
    }

    /**
     * creating sellers API
     */
    public function createSeller(StoreSellerRequest $request): JsonResponse
    {
        // Restrict seller self-registration in Single Vendor mode
        if (Setting::isSystemVendorTypeSingle()) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.seller_registration_disabled'),
                status: 403
            );
        }

        try {
            $validated = $request->validated();
            $validated['verification_status'] = SellerVisibilityStatusEnum::Draft();
            $validated['visibility_status'] = SellerVerificationStatusEnum::NotApproved();
            $seller = $this->sellerService->createSeller(
                $validated,
                $request->allFiles()
            );

            // Joining fee (admin-set, Razorpay). The website redirects the vendor to payment_url.
            $fee = app(\App\Services\SmartBazars\NetworkFeeService::class)->startForSeller($seller);
            if ($fee) {
                app(\App\Services\SmartBazars\NetworkFeeService::class)->sendLink($fee, 'Vendor');
            }
            $seller->setAttribute('joining_fee', [
                'required' => (bool)$fee,
                'amount' => $fee ? (float)$fee->amount : 0,
                'payment_url' => $fee?->payUrl(),
            ]);

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.seller_created_successfully',
                $seller,
                201
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.validation_failed' . $e->getMessage(),
                data: $e->errors(),
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.seller_created_successfully',
                status: 500
            );
        }
    }
}
