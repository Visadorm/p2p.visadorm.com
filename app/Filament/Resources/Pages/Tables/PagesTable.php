<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Tables;

use App\Enums\PageStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('page.admin.col_title'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50),
                TextColumn::make('slug')
                    ->label(__('page.admin.col_slug'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('page.admin.col_status'))
                    ->badge()
                    ->sortable(),
                IconColumn::make('show_in_header')
                    ->label(__('page.admin.col_header'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('show_in_footer')
                    ->label(__('page.admin.col_footer'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->label(__('page.admin.col_sort_order'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->label(__('page.admin.col_published_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->options(PageStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
