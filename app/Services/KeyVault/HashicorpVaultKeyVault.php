<?php

declare(strict_types=1);

namespace App\Services\KeyVault;

use RuntimeException;

/**
 * B11: HashiCorp Vault driver scaffold. NOT FUNCTIONAL until implemented.
 *
 * Real impl uses Vault's KV-v2 secret engine to read the private key, OR
 * Vault's transit engine to sign raw transactions without exposing the key.
 * Transit engine is preferred — same migration pattern as KMS.
 */
class HashicorpVaultKeyVault implements KeyVault
{
    public function operatorKey(): string
    {
        throw new RuntimeException(
            'HashicorpVaultKeyVault::operatorKey() not implemented. '.
            'Configure BLOCKCHAIN_KEY_VAULT_DRIVER=env, or implement Vault signing.'
        );
    }

    public function adminKey(): string
    {
        throw new RuntimeException(
            'HashicorpVaultKeyVault::adminKey() not implemented.'
        );
    }
}
