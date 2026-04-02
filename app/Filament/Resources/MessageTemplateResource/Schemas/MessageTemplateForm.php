<?php

namespace App\Filament\Resources\MessageTemplateResource\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MessageTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('p2p.msg_tpl.section_type'))
                    ->schema([
                        TextInput::make('label')
                            ->label(__('p2p.msg_tpl.label'))
                            ->disabled(),

                        TextInput::make('type')
                            ->label(__('p2p.msg_tpl.type'))
                            ->disabled(),

                        Placeholder::make('variables_guide')
                            ->label(__('p2p.msg_tpl.variables_guide'))
                            ->content(fn ($record): string => $record?->variables_guide ?? ''),
                    ])
                    ->columns(2),

                Section::make(__('p2p.msg_tpl.section_email'))
                    ->schema([
                        TextInput::make('email_subject')
                            ->label(__('p2p.msg_tpl.email_subject'))
                            ->maxLength(255)
                            ->helperText(__('p2p.msg_tpl.helper_variable')),

                        Textarea::make('email_body')
                            ->label(__('p2p.msg_tpl.email_body'))
                            ->rows(5)
                            ->maxLength(2000)
                            ->helperText(__('p2p.msg_tpl.helper_variable')),
                    ]),

                Section::make(__('p2p.msg_tpl.section_sms'))
                    ->schema([
                        Textarea::make('sms_text')
                            ->label(__('p2p.msg_tpl.sms_text'))
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText(__('p2p.msg_tpl.helper_sms_length')),
                    ]),
            ]);
    }
}
