<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('location')->required(),
                TextInput::make('lat')->required(),
                TextInput::make('lon')->required(),
                TextInput::make('base_price')
                    ->prefix('$')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state / 100)
                    ->dehydrateStateUsing(fn ($state) => (int) round($state * 100)),
                MarkdownEditor::make('description')
                    ->columnSpanFull()
                    ->required(),
                DateTimePicker::make('starts_at')
                    ->seconds(false)
                    ->native(false)        // enable Filament’s JS datepicker
                    ->displayFormat('Y-m-d H:i')
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->seconds(false)
                    ->native(false)        // enable Filament’s JS datepicker
                    ->displayFormat('Y-m-d H:i')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
