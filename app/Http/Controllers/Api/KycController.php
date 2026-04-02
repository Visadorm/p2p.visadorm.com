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

        $this->kycService->deleteDocument($document);

        return response()->json([
            'message' => __('p2p.kyc_deleted'),
        ]);
    }
}
