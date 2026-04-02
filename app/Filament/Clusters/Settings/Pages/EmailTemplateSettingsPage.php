<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings;
use App\Settings\EmailTemplateSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmailTemplateSettingsPage extends SettingsPage
{
    protected static ?string $cluster = Settings::class;

    protected static string $settings = EmailTemplateSettings::class;

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('settings.email_template');
    }

    public function getTitle(): string
    {
        return __('settings.email_template');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (is_array($data['logo_path'] ?? null)) {
            $data['logo_path'] = collect($data['logo_path'])->first();
        }
        if (is_array($data['header_image_path'] ?? null)) {
            $data['header_image_path'] = collect($data['header_image_path'])->first();
        }

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('settings.email_template_branding.title'))
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label(__('settings.email_template_branding.logo'))
                            ->image()
                            ->disk('public')
                            ->directory('email-branding')
                            ->visibility('public'),
                        FileUpload::make('header_image_path')
                            ->label(__('settings.email_template_branding.header_image'))
                            ->image()
                            ->disk('public')
                            ->directory('email-branding')
                            ->visibility('public'),
                    ]),

                Section::make(__('settings.email_template_colors.title'))
                    ->columns(2)
                    ->schema([
                        ColorPicker::make('primary_color')
                            ->label(__('settings.email_template_colors.primary_color')),
                        ColorPicker::make('secondary_color')
                            ->label(__('settings.email_template_colors.secondary_color')),
                    ]),

                Section::make(__('settings.email_template_footer.title'))
                    ->schema([
                        RichEditor::make('footer_text')
                            ->label(__('settings.email_template_footer.footer_text'))
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList',
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('settings.email_template_preview.button'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(route('admin.email-preview'), shouldOpenInNewTab: true),
        ];
    }
}
