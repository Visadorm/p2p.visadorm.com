<?php

namespace App\Filament\Resources\MessageTemplateResource\Pages;

use App\Filament\Resources\MessageTemplateResource\MessageTemplateResource;
use App\Models\MessageTemplate;
use Filament\Resources\Pages\EditRecord;

class EditMessageTemplate extends EditRecord
{
    protected static string $resource = MessageTemplateResource::class;

    protected function afterSave(): void
    {
        MessageTemplate::clearCache($this->record->type);
    }
}
