<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KennelResource\Pages;
use App\Models\Kennel;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class KennelResource extends Resource
{
    protected static ?string $model = Kennel::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('abbreviation')
                    ->label('Abbreviation')
                    ->required()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true),
                TextInput::make('full_name')
                    ->label('Full name')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('abbreviation')
                    ->label('Abbreviation')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Full name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('events_count')
                    ->label('Events')
                    ->counts('events')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('abbreviation');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKennels::route('/'),
            'create' => Pages\CreateKennel::route('/create'),
            'edit' => Pages\EditKennel::route('/{record}/edit'),
        ];
    }
}
