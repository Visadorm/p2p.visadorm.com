<?php

namespace App\Ethereum;

use kornrunner\Keccak;

/**
 * Extends kornrunner\Ethereum\Transaction to fix EIP-155 chainId encoding.
 *
 * The parent library passes the chainId as a PHP int to RLPencode(), which
 * then calls hexup(intToString) — treating decimal digits as hex characters.
 * For chainId 84532 this encodes 0x084532 (=542002) instead of 0x014a54
 * (=84532), producing an invalid signature on any chain with id > 9.
 *
 * Fix: override hash() to convert chainId via dechex() before RLP encoding.
 */
class Transaction extends \kornrunner\Ethereum\Transaction
{
    protected function hash(int $chainId): string
    {
        $input = $this->getInput();

        if ($chainId > 0) {
            $input['v'] = dechex($chainId); // ← correct hex string, not decimal int
            $input['r'] = '';
            $input['s'] = '';
        } else {
            unset($input['v'], $input['r'], $input['s']);
        }

        $encoded = $this->RLPencode($input);

        return Keccak::hash(hex2bin($encoded), 256);
    }
}
