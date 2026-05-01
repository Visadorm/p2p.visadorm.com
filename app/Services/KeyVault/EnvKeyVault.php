<?php

declare(strict_types=1);

namespace App\Services\KeyVault;

use RuntimeException;

/**
 * B11: default KeyVault driver. Reads from the existing `.env` configuration.
 * Equivalent to the legacy `config('blockchain.operator_private_key')` reads.
 */
class EnvKeyVault implements KeyVault
{
    public function operatorKey(): string
    {
        $key = (string) config('blockchain.operator_private_key', '');
        if ($key === '') {
            throw new RuntimeException('OPERATOR_PRIVATE_KEY env not configured.');
        }
        return $key;
    }

    public function adminKey(): string
    {
        $key = (string) config('blockchain.admin_private_key', '');
        if ($key === '') {
            throw new RuntimeException('ADMIN_PRIVATE_KEY env not configured.');
        }
        return $key;
    }
}
