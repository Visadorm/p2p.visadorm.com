<?php

namespace App\Filament\Resources\MerchantRankResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MerchantRankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('merchant.rank_fields.section_requirements'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('merchant.rank_fields.name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->label(__('merchant.rank_fields.slug'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('min_trades')
                            ->label(__('merchant.rank_fields.min_trades'))
                            ->numeric()
                            ->required()
                            ->minValue(0),

                        TextInput::make('min_completion_rate')
                            ->label(__('merchant.rank_fields.min_completion_rate'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),

                        TextInput::make('min_volume')
                            ->label(__('merchant.rank_fields.min_volume'))
                            ->numeric()
                            ->minValue(0),

                        TextInput::make('sort_order')
                            ->label(__('merchant.rank_fields.sort_order'))
                            ->numeric()
                            ->required()
                            ->minValue(0),
                    ])
                    ->columns(2),
            ]);
    }
}
