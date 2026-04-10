<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $title = 'Activity Log';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.activity-log';

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('user')
                    ->label('User')
                    ->getStateUsing(fn (Activity $record) =>
                        $record->causer?->name ?? $record->subject?->name ?? 'Unknown'
                    )
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('causer', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                    }),

                TextColumn::make('description')
                    ->label('Description')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('Y-m-d h:i A', 'America/New_York')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No activity found.')
            ->paginated([10, 25, 50]);
    }
}
