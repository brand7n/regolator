<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Mail\RegoInvite;
use App\Models\Order;
use App\Models\OrderStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')->relationship('user', 'name'),
                Select::make('event_id')->relationship('event', 'name'),
                TextInput::make('order_id')->readOnly(),
                Select::make('status')
                    ->options(OrderStatus::class)
                    ->required(),
                DateTimePicker::make('verified_at')
                    ->seconds(false)
                    ->native(false)
                    ->timezone('America/New_York'),
                Textarea::make('comment'),
                KeyValue::make('event_info')->label('Event Info'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                // timestamps
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('send_invites')
                        ->label('Send Invites')
                        ->icon('heroicon-o-envelope')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = 0;
                            /** @var Order $order */
                            foreach ($records as $order) {
                                $order->status = OrderStatus::Invited;
                                $order->save();

                                $user = $order->user;
                                $event = $order->event;
                                $quickLogin = $user->getQuickLogin($event->ends_at);
                                $eventUrl = route('events.show', $event);

                                $mail = new RegoInvite($user, $event, url('/quicklogin/'.$quickLogin.'?action='.$eventUrl));
                                Mail::to($user)->later(now()->addSeconds($count * 5), $mail);
                                $count++;
                            }

                            Notification::make()
                                ->title("Invited {$count} user(s)")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
