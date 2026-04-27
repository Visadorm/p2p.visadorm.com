<?php

namespace App\Services;

use App\Settings\BlockchainSettings;
use Illuminate\Support\Facades\Http;
use kornrunner\Keccak;

class BlockchainService
{
    private string $rpcUrl;

    private string $tradeEscrowAddress;

    private string $soulboundNftAddress;

    private string $usdcAddress;

    private int $chainId;

    private array $tradeEscrowAbi;

    private array $soulboundNftAbi;

    public function __construct()
    {
        $settings = app(BlockchainSettings::class);
        $this->rpcUrl = $settings->rpc_url ?: 'https://base-sepolia.g.alchemy.com/v2/' . config('blockchain.alchemy_api_key', '');
        $this->tradeEscrowAddress = $settings->trade_escrow_address;
        $this->soulboundNftAddress = $settings->soulbound_nft_address;
        $this->usdcAddress = $settings->usdc_address;
        $this->chainId = $settings->chain_id;

        $escrowAbiPath = storage_path('app/contracts/TradeEscrowContract.abi.json');
        $nftAbiPath = storage_path('app/contracts/SoulboundTradeNFT.abi.json');

        $this->tradeEscrowAbi = file_exists($escrowAbiPath)
            ? json_decode(file_get_contents($escrowAbiPath), true)
            : [];

        $this->soulboundNftAbi = file_exists($nftAbiPath)
            ? json_decode(file_get_contents($nftAbiPath), true)
            : [];
    }

    // ─── Read-Only Calls (no gas needed) ────────────────────────

    /**
     * Get the total escrow balance deposited by a merchant.
     * Calls: merchantEscrowBalance(address) -> uint256
     */
    public function getMerchantEscrowBalance(string $merchantAddress): string
    {
        return $this->callEscrowContract('merchantEscrowBalance', [$merchantAddress]);
    }

    /**
     * Get the available (unlocked) balance for a merchant.
     * Calls: getAvailableBalance(address) -> uint256
     */
    public function getAvailableBalance(string $merchantAddress): string
    {
        return $this->callEscrowContract('getAvailableBalance', [$merchantAddress]);
    }

    /**
     * Get the amount locked in active trades for a merchant.
     * Calls: merchantLockedInTrades(address) -> uint256
     */
    public function getLockedInTrades(string $merchantAddress): string
    {
        return $this->callEscrowContract('merchantLockedInTrades', [$merchantAddress]);
    }

    /**
     * Get a trade struct from the contract.
     * Calls: trades(bytes32) -> Trade struct field order:
     *   0: merchant (address)
     *   1: buyer (address)
     *   2: amount (uint256)
     *   3: stakeAmount (uint256)
     *   4: stakePaidBy (address)
     *   5: status (uint8/enum)
     *   6: isPrivate (bool)
     *   7: createdAt (uint256)
     *   8: expiresAt (uint256)
     */
    public function getTradeOnChain(string $tradeId): array
    {
        $result = $this->callEscrowContract('trades', [$tradeId]);

        // The trades mapping returns a tuple decoded as a flat array
        if (is_array($result) && count($result) >= 9) {
            return [
                'merchant'     => $result[0],
                'buyer'        => $result[1],
                'amount'       => $result[2],
                'stakeAmount'  => $result[3],
                'stakePaidBy'  => $result[4],
                'status'       => $result[5],
                'isPrivate'    => $result[6],
                'createdAt'    => $result[7],
                'expiresAt'    => $result[8],
            ];
        }

        return [];
    }

    /**
     * Check if the contract is paused.
     * Calls: paused() -> bool
     */
    public function isPaused(): bool
    {
        $result = $this->callEscrowContract('paused', []);

        return $result === '0x0000000000000000000000000000000000000000000000000000000000000001';
    }

    /**
     * Get the fee wallet address from the contract.
     * Calls: feeWallet() -> address
     */
    public function getFeeWallet(): string
    {
        $result = $this->callEscrowContract('feeWallet', []);

        return $this->decodeAddress($result);
    }

    /**
     * Get the platform fee in basis points.
     * Calls: FEE_BPS() -> uint256
     */
    public function getFeeBps(): string
    {
        return $this->callEscrowContract('FEE_BPS', []);
    }

