<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Models\User;
use App\Services\WalletAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private readonly WalletAuthService $walletAuthService,
    ) {}

    /**
     * Generate a nonce for wallet signature verification.
     */
    public function nonce(Request $request): JsonResponse
    {
        $request->validate([
            'wallet_address' => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
        ]);

        $nonce = $this->walletAuthService->generateNonce();
        $message = $this->walletAuthService->buildSignMessage($nonce);

        Cache::put(
            'wallet_nonce:' . strtolower($request->wallet_address),
            $nonce,
            now()->addMinutes(5)
        );

        return response()->json([
            'data' => [
                'nonce' => $nonce,
                'message' => $message,
            ],
            'message' => __('p2p.nonce_generated'),
        ]);
    }

    /**
     * Verify wallet signature and authenticate.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'wallet_address' => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'signature' => ['required', 'string'],
            'nonce' => ['required', 'string'],
        ]);

        $walletAddress = strtolower($request->wallet_address);
        $cachedNonce = Cache::get('wallet_nonce:' . $walletAddress);

        if (! $cachedNonce || $cachedNonce !== $request->nonce) {
            Log::warning('Nonce verification failed', ['wallet' => $walletAddress]);

            return response()->json([
                'message' => __('p2p.nonce_invalid'),
            ], 422);
        }

        $message = $this->walletAuthService->buildSignMessage($request->nonce);

        if (! $this->walletAuthService->verifySignature($message, $request->signature, $request->wallet_address)) {
            return response()->json([
                'message' => __('p2p.signature_invalid'),
            ], 422);
        }

        Cache::forget('wallet_nonce:' . $walletAddress);

        $user = User::firstOrCreate(
            ['wallet_address' => $walletAddress],
            ['name' => Str::substr($walletAddress, 0, 10)]
        );

        $defaultRank = MerchantRank::orderBy('sort_order')->first();

        $merchant = Merchant::firstOrCreate(
            ['wallet_address' => $walletAddress],
            [
                'username' => 'user_' . substr($walletAddress, 2, 8),
                'rank_id' => $defaultRank?->id,
                'is_active' => true,
                'member_since' => now(),
            ]
        );

        // Notify admins on new merchant registration
        if ($merchant->wasRecentlyCreated) {
            \App\Listeners\NotifyAdminOnMerchantRegistered::notify(
                $merchant->username,
                substr($walletAddress, 0, 10) . '...'
            );
        }

        $token = $user->createToken('wallet-auth', ['*'], now()->addDays(30))->plainTextToken;

        Log::info('Wallet login successful', ['wallet' => $walletAddress]);

        return response()->json([
            'data' => [
                'token' => $token,
                'merchant' => $merchant->load('rank'),
            ],
            'message' => __('p2p.login_success'),
        ]);
    }

    /**
     * Logout and revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => __('p2p.logout_success'),
        ]);
    }

    /**
     * Get the authenticated merchant profile.
     */
    public function me(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $merchant->load(['rank', 'currencies', 'paymentMethods']);

        return response()->json([
            'data' => $merchant,
            'message' => __('p2p.profile_loaded'),
        ]);
    }
}
