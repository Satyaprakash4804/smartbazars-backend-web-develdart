<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\SettingTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Factory;

/**
 * Forgot password with a Firebase phone OTP (no email OTP).
 * Works with whichever OTP gateway the admin selected for login:
 *  Firebase : client sends/confirms the OTP with Firebase Auth -> Firebase ID token ->
 *             POST /api/auth/phone/reset-password { idToken, password, password_confirmation, panel? }
 *             the server verifies the token with the Firebase Admin SDK and reads the *verified* phone from it.
 *  Custom SMS: POST /api/auth/phone/forgot-send { mobile, panel? } (server sends the OTP) then
 *             POST /api/auth/phone/reset-password { mobile, otp, password, password_confirmation, panel? }
 */
class PhonePasswordResetController extends Controller
{
    /** access_panel values a given reset page may touch. No panel = customers/app users only (never admin). */
    private const PANELS = [
        'web' => ['web'], 'customer' => ['web'], 'seller' => ['seller'], 'admin' => ['admin'], 'partner' => ['partner'],
    ];

    public function __construct(protected SettingService $settings, protected \App\Services\OtpService $otp) {}

    /** Does an account exist for this phone? (lets the UI stop before sending an SMS) */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate(['mobile' => 'required|string|min:10|max:16', 'panel' => 'nullable|in:web,customer,seller,admin,partner']);
        $user = $this->findByPhone($data['mobile'], $this->panels($data['panel'] ?? null));
        return response()->json(['success' => true, 'exists' => (bool)$user]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'idToken' => 'required_without:otp|nullable|string',
            'mobile' => 'required_with:otp|nullable|string|min:10|max:16',
            'otp' => 'required_without:idToken|nullable|string|size:6',
            'password' => 'required|string|min:8|max:100|confirmed',
            'panel' => 'nullable|in:web,customer,seller,admin,partner',
        ]);

        try {
            if (!empty($data['idToken'])) {
                $phone = $this->phoneFromFirebase($data['idToken']);
            } else {
                // custom SMS gateway: verify the OTP the server sent
                $m = $this->otp->sanitizeMobile($data['mobile']);
                $v = $this->otp->verifyOtp($m, $data['otp']);
                if (empty($v['success'])) {
                    return $this->fail($v['message'] ?? 'Invalid or expired OTP.', 422);
                }
                $phone = $m;
            }

            $user = $this->findByPhone($phone, $this->panels($data['panel'] ?? null));
            if (!$user) {
                return $this->fail('No account found for this mobile number.', 404);
            }

            $user->forceFill(['password' => $data['password']])->save();   // 'hashed' cast
            $user->tokens()->delete();                                      // sign out every device
            Log::info('Password reset via phone OTP', ['user_id' => $user->id]);

            return response()->json(['success' => true, 'message' => 'Password updated. Please sign in with your new password.']);
        } catch (FailedToVerifyToken $e) {
            return $this->fail('Invalid or expired verification. Please request a new OTP.', 422);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('Phone password reset failed: ' . $e->getMessage());
            return $this->fail('Could not reset the password. Please try again.', 500);
        }
    }

    /** Custom SMS gateway: send the OTP (only to numbers that have an account). */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate(['mobile' => 'required|string|min:10|max:16', 'panel' => 'nullable|in:web,customer,seller,admin,partner']);
        $user = $this->findByPhone($data['mobile'], $this->panels($data['panel'] ?? null));
        if (!$user) {
            return $this->fail('No account found for this mobile number.', 404);
        }
        $result = $this->otp->sendOtp($data['mobile']);
        if (empty($result['success'])) {
            return $this->fail($result['message'] ?? 'Could not send the OTP.', 422);
        }
        return response()->json(['success' => true, 'message' => $result['message'] ?? 'OTP sent.', 'expires_in' => $result['expires_in'] ?? 600]);
    }

    /** Verifies the Firebase ID token and returns the phone number Firebase verified. */
    private function phoneFromFirebase(string $idToken): string
    {
        $auth = (new Factory)->withServiceAccount(storage_path('app/private/settings/service-account-file.json'))->createAuth();
        $verified = $auth->verifyIdToken($idToken);
        $claims = $verified->claims();

        // must come from a phone sign-in done just now
        $provider = $claims->get('firebase')['sign_in_provider'] ?? null;
        if ($provider !== 'phone') {
            throw new \RuntimeException('This token was not issued by phone verification.');
        }
        $authTime = $claims->get('auth_time');
        $authTime = $authTime instanceof \DateTimeInterface ? $authTime->getTimestamp() : (int)$authTime;
        if ($authTime && time() - $authTime > 600) {
            throw new \RuntimeException('The OTP verification expired. Please verify your phone again.');
        }

        $phone = $auth->getUser($claims->get('sub'))->phoneNumber ?? null;
        if (!$phone) {
            throw new \RuntimeException('Phone number not found in the verification.');
        }
        return $phone;
    }

    private function panels(?string $panel): array
    {
        return $panel ? self::PANELS[$panel] : ['web', 'seller', 'partner'];
    }

    /**
     * Same phone matching as the OTP login (phoneCallback), so every number that can log in with OTP can also reset:
     * exact, +digits, digits, and the number with a 1-3 digit country code removed. Most specific match wins.
     */
    private function findByPhone(string $phone, array $panels): ?User
    {
        $digits = preg_replace('/\D+/', '', $phone);
        $variants = [$phone, $digits, '+' . $digits];
        if (strlen($digits) === 10) {            // a bare Indian mobile number
            $variants[] = '91' . $digits;
            $variants[] = '+91' . $digits;
        }
        for ($i = 1; $i <= 3; $i++) {
            if (strlen($digits) > $i + 6) {
                $local = substr($digits, $i);
                $variants[] = $local;
                $variants[] = '+' . $local;
            }
        }
        $variants = array_values(array_unique(array_filter($variants)));

        foreach ($variants as $v) {
            $user = User::where('mobile', $v)->whereIn('access_panel', $panels)->first();
            if ($user) {
                return $user;
            }
        }
        return null;
    }

    private function fail(string $msg, int $code): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $msg], $code);
    }
}