    /**
     * Get the required merchant stake amount.
     * Calls: STAKE_AMOUNT() -> uint256
     */
    public function getStakeAmount(): string
    {
        return $this->callEscrowContract('STAKE_AMOUNT', []);
    }

    /**
     * Get the current block number from the RPC.
     */
    public function getBlockNumber(): int
    {
        $result = $this->rpcCall('eth_blockNumber', []);

        return hexdec($result);
    }

    /**
     * Get ETH balance for an address (useful for gas wallet monitoring).
     */
    public function getEthBalance(string $address): string
    {
        $result = $this->rpcCall('eth_getBalance', [$address, 'latest']);

        return $this->hexToDecimal($result);
    }

    /**
     * Get USDC balance for an address (ERC20 balanceOf).
     * Returns human-readable USDC value (6 decimals).
     */
    public function getUsdcBalance(string $address): string
    {
        // balanceOf(address) = keccak256 selector 0x70a08231
        // Strip '0x' prefix as a string (not char-by-char) then left-pad to 32 bytes
        $paddedAddress = str_pad(str_replace('0x', '', strtolower($address)), 64, '0', STR_PAD_LEFT);
        $data = '0x70a08231' . $paddedAddress;

        $result = $this->rpcCall('eth_call', [
            ['to' => $this->usdcAddress, 'data' => $data],
            'latest',
        ]);

        // Convert hex to decimal, then divide by 1e6 (USDC has 6 decimals)
        $rawBalance = $this->hexToDecimal($result ?? '0x0');

        return bcdiv($rawBalance, '1000000', 6);
    }

    /**
     * Get USDC allowance granted by owner to the escrow contract.
     * Calls: USDC.allowance(address owner, address spender) -> uint256
     * Returns the allowance as a raw decimal string (atomic USDC units, 6 decimals).
     */
    public function getUsdcAllowance(string $ownerAddress): string
    {
        // allowance(address,address) selector: 0xdd62ed3e
        $owner   = str_pad(str_replace('0x', '', strtolower($ownerAddress)), 64, '0', STR_PAD_LEFT);
        $spender = str_pad(str_replace('0x', '', strtolower($this->tradeEscrowAddress)), 64, '0', STR_PAD_LEFT);
        $data    = '0xdd62ed3e' . $owner . $spender;

        $result = $this->rpcCall('eth_call', [
            ['to' => $this->usdcAddress, 'data' => $data],
            'latest',
        ]);

        return $this->hexToDecimal($result ?? '0x0');
    }

    /**
     * Estimate gas for a transaction.
     * Returns gas units as decimal string.
     */
    public function estimateGas(array $tx): string
    {
        $result = $this->rpcCall('eth_estimateGas', [$tx]);

        return $this->hexToDecimal($result ?? '0x5208');
    }

    /**
     * Get transaction receipt. Returns null if transaction is pending.
     */
    public function getTransactionReceipt(string $txHash): ?array
    {
        return $this->rpcCall('eth_getTransactionReceipt', [$txHash]);
    }

    // ─── Write Operations (require gas wallet key) ───────────────

    /**
     * Deposit USDC into escrow for a merchant.
     * Calls: depositEscrow(address, uint256)
     */
    public function depositEscrow(string $merchantAddress, string $amountUsdc): string
    {
        $signerKey = config('blockchain.operator_private_key');
        $data = $this->encodeFunctionCall($this->tradeEscrowAbi, 'depositEscrow', [$merchantAddress, $amountUsdc]);

        return $this->sendTransaction($this->tradeEscrowAddress, $data, $signerKey);
    }

    /**
     * Withdraw USDC from escrow for a merchant.
     * Calls: withdrawEscrow(address, uint256)
     */
    public function withdrawEscrow(string $merchantAddress, string $amountUsdc): string
    {
        $signerKey = config('blockchain.operator_private_key');
        $data = $this->encodeFunctionCall($this->tradeEscrowAbi, 'withdrawEscrow', [$merchantAddress, $amountUsdc]);

        return $this->sendTransaction($this->tradeEscrowAddress, $data, $signerKey);
    }

