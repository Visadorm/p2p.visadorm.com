<?php

declare(strict_types=1);

namespace App\Services\KeyVault;

/**
 * B11: KeyVault abstracts where private keys live so we can swap from
 * env-stored hex to a managed service (AWS KMS, HashiCorp Vault, GCP KMS)
 * without touching call sites in BlockchainService.
 *
 * The two relevant keys are:
 *  - operator: signs routine on-chain operator-mode txs
 *  - admin:    signs dispute resolution txs (mediator multisig role)
 *
 * Implementations MUST return a hex string (with or without 0x prefix) for
 * the v1 transition. A future HSM-native implementation may add a
 * `signTransaction(...)` method instead, but the env path keeps working
 * during migration.
 */
interface KeyVault
{
    /**
     * @return string hex-encoded private key (with optional 0x prefix)
     */
    public function operatorKey(): string;

    /**
     * @return string hex-encoded private key (with optional 0x prefix)
     */
    public function adminKey(): string;
}
