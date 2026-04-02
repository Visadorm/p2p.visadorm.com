<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_null_role_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create(['role' => null]);
        $this->assertFalse($user->canAccessPanel(Filament::getDefaultPanel()));
    }

    public function test_super_admin_can_access_admin_panel(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        $this->assertTrue($user->canAccessPanel(Filament::getDefaultPanel()));
    }

    public function test_kyc_reviewer_can_access_admin_panel(): void
    {
        $user = User::factory()->create(['role' => 'kyc_reviewer']);
        $this->assertTrue($user->canAccessPanel(Filament::getDefaultPanel()));
    }

    public function test_dispute_manager_can_access_admin_panel(): void
    {
        $user = User::factory()->create(['role' => 'dispute_manager']);
        $this->assertTrue($user->canAccessPanel(Filament::getDefaultPanel()));
    }

    public function test_new_user_has_null_role_by_default(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->role);
    }

    public function test_kyc_reviewer_can_view_kyc_documents(): void
    {
        $user = User::factory()->create(['role' => 'kyc_reviewer']);
        $this->actingAs($user);
        $this->assertTrue(\App\Filament\Resources\KycDocumentResource\KycDocumentResource::canViewAny());
    }

    public function test_kyc_reviewer_cannot_view_merchant_resource(): void
    {
        $user = User::factory()->create(['role' => 'kyc_reviewer']);
        $this->actingAs($user);
        $this->assertFalse(\App\Filament\Resources\MerchantResource\MerchantResource::canViewAny());
    }

    public function test_dispute_manager_can_view_disputes(): void
    {
        $user = User::factory()->create(['role' => 'dispute_manager']);
        $this->actingAs($user);
        $this->assertTrue(\App\Filament\Resources\DisputeResource\DisputeResource::canViewAny());
    }

    public function test_dispute_manager_cannot_view_kyc_documents(): void
    {
        $user = User::factory()->create(['role' => 'dispute_manager']);
        $this->actingAs($user);
        $this->assertFalse(\App\Filament\Resources\KycDocumentResource\KycDocumentResource::canViewAny());
    }
}
