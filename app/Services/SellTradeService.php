<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StakePaidBy;
use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Events\PaymentMarked;
use App\Events\PaymentProofUploaded;
use App\Events\SellTradeJoined;
use App\Events\TradeMessageSent;
use App\Models\TradeMessage;
use App\Events\TradeCompleted;
use App\Events\TradeInitiated;
use App\Models\Merchant;
use App\Models\Trade;
use App\Settings\BlockchainSettings;
use App\Settings\TradeSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SellTradeService
{
    public function __construct(
        private readonly BlockchainService $blockchain,
        private readonly DisputeService $disputes,
        private readonly TradeSettings $settings,
        private readonly BlockchainSettings $chain,
    ) {
    }

    /**
     * Build sell trade DB record + return on-chain payload for seller wallet.
     *
     * Returned payload lets the frontend:
     *   1. usdc.approve(escrow, approve_amount)
     *   2. send tx to escrow with calldata
     *   3. POST confirm-fund with tx hash
     */
    public function openTrade(Merchant $merchant, array $data, string $sellerWallet): array
    {
        if (! $this->settings->sell_enabled) {
            throw new RuntimeException(__('p2p.sell_disabled'));
        }

        $isCash = (bool) ($data['is_cash_trade'] ?? false);
        if ($isCash && ! $this->settings->sell_cash_trade_enabled) {
            throw new RuntimeException(__('p2p.cash_disabled'));
        }

        $amount = (float) $data['amount'];
        if ($amount < $this->settings->global_min_trade) {
            throw new RuntimeException(__('p2p.below_min'));
        }
        if ($amount > $this->settings->global_max_trade) {
            throw new RuntimeException(__('p2p.above_max'));
        }

        $sellerWallet = strtolower($sellerWallet);
        if (strtolower($merchant->wallet_address) === $sellerWallet) {
            throw new InvalidArgumentException(__('p2p.cannot_trade_with_self'));
        }

        // A1: enforce one active sell trade per seller wallet (any merchant).
        // Cache lock prevents the check-then-create race when two requests
        // hit openTrade in parallel — both could pass the exists() check
        // before either Trade::create commits. Lock is held through the
        // DB::transaction so the create lands while the gate still holds.
        $lock = Cache::lock("sell-trade-open:{$sellerWallet}", 10);
        if (! $lock->block(5)) {
            throw new RuntimeException(__('trade.error.active_sell_trade_exists'));
        }

        try {
            $activeExists = Trade::query()
                ->where('seller_wallet', $sellerWallet)
                ->where('type', TradeType::Sell)
                ->whereIn('status', TradeStatus::activeSellStatuses())
                ->exists();

            if ($activeExists) {
                throw new RuntimeException(__('trade.error.active_sell_trade_exists'));
            }

            $requireStake = $this->resolveStakeRequirement($data, $isCash);
            $stakeAmountUsdc = $requireStake ? $this->settings->sell_anti_spam_stake_usdc : 0;
            $feeAmount = round($amount * 0.002, 6);
            $approveAmountWei = $this->toWei($amount + $feeAmount + $stakeAmountUsdc);
            $amountWei = $this->toWei($amount);

            $expiresAt = isset($data['expires_at'])
                ? (int) strtotime($data['expires_at'])
                : (time() + ($this->settings->sell_default_expiry_minutes * 60));

            $tradeId = '0x' . bin2hex(random_bytes(32));
            $tradeHash = $tradeId; // single-source-of-truth

            return DB::transaction(function () use (
                $merchant, $sellerWallet, $data, $amount, $feeAmount,
                $stakeAmountUsdc, $isCash, $expiresAt, $tradeId,
                $tradeHash, $requireStake, $amountWei, $approveAmountWei
            ) {
                $trade = Trade::create([
                    'trade_hash' => $tradeHash,
                    'merchant_id' => $merchant->id,
                    'seller_wallet' => $sellerWallet,
                    'buyer_wallet' => strtolower($merchant->wallet_address),
                    'amount_usdc' => $amount,
                    'amount_fiat' => round($amount * (float) $data['fiat_rate'], 2),
                    'currency_code' => strtoupper($data['currency']),
                    'exchange_rate' => $data['fiat_rate'],
                    'fee_amount' => $feeAmount,
                    'payment_method' => (string) $data['payment_method_id'],
                    'is_cash_trade' => $isCash,
                    'meeting_location' => $isCash ? ($data['meeting_location'] ?? null) : null,
                    'type' => TradeType::Sell,
                    'status' => TradeStatus::Pending,
                    'stake_amount' => $stakeAmountUsdc,
                    'stake_paid_by' => $requireStake ? StakePaidBy::Buyer : null, // seller paid; reuse Buyer enum slot for "user"
                    'expires_at' => date('Y-m-d H:i:s', $expiresAt),
                ]);

                $calldata = $this->blockchain->openSellTradeCalldata(
                    tradeHash: $tradeHash,
                    merchant: $merchant->wallet_address,
                    amountWei: $amountWei,
                    expiresAt: $expiresAt,
                    requireStake: $requireStake,
                    isCashTrade: $isCash,
                    meetingLocation: (string) ($data['meeting_location'] ?? '')
                );

                return [
                    'trade' => $trade,
                    'trade_hash' => $tradeHash,
                    'trade_id' => $tradeId,
                    'calldata' => $calldata,
                    'escrow_address' => $this->chain->trade_escrow_address,
                    'approve_amount' => $approveAmountWei,
                    'expires_at' => date('c', $expiresAt),
                    'stake_required' => $requireStake,
                    'stake_amount_usdc' => (string) $stakeAmountUsdc,
                ];
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Verify on-chain fund tx + persist hash. Idempotent.
     */
    public function confirmFund(Trade $trade, string $txHash): Trade
    {
        $this->ensureSellTrade($trade);

        if ($trade->fund_tx_hash === $txHash) {
            return $trade->fresh();
        }
        if (! empty($trade->fund_tx_hash)) {
            throw new InvalidArgumentException(__('p2p.tx_already_recorded'));
        }

        $this->verifyReceipt($txHash, $trade->seller_wallet);
        $receipt = $this->blockchain->getTransactionReceipt($txHash);
        // B2: pass expectedSeller so the event's indexed seller topic must match.
        if (! $this->blockchain->parseSellTradeOpenedLog($receipt, $trade->trade_hash, $trade->seller_wallet)) {
            throw new RuntimeException(__('p2p.tx_missing_event'));
        }

        $updates = ['fund_tx_hash' => $txHash];

        // Cash trade: capture NFT token ID minted in same tx
        if ($trade->is_cash_trade) {
            $tokenId = $this->blockchain->parseNftTokenIdFromReceipt($receipt);
            if ($tokenId !== null) {
                $updates['nft_token_id'] = $tokenId;
            }
        }

        $trade->update($updates);
        event(new TradeInitiated($trade));

        return $trade->fresh();
    }

    public function confirmJoin(Trade $trade, Merchant $merchant, string $txHash): Trade
    {
        $this->ensureSellTrade($trade);

        if ($trade->join_tx_hash === $txHash) {
            return $trade->fresh();
        }
        if (strtolower($merchant->wallet_address) !== strtolower($trade->buyer_wallet)) {
            throw new InvalidArgumentException(__('p2p.not_authorized'));
        }
        if ($trade->status !== TradeStatus::Pending) {
            throw new RuntimeException(__('p2p.bad_trade_status'));
        }

        $this->verifyReceipt($txHash, $merchant->wallet_address);
        $receipt = $this->blockchain->getTransactionReceipt($txHash);
        if (! $this->blockchain->parseSellTradeJoinedLog($receipt, $trade->trade_hash)) {
            throw new RuntimeException(__('p2p.tx_missing_event'));
        }

        $trade->update([
            'join_tx_hash' => $txHash,
            'status' => TradeStatus::EscrowLocked,
            'escrow_tx_hash' => $txHash,
        ]);

        event(new SellTradeJoined($trade->fresh()));

        return $trade->fresh();
    }

    public function confirmMarkPaid(Trade $trade, Merchant $merchant, string $txHash): Trade
    {
        $this->ensureSellTrade($trade);

        if ($trade->mark_paid_tx_hash === $txHash) {
            return $trade->fresh();
        }
        if (strtolower($merchant->wallet_address) !== strtolower($trade->buyer_wallet)) {
            throw new InvalidArgumentException(__('p2p.not_authorized'));
        }
        if ($trade->status !== TradeStatus::EscrowLocked) {
            throw new RuntimeException(__('p2p.bad_trade_status'));
        }

        $this->verifyReceipt($txHash, $merchant->wallet_address);
        $receipt = $this->blockchain->getTransactionReceipt($txHash);
        if (! $this->blockchain->parseSellPaymentMarkedLog($receipt, $trade->trade_hash)) {
            throw new RuntimeException(__('p2p.tx_missing_event'));
        }

        $trade->update([
            'mark_paid_tx_hash' => $txHash,
            'status' => TradeStatus::PaymentSent,
        ]);

        event(new PaymentMarked($trade->fresh()));

        return $trade->fresh();
    }

    public function attachCashProof(Trade $trade, UploadedFile $file, ?string $note): Trade
    {
        $this->ensureSellTrade($trade);
        if (! $trade->is_cash_trade) {
            throw new InvalidArgumentException(__('p2p.not_cash_trade'));
        }

        $path = $file->store('cash-proofs/' . date('Y/m'), 'local');
        $trade->update(['cash_proof_url' => $path]);

        return $trade->fresh();
    }

    /**
     * A4: buyer uploads proof of fiat payment so seller can verify before release.
     * Allowed during EscrowLocked (preferred — uploaded with mark-paid) and
     * PaymentSent (after marking, late upload).
     */
    public function attachPaymentProof(Trade $trade, Merchant $caller, UploadedFile $file): Trade
    {
        $this->ensureSellTrade($trade);

        if (strtolower($caller->wallet_address) !== strtolower($trade->buyer_wallet)) {
            throw new InvalidArgumentException(__('p2p.not_authorized'));
        }
        if (! in_array($trade->status, [TradeStatus::EscrowLocked, TradeStatus::PaymentSent], true)) {
            throw new RuntimeException(__('p2p.bad_trade_status'));
        }

        $path = $file->store('payment-proofs/' . date('Y/m'), 'local');

        $trade->update([
            'payment_proof_url' => $path,
            'payment_proof_uploaded_at' => now(),
        ]);

        $fresh = $trade->fresh();
        event(new PaymentProofUploaded($fresh));

        return $fresh;
    }

    /**
     * A5: post a private message inside the trade. Either party can send.
     * Locked once trade is Completed/Cancelled/Expired/Resolved.
     * Body and/or attachment — at least one required.
     */
    public function postMessage(Trade $trade, Merchant $caller, ?string $body, ?\Illuminate\Http\UploadedFile $attachment): TradeMessage
    {
        $this->ensureSellTrade($trade);

        $callerWallet = strtolower($caller->wallet_address);
        $isSeller = $callerWallet === strtolower($trade->seller_wallet);
        $isBuyer = $callerWallet === strtolower($trade->buyer_wallet);

        if (! $isSeller && ! $isBuyer) {
            throw new InvalidArgumentException(__('p2p.not_authorized'));
        }

        if (in_array($trade->status, [TradeStatus::Completed, TradeStatus::Cancelled, TradeStatus::Expired, TradeStatus::Resolved], true)) {
            throw new RuntimeException(__('trade.error.chat_locked'));
        }

        $body = $body !== null ? trim($body) : null;
        if (($body === null || $body === '') && ! $attachment) {
            throw new InvalidArgumentException(__('trade.error.chat_empty'));
        }

        $attachmentPath = null;
        if ($attachment) {
            $attachmentPath = $attachment->store('trade-messages/' . date('Y/m'), 'local');
        }

        $message = TradeMessage::create([
            'trade_id' => $trade->id,
            'sender_wallet' => $callerWallet,
            'sender_role' => $isSeller ? 'seller' : 'buyer',
            'body' => $body ?: null,
            'attachment_path' => $attachmentPath,
        ]);

        event(new TradeMessageSent($message->fresh(['trade'])));

        return $message;
    }

    public function setSellerVerifiedPayment(Trade $trade, Merchant $caller, bool $verified): Trade
    {
        $this->ensureSellTrade($trade);

        if (strtolower($caller->wallet_address) !== strtolower($trade->seller_wallet)) {
            throw new InvalidArgumentException(__('p2p.not_authorized'));
        }
        if (! in_array($trade->status, [TradeStatus::PaymentSent, TradeStatus::EscrowLocked], true)) {
            throw new RuntimeException(__('p2p.bad_trade_status'));
        }

        $trade->update(['seller_verified_payment' => $verified]);

        return $trade->fresh();
    }

    public function confirmRelease(Trade $trade, string $txHash): Trade
    {
        $this->ensureSellTrade($trade);

        if ($trade->release_tx_hash === $txHash) {
            return $trade->fresh();
        }
        if (! empty($trade->release_tx_hash)) {
            throw new InvalidArgumentException(__('p2p.tx_already_recorded'));
        }

        $this->verifyReceipt($txHash, $trade->seller_wallet);
        $receipt = $this->blockchain->getTransactionReceipt($txHash);
        if (! $this->blockchain->parseSellEscrowReleasedLog($receipt, $trade->trade_hash)) {
            throw new RuntimeException(__('p2p.tx_missing_event'));
        }

        $trade->update([
            'release_tx_hash' => $txHash,
            'status' => TradeStatus::Completed,
            'completed_at' => now(),
        ]);

        event(new TradeCompleted($trade->fresh()));

        return $trade->fresh();
    }

    public function openDispute(Trade $trade, Merchant $caller, string $txHash, string $reason): Trade
    {
        $this->ensureSellTrade($trade);

        $callerWallet = strtolower($caller->wallet_address);
        $isParty = $callerWallet === strtolower($trade->seller_wallet)
            || $callerWallet === strtolower($trade->buyer_wallet);

        if (! $isParty) {
            throw new InvalidArgumentException(__('p2p.not_authorized'));
        }

        $this->verifyReceipt($txHash, $caller->wallet_address);
        $receipt = $this->blockchain->getTransactionReceipt($txHash);
        if (! $this->blockchain->parseDisputeOpenedLog($receipt, $trade->trade_hash)) {
            throw new RuntimeException(__('p2p.tx_missing_event'));
        }

        // B7: lock the trade row inside a transaction so two simultaneous
        // dispute opens (one from each party) cannot both pass the status
        // check + write before either commits.
        return DB::transaction(function () use ($trade, $txHash, $reason, $callerWallet) {
            /** @var Trade $locked */
            $locked = Trade::query()->whereKey($trade->id)->lockForUpdate()->first();
            if (! $locked) {
                throw new RuntimeException(__('p2p.sell_trade_not_found'));
            }

            if (! in_array($locked->status, [TradeStatus::EscrowLocked, TradeStatus::PaymentSent], true)) {
                // Already disputed (or transitioned past) — return idempotently if same tx.
                if ($locked->dispute_tx_hash === $txHash) {
                    return $locked->fresh();
                }
                throw new RuntimeException(__('p2p.sell_trade_not_disputable'));
            }

            if ($locked->dispute_tx_hash !== $txHash) {
                $locked->update([
                    'dispute_tx_hash' => $txHash,
                    'status' => TradeStatus::Disputed,
                    'disputed_at' => now(),
                ]);

                $this->disputes->openDispute($locked->fresh(), $callerWallet, $reason);
            }

            return $locked->fresh();
        });
    }

    public function cancel(Trade $trade, string $txHash): Trade
    {
        $this->ensureSellTrade($trade);

        if ($trade->cancel_tx_hash === $txHash) {
            return $trade->fresh();
        }
        if (! in_array($trade->status, [TradeStatus::Pending, TradeStatus::EscrowLocked], true)) {
            throw new RuntimeException(__('p2p.bad_trade_status'));
        }

        $expectedFrom = $trade->status === TradeStatus::Pending
            ? $trade->seller_wallet
            : $trade->buyer_wallet;
        $this->verifyReceipt($txHash, $expectedFrom);
        $receipt = $this->blockchain->getTransactionReceipt($txHash);
        if (! $this->blockchain->parseTradeCancelledLog($receipt, $trade->trade_hash)) {
            throw new RuntimeException(__('p2p.tx_missing_event'));
        }

        $trade->update([
            'cancel_tx_hash' => $txHash,
            'status' => TradeStatus::Cancelled,
        ]);

        return $trade->fresh();
    }

    // ─── Private helpers ─────────────────────────────────────────────

    private function ensureSellTrade(Trade $trade): void
    {
        if ($trade->type !== TradeType::Sell) {
            throw new InvalidArgumentException(__('p2p.not_sell_trade'));
        }
    }

    private function resolveStakeRequirement(array $data, bool $isCash): bool
    {
        if ($isCash) {
            return $this->settings->sell_require_stake_cash;
        }
        $entryPath = $data['entry_path'] ?? 'merchant_page';
        return $entryPath === 'private_link'
            ? $this->settings->sell_require_stake_link
            : $this->settings->sell_require_stake_public;
    }

    private function verifyReceipt(string $txHash, string $expectedFrom): array
    {
        $receipt = $this->blockchain->getTransactionReceipt($txHash);
        if (! is_array($receipt)) {
            throw new RuntimeException(__('p2p.tx_verification_failed'));
        }
        if (($receipt['status'] ?? '') !== '0x1') {
            throw new RuntimeException(__('p2p.tx_verification_failed'));
        }
        if (strtolower($receipt['to'] ?? '') !== strtolower($this->chain->trade_escrow_address)) {
            throw new RuntimeException(__('p2p.tx_verification_failed'));
        }
        if (strtolower($receipt['from'] ?? '') !== strtolower($expectedFrom)) {
            throw new RuntimeException(__('p2p.tx_wrong_from'));
        }
        return $receipt;
    }

    private function toWei(float $usdc): string
    {
        return Str::of(number_format($usdc * 1_000_000, 0, '.', ''))->toString();
    }
}
