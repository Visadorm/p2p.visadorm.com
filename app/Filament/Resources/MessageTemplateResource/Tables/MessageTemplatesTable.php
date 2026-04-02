<?php

namespace App\Filament\Resources\MessageTemplateResource\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessageTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label(__('p2p.msg_tpl.label'))
                    ->searchable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('email_subject')
                    ->label(__('p2p.msg_tpl.email_subject'))
                    ->limit(40),

                TextColumn::make('sms_text')
                    ->label(__('p2p.msg_tpl.sms_preview'))
                    ->limit(40),

                TextColumn::make('updated_at')
                    ->label(__('p2p.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->defaultSort('type', 'asc');
    }
}
