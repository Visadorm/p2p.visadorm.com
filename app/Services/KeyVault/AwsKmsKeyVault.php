<?php

declare(strict_types=1);

namespace App\Services\KeyVault;

use RuntimeException;

/**
 * B11: AWS KMS driver scaffold. NOT FUNCTIONAL until implemented.
 *
 * Real impl notes:
 *  - KMS does NOT expose private key material. Native HSM workflow signs
 *    raw transaction bytes via `kms:Sign`.
 *  - To preserve the current `BlockchainService::sendTransaction` shape
 *    (which expects a hex string), this class would need a "key escrow"
 *    where keys are wrapped in KMS but unwrapped server-side. Not ideal.
 *  - Recommended path: introduce `signTransaction(Transaction $tx)` method
 *    on KeyVault and refactor sendTransaction to delegate signing.
 *  - That refactor is its own ticket — this stub exists so the abstraction
 *    is in place and the migration path is clear.
 */
class AwsKmsKeyVault implements KeyVault
{
    public function operatorKey(): string
    {
        throw new RuntimeException(
            'AwsKmsKeyVault::operatorKey() not implemented. '.
            'Configure BLOCKCHAIN_KEY_VAULT_DRIVER=env, or implement KMS signing.'
        );
    }

    public function adminKey(): string
    {
        throw new RuntimeException(
            'AwsKmsKeyVault::adminKey() not implemented. '.
            'Configure BLOCKCHAIN_KEY_VAULT_DRIVER=env, or implement KMS signing.'
        );
    }
}
