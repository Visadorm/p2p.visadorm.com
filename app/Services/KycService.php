<?php

namespace App\Services;

use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Events\KycDocumentSubmitted;
use App\Events\KycProfileSubmitted;
use App\Models\KycDocument;
use App\Models\Merchant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KycService
{
    /**
     * A8: submit KYC profile data. First successful submission locks the
     * record — only an admin can amend afterwards.
     */
    public function submitProfile(Merchant $merchant, array $data): Merchant
    {
        if ($merchant->kyc_locked_at !== null) {
            throw new \RuntimeException(__('p2p.kyc_locked'));
        }

        $merchant->fill([
            'full_name' => $data['full_name'] ?? $merchant->full_name,
            'date_of_birth' => $data['date_of_birth'] ?? $merchant->date_of_birth,
            'country_of_birth' => $data['country_of_birth'] ?? $merchant->country_of_birth,
            'country_of_residence' => $data['country_of_residence'] ?? $merchant->country_of_residence,
            'full_address' => $data['full_address'] ?? $merchant->full_address,
            'business_name' => $data['business_name'] ?? $merchant->business_name,
            'country_of_incorporation' => $data['country_of_incorporation'] ?? $merchant->country_of_incorporation,
            'kyc_locked_at' => now(),
        ]);

        $merchant->save();

        Log::info('KYC profile submitted + locked', [
            'merchant_id' => $merchant->id,
            'locked_at' => $merchant->kyc_locked_at?->toIso8601String(),
        ]);

        $fresh = $merchant->fresh();
        KycProfileSubmitted::dispatch($fresh);

        return $fresh;
    }

    /**
     * A8: admin override — clears the lock so the merchant can re-submit.
     * Audit logged.
     */
    public function adminUnlockProfile(Merchant $merchant, int $adminUserId): Merchant
    {
        if ($merchant->kyc_locked_at === null) {
            return $merchant;
        }

        $merchant->update([
            'kyc_locked_at' => null,
            'kyc_unlocked_by' => $adminUserId,
            'kyc_unlocked_at' => now(),
        ]);

        Log::warning('KYC profile unlocked by admin', [
            'merchant_id' => $merchant->id,
            'admin_user_id' => $adminUserId,
            'unlocked_at' => $merchant->kyc_unlocked_at?->toIso8601String(),
        ]);

        return $merchant->fresh();
    }

    /**
     * Upload and store an encrypted KYC document.
     */
    public function uploadDocument(Merchant $merchant, string $type, UploadedFile $file): KycDocument
    {
        $documentType = KycDocumentType::from($type);

        $encryptedContent = encrypt($file->get());
        $storagePath = 'kyc/' . $merchant->id . '/' . uniqid('doc_', true) . '.enc';

        Storage::disk('local')->put($storagePath, $encryptedContent);

        $document = KycDocument::create([
            'merchant_id' => $merchant->id,
            'type' => $documentType,
            'file_path' => $storagePath,
            'original_name' => $file->getClientOriginalName(),
            'status' => KycStatus::Pending,
        ]);

        KycDocumentSubmitted::dispatch($document);

        Log::info('KYC document uploaded', [
            'merchant_id' => $merchant->id,
            'type' => $documentType->value,
            'document_id' => $document->id,
        ]);

        return $document;
    }

    /**
     * Delete a pending KYC document from storage and database.
     */
    public function deleteDocument(KycDocument $document): void
    {
        if ($document->status !== KycStatus::Pending) {
            throw new \RuntimeException(__('kyc.error.cannot_delete_reviewed'));
        }

        Storage::disk('local')->delete($document->file_path);

        Log::info('KYC document deleted', [
            'merchant_id' => $document->merchant_id,
            'document_id' => $document->id,
        ]);

        $document->delete();
    }
}
