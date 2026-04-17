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
use Illuminate\Support\Facades\Log;
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
                            $skipped = 0;
                            $failed = 0;
                            /** @var Order $order */
                            foreach ($records as $order) {
                                if (in_array($order->status, [OrderStatus::PaymentVerified, OrderStatus::Blocked])) {
                                    $skipped++;

                                    continue;
                                }

                                $order->status = OrderStatus::Invited;
                                $order->save();

                                try {
                                    $user = $order->user;
                                    $event = $order->event;
                                    $quickLogin = $user->getQuickLogin($event->ends_at);
                                    $eventUrl = route('events.show', $event);

                                    Mail::to($user)->send(new RegoInvite($user, $event, url('/quicklogin/'.$quickLogin.'?action='.$eventUrl)));
                                    $count++;
                                } catch (\Throwable $e) {
                                    Log::error('failed to send invite', [
                                        'user_id' => $order->user_id,
                                        'order_id' => $order->id,
                                        'error' => $e->getMessage(),
                                    ]);
                                    $failed++;
                                }
                            }

                            $msg = "Invited {$count} user(s)";
                            if ($skipped) {
                                $msg .= ", skipped {$skipped} already paid";
                            }
                            if ($failed) {
                                $msg .= ", {$failed} failed to send";
                            }

                            Notification::make()
                                ->title($msg)
                                ->status($failed ? 'warning' : 'success')
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->defaultPaginationPageOption(100)
            ->paginated([50, 100, 'all']);
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