    /**
     * Initiate a new trade on-chain.
     * Calls: initiateTrade(bytes32, address, address, uint256, bool, uint256)
     */
    public function initiateTrade(string $tradeHash, string $merchant, string $buyer, string $amount, bool $isPrivate, int $expiresAt): string
    {
        $signerKey = config('blockchain.operator_private_key');
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        $data = $this->encodeFunctionCall($this->tradeEscrowAbi, 'initiateTrade', [
            $bytes32Hash,
            $merchant,
            $buyer,
            $amount,
            $isPrivate,
            (string) $expiresAt,
        ]);

        return $this->sendTransaction($this->tradeEscrowAddress, $data, $signerKey);
    }

    /**
     * Mark payment as sent by buyer.
     * Calls: markPaymentSent(bytes32)
     */
    public function markPaymentSent(string $tradeHash): string
    {
        $signerKey = config('blockchain.operator_private_key');
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        $data = $this->encodeFunctionCall($this->tradeEscrowAbi, 'markPaymentSent', [$bytes32Hash]);

        return $this->sendTransaction($this->tradeEscrowAddress, $data, $signerKey);
    }

    /**
     * Confirm payment received by merchant — releases escrow.
     * Calls: confirmPayment(bytes32)
     */
    public function confirmPayment(string $tradeHash): string
    {
        $signerKey = config('blockchain.operator_private_key');
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        $data = $this->encodeFunctionCall($this->tradeEscrowAbi, 'confirmPayment', [$bytes32Hash]);

        return $this->sendTransaction($this->tradeEscrowAddress, $data, $signerKey);
    }

    /**
     * Cancel a trade and refund the locked escrow.
     * Calls: cancelTrade(bytes32)
     */
    public function cancelTrade(string $tradeHash): string
    {
        $signerKey = config('blockchain.operator_private_key');
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        $data = $this->encodeFunctionCall($this->tradeEscrowAbi, 'cancelTrade', [$bytes32Hash]);

        return $this->sendTransaction($this->tradeEscrowAddress, $data, $signerKey);
    }

    /**
     * Open a dispute on a trade.
     * Calls: openDispute(bytes32, address)
     */
    public function openDispute(string $tradeHash, string $openedBy): string
    {
        $signerKey = config('blockchain.operator_private_key');
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        $data = $this->encodeFunctionCall($this->tradeEscrowAbi, 'openDispute', [$bytes32Hash, $openedBy]);

        return $this->sendTransaction($this->tradeEscrowAddress, $data, $signerKey);
    }

    /**
     * Resolve a dispute, sending funds to the winner.
     * Calls: resolveDispute(bytes32, address)
     * Uses the admin key (not the operator key).
     */
    public function resolveDispute(string $tradeHash, string $winner): string
    {
        $signerKey = config('blockchain.admin_private_key');
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        $data = $this->encodeFunctionCall($this->tradeEscrowAbi, 'resolveDispute', [$bytes32Hash, $winner]);

        return $this->sendTransaction($this->tradeEscrowAddress, $data, $signerKey);
    }

    /**
     * Mint a Soulbound Trade NFT for a completed trade.
     * Calls: mintTradeNFT(bytes32, string)
     */
    public function mintTradeNFT(string $tradeHash, string $meetingLocation): string
    {
        $signerKey = config('blockchain.operator_private_key');
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        $data = $this->encodeFunctionCall($this->tradeEscrowAbi, 'mintTradeNFT', [$bytes32Hash, $meetingLocation]);

        return $this->sendTransaction($this->tradeEscrowAddress, $data, $signerKey);
    }

    /**
     * Burn a Soulbound Trade NFT.
     * Calls: burnTradeNFT(bytes32)
     */
    public function burnTradeNFT(string $tradeHash): string
    {
        $signerKey = config('blockchain.operator_private_key');
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        $data = $this->encodeFunctionCall($this->tradeEscrowAbi, 'burnTradeNFT', [$bytes32Hash]);

        return $this->sendTransaction($this->tradeEscrowAddress, $data, $signerKey);
    }

    // ─── Sell Flow — pure encoders (frontend broadcasts from user wallet) ──
    // No operator broadcasters. Spec: backend has zero authority over sell escrow.

