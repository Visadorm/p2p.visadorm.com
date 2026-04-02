<?php

namespace App\Services;

use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Events\KycDocumentSubmitted;
use App\Models\KycDocument;
use App\Models\Merchant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KycService
{
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
