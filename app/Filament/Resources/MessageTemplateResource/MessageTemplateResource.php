<?php

namespace App\Filament\Resources\MessageTemplateResource;

use App\Filament\Resources\MessageTemplateResource\Pages\EditMessageTemplate;
use App\Filament\Resources\MessageTemplateResource\Pages\ListMessageTemplates;
use App\Filament\Resources\MessageTemplateResource\Schemas\MessageTemplateForm;
use App\Filament\Resources\MessageTemplateResource\Tables\MessageTemplatesTable;
use App\Filament\Clusters\Settings;
use App\Models\MessageTemplate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MessageTemplateResource extends Resource
{
    protected static ?string $model = MessageTemplate::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static ?int $navigationSort = 7;

    protected static ?string $cluster = Settings::class;

    protected static ?string $slug = 'settings/message-templates';

    public static function getModelLabel(): string
    {
        return __('p2p.message_template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('p2p.message_templates');
    }

    public static function form(Schema $schema): Schema
    {
        return MessageTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MessageTemplatesTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessageTemplates::route('/'),
            'edit' => EditMessageTemplate::route('/{record}/edit'),
        ];
    }
}