    public function openSellTradeCalldata(string $tradeHash, string $merchant, string $amountWei, int $expiresAt, bool $requireStake, bool $isCashTrade, string $meetingLocation = ''): string
    {
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        return $this->encodeFunctionCall(
            $this->tradeEscrowAbi,
            'openSellTrade',
            [$bytes32Hash, $merchant, $amountWei, $expiresAt, $requireStake, $isCashTrade, $meetingLocation]
        );
    }

    public function joinSellTradeCalldata(string $tradeHash): string
    {
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        return $this->encodeFunctionCall($this->tradeEscrowAbi, 'joinSellTrade', [$bytes32Hash]);
    }

    public function markSellPaymentSentCalldata(string $tradeHash): string
    {
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        return $this->encodeFunctionCall($this->tradeEscrowAbi, 'markSellPaymentSent', [$bytes32Hash]);
    }

    public function releaseSellEscrowCalldata(string $tradeHash): string
    {
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        return $this->encodeFunctionCall($this->tradeEscrowAbi, 'releaseSellEscrow', [$bytes32Hash]);
    }

    public function openSellDisputeCalldata(string $tradeHash): string
    {
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        return $this->encodeFunctionCall($this->tradeEscrowAbi, 'openSellDispute', [$bytes32Hash]);
    }

    public function cancelSellTradePendingCalldata(string $tradeHash): string
    {
        $bytes32Hash = '0x' . $this->tradeHashToBytes32($tradeHash);
        return $this->encodeFunctionCall($this->tradeEscrowAbi, 'cancelSellTradePending', [$bytes32Hash]);
    }

    // ─── Sell Flow — receipt log parsers ─────────────────────────────────

    public function parseSellTradeOpenedLog(array $receipt, string $expectedTradeHash): ?array
    {
        return $this->matchEscrowEvent($receipt, 'SellTradeOpened(bytes32,address,address,uint256)', $expectedTradeHash);
    }

    public function parseSellTradeJoinedLog(array $receipt, string $expectedTradeHash): ?array
    {
        return $this->matchEscrowEvent($receipt, 'SellTradeJoined(bytes32,address)', $expectedTradeHash);
    }

    public function parseSellPaymentMarkedLog(array $receipt, string $expectedTradeHash): ?array
    {
        return $this->matchEscrowEvent($receipt, 'SellPaymentMarked(bytes32)', $expectedTradeHash);
    }

    public function parseSellEscrowReleasedLog(array $receipt, string $expectedTradeHash): ?array
    {
        return $this->matchEscrowEvent($receipt, 'SellEscrowReleased(bytes32,uint256)', $expectedTradeHash);
    }

    public function parseDisputeOpenedLog(array $receipt, string $expectedTradeHash): ?array
    {
        return $this->matchEscrowEvent($receipt, 'DisputeOpened(bytes32,address)', $expectedTradeHash);
    }

    public function parseTradeCancelledLog(array $receipt, string $expectedTradeHash): ?array
    {
        return $this->matchEscrowEvent($receipt, 'TradeCancelled(bytes32)', $expectedTradeHash);
    }

    /**
     * Find an event log emitted by the trade escrow contract whose first indexed
     * topic matches the expected tradeHash. Returns ['topics' => [...], 'data' => '0x...']
     * or null when no matching log present.
     */
    private function matchEscrowEvent(array $receipt, string $signature, string $expectedTradeHash): ?array
    {
        $topic0 = '0x' . Keccak::hash($signature, 256);
        $expectedTopic1 = '0x' . str_pad(ltrim($this->tradeHashToBytes32($expectedTradeHash), '0'), 64, '0', STR_PAD_LEFT);
        $escrowAddr = strtolower($this->tradeEscrowAddress);

        foreach (($receipt['logs'] ?? []) as $log) {
            if (strtolower($log['address'] ?? '') !== $escrowAddr) continue;
            $topics = $log['topics'] ?? [];
            if (($topics[0] ?? '') !== $topic0) continue;
            if (! isset($topics[1])) continue;
            if (strtolower($topics[1]) !== strtolower($expectedTopic1)) continue;
            return ['topics' => $topics, 'data' => $log['data'] ?? '0x'];
        }
        return null;
    }

