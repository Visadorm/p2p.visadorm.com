<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Models\KycDocument;
use App\Services\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KycController extends Controller
{
    public function __construct(
        private readonly KycService $kycService,
    ) {}

    /**
     * A8: read KYC profile fields + lock state.
     */
    public function profile(Request $request): JsonResponse
    {
        $merchant = $request->merchant;
        return response()->json([
            'data' => [
                'full_name' => $merchant->full_name,
                'date_of_birth' => $merchant->date_of_birth?->toDateString(),
                'country_of_birth' => $merchant->country_of_birth,
                'country_of_residence' => $merchant->country_of_residence,
                'full_address' => $merchant->full_address,
                'business_name' => $merchant->business_name,
                'country_of_incorporation' => $merchant->country_of_incorporation,
                'kyc_locked_at' => $merchant->kyc_locked_at?->toIso8601String(),
                'is_locked' => $merchant->kyc_locked_at !== null,
            ],
        ]);
    }

    /**
     * A8: submit KYC profile. Locks once submitted — only admin can amend.
     */
    public function submitProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'country_of_birth' => ['required', 'string', 'size:2'],
            'country_of_residence' => ['required', 'string', 'size:2'],
            'full_address' => ['required', 'string', 'max:500'],
            'business_name' => ['nullable', 'string', 'max:200'],
            'country_of_incorporation' => ['nullable', 'string', 'size:2'],
        ]);

        $merchant = $request->merchant;

        try {
            $merchant = $this->kycService->submitProfile($merchant, $validated);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'full_name' => $merchant->full_name,
                'date_of_birth' => $merchant->date_of_birth?->toDateString(),
                'country_of_birth' => $merchant->country_of_birth,
                'country_of_residence' => $merchant->country_of_residence,
                'full_address' => $merchant->full_address,
                'business_name' => $merchant->business_name,
                'country_of_incorporation' => $merchant->country_of_incorporation,
                'kyc_locked_at' => $merchant->kyc_locked_at?->toIso8601String(),
                'is_locked' => true,
            ],
            'message' => __('p2p.kyc_profile_locked'),
        ]);
    }

    /**
     * List merchant's KYC documents.
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $documents = $merchant->kycDocuments()->latest()->get();

        return response()->json([
            'data' => $documents,
            'message' => __('p2p.kyc_documents_loaded'),
        ]);
    }

    /**
     * Upload a KYC document.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', Rule::enum(KycDocumentType::class)],
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $merchant = $request->merchant;

        // A8: once KYC profile is locked, new doc uploads also blocked.
        if ($merchant->kyc_locked_at !== null) {
            return response()->json(['message' => __('p2p.kyc_locked')], 422);
        }

        $document = $this->kycService->uploadDocument(
            merchant: $merchant,
            type: $request->type,
            file: $request->file('file'),
        );

        return response()->json([
            'data' => $document,
            'message' => __('p2p.kyc_uploaded'),
        ], 201);
    }

    /**
     * Delete a pending KYC document.
     */
    public function destroy(Request $request, KycDocument $document): JsonResponse
    {
        $merchant = $request->merchant;

        if ($document->merchant_id !== $merchant->id) {
            return response()->json([
                'message' => __('p2p.forbidden'),
            ], 403);
        }

        if ($document->status !== KycStatus::Pending) {
            return response()->json([
                'message' => __('p2p.kyc_not_pending'),
            ], 422);
        }

        // A8: cannot delete after profile lock.
        if ($merchant->kyc_locked_at !== null) {
            return response()->json([
                'message' => __('p2p.kyc_locked'),
            ], 422);
        }

        $this->kycService->deleteDocument($document);

        return response()->json([
            'message' => __('p2p.kyc_deleted'),
        ]);
    }
}
