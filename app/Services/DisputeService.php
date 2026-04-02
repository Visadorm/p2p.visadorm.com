<?php

namespace App\Services;

use App\Enums\DisputeStatus;
use App\Enums\TradeStatus;
use App\Events\DisputeOpened;
use App\Events\TradeCompleted;
use App\Models\Dispute;
use App\Models\Trade;
use Illuminate\Http\UploadedFile;

class DisputeService
{
    /**
     * Open a dispute on a trade.
     */
    public function openDispute(Trade $trade, string $openedBy, string $reason): Dispute
    {
        $dispute = $trade->dispute()->create([
            'opened_by' => $openedBy,
            'reason' => $reason,
            'status' => DisputeStatus::Open,
            'evidence' => [],
        ]);

        $trade->update([
            'status' => TradeStatus::Disputed,
            'disputed_at' => now(),
        ]);

        DisputeOpened::dispatch($dispute);

        return $dispute;
    }

    /**
     * Resolve a dispute and update trade status on-chain.
     */
    public function resolveDispute(Dispute $dispute, string $winner, string $txHash): void
    {
        $trade = $dispute->trade;
        $merchantWallet = strtolower($trade->merchant->wallet_address);

        // Determine the resolution status based on who won
        $resolvedStatus = strtolower($winner) === $merchantWallet
            ? DisputeStatus::ResolvedMerchant
            : DisputeStatus::ResolvedBuyer;

        $dispute->update([
            'status'             => $resolvedStatus,
            'resolved_by'        => $winner,
            'resolution_tx_hash' => $txHash,
        ]);

        $trade->update([
            'status'           => TradeStatus::Completed,
            'completed_at'     => now(),
            'release_tx_hash'  => $txHash,
        ]);

        TradeCompleted::dispatch($trade);
    }

    /**
     * Upload and attach evidence to an open dispute.
     */
    public function submitEvidence(Dispute $dispute, UploadedFile $file, string $uploadedBy): Dispute
    {
        $path = $file->store('disputes/' . $dispute->id, 'local');

        $evidence = $dispute->evidence ?? [];
        $evidence[] = [
            'uploaded_by' => $uploadedBy,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now()->toISOString(),
        ];

        $dispute->update(['evidence' => $evidence]);

        return $dispute->fresh();
    }
}