    /**
     * Get block timestamp by block number (hex).
     */
    public function getBlockTimestamp(string $blockNumberHex): ?int
    {
        $block = $this->rpcCall('eth_getBlockByNumber', [$blockNumberHex, false]);
        if (! is_array($block) || ! isset($block['timestamp'])) return null;
        return (int) hexdec($block['timestamp']);
    }

    /**
     * Poll for a transaction receipt until mined or attempts exhausted.
     *
     * @throws \RuntimeException if tx reverts or exceeds max attempts
     */
    public function waitForReceipt(string $txHash, int $maxAttempts = 30, int $sleepSeconds = 3): array
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $receipt = $this->getTransactionReceipt($txHash);

            if ($receipt !== null) {
                if (($receipt['status'] ?? '') === '0x0') {
                    throw new \RuntimeException("Transaction reverted: {$txHash}");
                }

                return $receipt;
            }

            sleep($sleepSeconds);
        }

        throw new \RuntimeException("Transaction not mined after {$maxAttempts} attempts: {$txHash}");
    }

    /**
     * Parse the minted NFT token ID from a mint transaction receipt.
     * Looks for a TradeNFTMinted event log from the SoulboundTradeNFT contract.
     * Returns the decimal token ID string, or null if not found.
     */
    public function parseNftTokenIdFromReceipt(array $receipt): ?string
    {
        $eventSignature = 'TradeNFTMinted(uint256,bytes32,address,address,uint256)';
        $topic0 = '0x' . \kornrunner\Keccak::hash($eventSignature, 256);

        $logs = $receipt['logs'] ?? [];
        foreach ($logs as $log) {
            $logAddress = strtolower($log['address'] ?? '');
            $contractAddress = strtolower($this->soulboundNftAddress);

            if ($logAddress !== $contractAddress) {
                continue;
            }

            $topics = $log['topics'] ?? [];
            if (($topics[0] ?? '') === $topic0 && isset($topics[1])) {
                return $this->hexToDecimal($topics[1]);
            }
        }

        return null;
    }

    // ─── Internal: Contract Call Helpers ─────────────────────────

    /**
     * Call a read-only function on the TradeEscrowContract.
     */
    private function callEscrowContract(string $method, array $params): mixed
    {
        return $this->callContract($this->tradeEscrowAddress, $this->tradeEscrowAbi, $method, $params);
    }

    /**
     * Call a read-only function on the SoulboundTradeNFT contract.
     */
    private function callNftContract(string $method, array $params): mixed
    {
        return $this->callContract($this->soulboundNftAddress, $this->soulboundNftAbi, $method, $params);
    }

    /**
     * Execute an eth_call against a contract.
     */
    private function callContract(string $contractAddress, array $abi, string $method, array $params): mixed
    {
        $data = $this->encodeFunctionCall($abi, $method, $params);

        $result = $this->rpcCall('eth_call', [
            [
                'to' => $contractAddress,
                'data' => $data,
            ],
            'latest',
        ]);

        return $result;
    }

    /**
     * Encode a function call: 4-byte selector + ABI-encoded parameters.
     */
    private function encodeFunctionCall(array $abi, string $functionName, array $params): string
    {
        $functionAbi = $this->findFunctionAbi($abi, $functionName);
        if (! $functionAbi) {
            throw new \RuntimeException("Function '{$functionName}' not found in ABI");
        }

        // Build canonical function signature
        $inputTypes = array_map(fn ($input) => $input['type'], $functionAbi['inputs']);
        $signature = $functionName . '(' . implode(',', $inputTypes) . ')';

        // Keccak-256 hash, take first 4 bytes (8 hex chars)
        $hash = Keccak::hash($signature, 256);
        $selector = substr($hash, 0, 8);

        // ABI-encode parameters
        $encodedParams = $this->abiEncode($inputTypes, $params);

        return '0x' . $selector . $encodedParams;
    }

    /**
     * Find a function definition in the ABI by name.
     */
    private function findFunctionAbi(array $abi, string $functionName): ?array
    {
        foreach ($abi as $entry) {
            if (($entry['type'] ?? '') === 'function' && ($entry['name'] ?? '') === $functionName) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * ABI-encode parameters according to Solidity types.
     * Supports static types: address, uint256, uint8, uint64, int256, bool, bytes32, bytes4
     * Supports dynamic types: string, bytes (two-pass head/tail encoding per ABI spec)
     */
    private function abiEncode(array $types, array $values): string
    {
        $staticSlots = count($types);
        $heads = [];
        $tails = [];
        $tailOffset = $staticSlots * 32; // byte offset where tail section starts

        foreach ($types as $i => $type) {
            $value = $values[$i] ?? null;

            if ($type === 'string' || $type === 'bytes') {
                // Dynamic: head = offset into tail section
                $heads[] = $this->encodeUint((string) $tailOffset);
                $tail = $this->encodeDynamicString((string) ($value ?? ''));
                $tails[] = $tail;
                $tailOffset += intdiv(strlen($tail), 2); // hex chars / 2 = bytes
            } else {
                $heads[] = match ($type) {
                    'address'          => $this->encodeAddress($value),
                    'uint256', 'uint8',
                    'uint64', 'int256' => $this->encodeUint((string) $value),
                    'bool'             => $this->encodeBool((bool) $value),
                    'bytes32'          => $this->encodeBytes32($value),
                    'bytes4'           => str_pad(str_replace('0x', '', $value), 64, '0', STR_PAD_RIGHT),
                    default            => throw new \RuntimeException("Unsupported ABI type: {$type}"),
                };
            }
        }

        return implode('', $heads) . implode('', $tails);
    }

    /**
     * Encode a dynamic string/bytes value: 32-byte length prefix + data padded to 32-byte boundary.
     */
    private function encodeDynamicString(string $value): string
    {
        $bytes = bin2hex($value);
        $length = strlen($value);
        $padTo = (int) (ceil($length / 32) * 32); // pad to 32-byte boundary

        return $this->encodeUint((string) $length)
            . str_pad($bytes, $padTo * 2, '0', STR_PAD_RIGHT);
    }

    /**
     * Encode an address to 32-byte padded hex.
     */
    private function encodeAddress(string $address): string
    {
        $address = str_replace('0x', '', strtolower($address));

        return str_pad($address, 64, '0', STR_PAD_LEFT);
    }

    /**
     * Encode a uint/int to 32-byte padded hex.
     */
    private function encodeUint(string|int $value): string
    {
        if (is_string($value) && str_starts_with($value, '0x')) {
            $hex = substr($value, 2);
        } else {
            // Handle large numbers — bcmath for precision
            $hex = $this->decimalToHex((string) $value);
        }

        return str_pad($hex, 64, '0', STR_PAD_LEFT);
    }

    /**
     * Encode a boolean to 32-byte padded hex.
     */
    private function encodeBool(bool $value): string
    {
        return str_pad($value ? '1' : '0', 64, '0', STR_PAD_LEFT);
    }

    /**
     * Encode a bytes32 value to 32-byte padded hex.
     */
    private function encodeBytes32(string $value): string
    {
        $value = str_replace('0x', '', $value);

        return str_pad($value, 64, '0', STR_PAD_RIGHT);
    }

    // ─── Internal: JSON-RPC ──────────────────────────────────────

    /**
     * Execute a JSON-RPC call to the blockchain node.
     */
    private function rpcCall(string $method, array $params): mixed
    {
        if (empty($this->rpcUrl)) {
            throw new \RuntimeException('Blockchain RPC URL is not configured');
        }

        $response = Http::timeout(30)->post($this->rpcUrl, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Blockchain RPC request failed with HTTP ' . $response->status());
        }

        $result = $response->json();

        if (isset($result['error'])) {
            throw new \RuntimeException(
                'Blockchain RPC error: ' . ($result['error']['message'] ?? 'Unknown error')
            );
        }

        return $result['result'] ?? null;
    }

    // ─── Internal: Decoding Helpers ──────────────────────────────

    /**
     * Decode a 32-byte hex value as an Ethereum address (last 20 bytes).
     */
    private function decodeAddress(string $hex): string
    {
        $hex = str_replace('0x', '', $hex);
        // Address is the last 40 hex chars of a 64-char slot
        $address = substr($hex, -40);

        return '0x' . $address;
    }

    /**
     * Convert a hex string (0x prefixed) to a decimal string.
     * Uses bcmath for precision with large uint256 values.
     */
    public function hexToDecimal(string $hex): string
    {
        $hex = str_replace('0x', '', $hex);
        $hex = ltrim($hex, '0') ?: '0';

        $decimal = '0';
        $len = strlen($hex);

        for ($i = 0; $i < $len; $i++) {
            $decimal = bcmul($decimal, '16');
            $decimal = bcadd($decimal, (string) hexdec($hex[$i]));
        }

        return $decimal;
    }

    /**
     * Convert a decimal string to hex (no 0x prefix).
     * Uses bcmath for precision with large uint256 values.
     */
    private function decimalToHex(string $decimal): string
    {
        if ($decimal === '0') {
            return '0';
        }

        $hex = '';
        while (bccomp($decimal, '0') > 0) {
            $remainder = bcmod($decimal, '16');
            $hex = dechex((int) $remainder) . $hex;
            $decimal = bcdiv($decimal, '16', 0);
        }

        return $hex ?: '0';
    }

    /**
     * Build, sign, and broadcast a raw EIP-155 transaction.
     * Returns the transaction hash.
     *
     * @throws \RuntimeException on RPC or signing failure
     */
    private function sendTransaction(string $to, string $data, string $privateKey): string
    {
        // Strip 0x prefix from private key before using
        if (str_starts_with($privateKey, '0x')) {
            $privateKey = substr($privateKey, 2);
        }

        $signerAddress = $this->getSignerAddress($privateKey);

        // Get nonce for the signer
        $nonceHex = $this->rpcCall('eth_getTransactionCount', [$signerAddress, 'pending']);

        // Get current gas price
        $gasPriceHex = $this->rpcCall('eth_gasPrice', []);

        // Estimate gas for this specific call
        $gasEstHex = $this->rpcCall('eth_estimateGas', [
            ['from' => $signerAddress, 'to' => $to, 'data' => $data],
        ]);

        // Add 20% gas buffer
        $gasLimit = intdiv(hexdec(ltrim($gasEstHex, '0x')) * 12, 10);
        $gasLimitHex = '0x' . dechex($gasLimit);

        // Strip 0x prefix — RLPencode internally prepends '0x', so inputs must be bare hex
        $strip = fn (string $h): string => str_starts_with($h, '0x') ? substr($h, 2) : $h;

        // Build and sign the transaction (use fixed subclass — see App\Ethereum\Transaction)
        $tx = new \App\Ethereum\Transaction(
            $strip($nonceHex),
            $strip($gasPriceHex),
            $strip($gasLimitHex),
            $strip($to),
            '0',
            $strip($data)
        );

        $raw = $tx->getRaw($privateKey, $this->chainId);

        // Broadcast signed transaction
        $txHash = $this->rpcCall('eth_sendRawTransaction', ['0x' . $raw]);

        return (string) $txHash;
    }

    /**
     * Derive the Ethereum address from a private key hex string (no 0x prefix).
     * Returns checksummed address with 0x prefix.
     */
    private function getSignerAddress(string $privateKey): string
    {
        // Strip 0x prefix if present
        if (str_starts_with($privateKey, '0x')) {
            $privateKey = substr($privateKey, 2);
        }

        $address = new \kornrunner\Ethereum\Address($privateKey);

        return '0x' . strtolower($address->get());
    }

    /**
     * Convert a trade hash string to a 64-char hex bytes32 representation.
     * Strips 0x prefix and left-pads with zeros to 64 hex characters.
     * Returns the raw 64-char hex string (no 0x prefix).
     */
    private function tradeHashToBytes32(string $tradeHash): string
    {
        $hex = str_replace('0x', '', $tradeHash);

        return str_pad($hex, 64, '0', STR_PAD_LEFT);
    }

    /**
     * Convert a USDC raw amount (6 decimals) to a human-readable float string.
     * E.g., "1000000" -> "1.000000"
     */
    public function usdcToHuman(string $rawAmount): string
    {
        return bcdiv($rawAmount, '1000000', 6);
    }

    /**
     * Convert a human-readable USDC amount to raw (6 decimals).
     * E.g., "1.5" -> "1500000"
     */
    public function humanToUsdc(string $amount): string
    {
        return bcmul($amount, '1000000', 0);
    }
}
