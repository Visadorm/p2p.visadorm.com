<?php

namespace App\Filament\Clusters\Profile\Pages;

use App\Filament\Clusters\Profile;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TwoFactorAuthPage extends Page
{
    protected static ?string $cluster = Profile::class;

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.two-factor-auth';

    public static function getNavigationLabel(): string
    {
        return __('settings.two_factor.title');
    }

    public function getTitle(): string
    {
        return __('settings.two_factor.title');
    }

    public function schema(Schema $schema): Schema
    {
        $provider = AppAuthentication::make()->recoverable();

        return $schema
            ->components([
                Section::make(__('settings.two_factor.section'))
                    ->description(__('settings.two_factor.description'))
                    ->schema($provider->getManagementSchemaComponents()),
            ]);
    }
}
