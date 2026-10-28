<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;

class ListUsersByShirtSize extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shirt_size')
                    ->label('Shirt Size')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kennel')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nerd_name')
                    ->label('Nerd Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->formatStateUsing(fn ($state) => $state ? preg_replace('/^(\+\d{1})(\d{3})(\d{3})(\d{4})$/', '$1-$2-$3-$4', $state) : null),
            ])
            ->filters([
                // Add filters for different shirt sizes
                Tables\Filters\SelectFilter::make('shirt_size')
                    ->options([
                        'XS' => 'XS',
                        'SM' => 'SM',
                        'MD' => 'MD',
                        'LG' => 'LG',
                        'XL' => 'XL',
                        '2XL' => '2XL',
                        '3XL' => '3XL',
                    ])
                    ->label('Shirt Size'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultPaginationPageOption(100)
            ->paginated([50, 100, 'all']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
