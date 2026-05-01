<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\KeyVault\AwsKmsKeyVault;
use App\Services\KeyVault\EnvKeyVault;
use App\Services\KeyVault\HashicorpVaultKeyVault;
use App\Services\KeyVault\KeyVault;
use Tests\TestCase;

class KeyVaultTest extends TestCase
{
    public function test_b11_default_driver_is_env_key_vault(): void
    {
        config()->set('blockchain.key_vault_driver', 'env');
        $this->app->forgetInstance(KeyVault::class);

        $vault = $this->app->make(KeyVault::class);
        $this->assertInstanceOf(EnvKeyVault::class, $vault);
    }

    public function test_b11_aws_kms_driver_resolves(): void
    {
        config()->set('blockchain.key_vault_driver', 'aws_kms');
        $this->app->forgetInstance(KeyVault::class);

        $vault = $this->app->make(KeyVault::class);
        $this->assertInstanceOf(AwsKmsKeyVault::class, $vault);
    }

    public function test_b11_vault_driver_resolves(): void
    {
        config()->set('blockchain.key_vault_driver', 'vault');
        $this->app->forgetInstance(KeyVault::class);

        $vault = $this->app->make(KeyVault::class);
        $this->assertInstanceOf(HashicorpVaultKeyVault::class, $vault);
    }

    public function test_b11_env_vault_returns_configured_keys(): void
    {
        config()->set('blockchain.operator_private_key', '0xaaaa');
        config()->set('blockchain.admin_private_key', '0xbbbb');

        $vault = new EnvKeyVault;
        $this->assertSame('0xaaaa', $vault->operatorKey());
        $this->assertSame('0xbbbb', $vault->adminKey());
    }

    public function test_b11_env_vault_throws_when_missing(): void
    {
        config()->set('blockchain.operator_private_key', '');

        $this->expectException(\RuntimeException::class);
        (new EnvKeyVault)->operatorKey();
    }

    public function test_b11_aws_kms_stub_throws_until_implemented(): void
    {
        $this->expectException(\RuntimeException::class);
        (new AwsKmsKeyVault)->operatorKey();
    }
}
