<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageResource\Pages;
use App\Models\Event;
use App\Models\Message;
use App\Models\MessageStatus;
use App\Models\OrderStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('event_id')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('subject')->required(),

                Forms\Components\MarkdownEditor::make('body')
                    ->columnSpanFull()
                    ->required()
                    ->helperText('Markdown email body. Recipients will also see any profile/event info fields you select below.'),

                Forms\Components\Select::make('recipient_filter')
                    ->label('Recipient Statuses')
                    ->multiple()
                    ->options(OrderStatus::class)
                    ->helperText('Orders with these statuses will receive the message'),

                Forms\Components\Section::make('Personalization')
                    ->description('Select fields to include as a reminder section in the email')
                    ->schema([
                        Forms\Components\CheckboxList::make('include_profile_fields')
                            ->label('Profile Fields')
                            ->options([
                                'name' => 'Name',
                                'email' => 'Email',
                                'kennel' => 'Kennel',
                                'nerd_name' => 'Nerd Name',
                                'shirt_size' => 'Shirt Size',
                                'short_bus' => 'Short Bus',
                                'phone' => 'Phone',
                                'order_status' => 'Registration Status',
                            ]),
                        Forms\Components\CheckboxList::make('include_event_fields')
                            ->label('Event Info Fields')
                            ->options(function (Forms\Get $get) {
                                $eventId = $get('event_id');
                                if (! $eventId) {
                                    return [];
                                }
                                $event = Event::find($eventId);
                                if (! $event || ! $event->properties) {
                                    return [];
                                }
                                $fields = data_get($event->properties, 'fields', []);

                                return collect($fields)->pluck('label', 'name')->toArray();
                            }),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (MessageStatus $state) => match ($state) {
                        MessageStatus::Draft => 'gray',
                        MessageStatus::Sending => 'warning',
                        MessageStatus::Sent => 'success',
                        MessageStatus::Failed => 'danger',
                    }),
                Tables\Columns\TextColumn::make('recipients_count')
                    ->label('Recipients')
                    ->counts('recipients'),
                Tables\Columns\TextColumn::make('sent_count')
                    ->label('Sent'),
                Tables\Columns\TextColumn::make('failed_count')
                    ->label('Failed'),
                Tables\Columns\TextColumn::make('last_sent_at')
                    ->dateTime('Y-m-d h:i A', 'America/New_York')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event_id')
                    ->relationship('event', 'name')
                    ->label('Event'),
                SelectFilter::make('status')
                    ->options(MessageStatus::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(100)
            ->paginated([50, 100, 'all']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessages::route('/'),
            'create' => Pages\CreateMessage::route('/create'),
            'edit' => Pages\EditMessage::route('/{record}/edit'),
        ];
    }
}
