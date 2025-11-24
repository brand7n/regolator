<?php
// app/Filament/Widgets/ActivityLogWidget.php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

class ActivityLogWidget extends BaseWidget
{
    protected static ?string $heading = 'Activity Log';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 2; // Controls widget order on dashboard

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->with(['causer', 'subject'])
                    ->where('created_at', '>=', Carbon::now()->subMonth())
            )
            ->columns([
                TextColumn::make('user')
                    ->label('User')
                    ->getStateUsing(fn (Activity $record) => 
                        $record->causer?->name ?? $record->subject?->name ?? 'Unknown'
                    ),

                TextColumn::make('description')
                    ->label('Description')
                    ->wrap(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('Y-m-d h:i A', 'America/New_York')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No activity found.')
            ->paginated([5, 10, 25]);
    }
}
