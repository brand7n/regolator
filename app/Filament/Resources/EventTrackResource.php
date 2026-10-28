<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventTrackResource\Pages;
use App\Models\EventTrack;
use App\Models\Kennel;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class EventTrackResource extends Resource
{
    protected static ?string $model = EventTrack::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $slug = 'tracks';

    protected static ?string $navigationLabel = 'Tracks';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->url(fn (EventTrack $record): string => EventResource::getUrl('edit', ['record' => $record->event_id])),
                Tables\Columns\TextColumn::make('event.kennel.name')
                    ->label('Kennel')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Uploaded by')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('points')
                    ->label('Points')
                    ->getStateUsing(fn (EventTrack $record): int => count($record->points ?? []))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024).' KB')
                    ->alignEnd()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('kennel_id')
                    ->label('Kennel')
                    ->options(fn (): array => Kennel::orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('event', fn (Builder $event) => $event->where('kennel_id', $data['value']))
                        : $query),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('GPX')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (EventTrack $record): string => Storage::disk('public')->url($record->path))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventTracks::route('/'),
        ];
    }
}
