<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                Select::make('created_by')
                    ->label('Event Creator')
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->required(),
                TextInput::make('name')->required(),
                TextInput::make('location')->required(),
                TextInput::make('lat')->required(),
                TextInput::make('lon')->required(),
                TextInput::make('kennel')->required(),
                TextInput::make('base_price')
                    ->prefix('$')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state / 100)
                    ->dehydrateStateUsing(fn ($state) => (int) round($state * 100)),
                FileUpload::make('event_photo_path')
                    ->label('Event Photo')
                    ->image()
                    ->directory('event-photos')
                    ->visibility('public'),
                MarkdownEditor::make('description')
                    ->columnSpanFull()
                    ->required(),
                TextInput::make('event_tag')->required(),
                Toggle::make('private')
                    ->label('Private (invite only)')
                    ->default(true),
                DateTimePicker::make('starts_at')
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('Y-m-d H:i')
                    ->timezone('America/New_York')
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('Y-m-d H:i')
                    ->timezone('America/New_York')
                    ->required(),
                Section::make('Event Properties')
                    ->description('Define custom fields and add-ons for this event')
                    ->schema([
                        Repeater::make('properties.fields')
                            ->label('Custom Fields')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->helperText('Internal key (e.g. cabin_number)'),
                                TextInput::make('label')
                                    ->required()
                                    ->helperText('Display label (e.g. Cabin #)'),
                                Select::make('type')
                                    ->options([
                                        'text' => 'Text',
                                        'number' => 'Number',
                                        'textarea' => 'Textarea',
                                    ])
                                    ->default('text')
                                    ->required(),
                                TextInput::make('rules')
                                    ->helperText('Laravel validation rules (e.g. required|string|max:512)'),
                                TextInput::make('placeholder'),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->defaultItems(0),
                        Repeater::make('properties.addons')
                            ->label('Add-ons')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->helperText('Internal key'),
                                TextInput::make('label')
                                    ->required()
                                    ->helperText('Display label (e.g. Add EH3 32nd Anal)'),
                                TextInput::make('price')
                                    ->prefix('$')
                                    ->numeric()
                                    ->required()
                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                    ->dehydrateStateUsing(fn ($state) => (int) round(($state ?? 0) * 100))
                                    ->helperText('Additional cost'),
                                TextInput::make('tag_suffix')
                                    ->helperText('Appended to event tag (e.g. _PLUS_EH3)'),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->defaultItems(0),
                    ])
                    ->columnSpanFull()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('past')
                    ->label('Past Events')
                    ->default(false)
                    ->queries(
                        true: fn ($query) => $query->where('ends_at', '<', now()),
                        false: fn ($query) => $query->where('ends_at', '>=', now()),
                        blank: fn ($query) => $query,
                    ),
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
