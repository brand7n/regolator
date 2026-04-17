<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

class ActivityLogWidget extends BaseWidget
{
    protected static ?string $heading = 'Activity Log';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->with(['causer', 'subject'])
                    ->where('description', '!=', 'updated')
                    ->where('created_at', '>=', Carbon::now()->subMonth())
            )
            ->columns([
                TextColumn::make('user')
                    ->label('User')
                    ->getStateUsing(function (Activity $record) {
                        return optional($record->causer)->name
                            ?? optional($record->subject)->name
                            ?? $record->properties->get('old.name')
                            ?? $record->properties->get('attributes.name')
                            ?? 'System';
                    }),

                TextColumn::make('subject_label')
                    ->label('Subject')
                    ->getStateUsing(function (Activity $record) {
                        $subject = $record->subject;
                        $type = $record->subject_type ? class_basename($record->subject_type) : null;

                        if ($subject) {
                            /** @var Model&object{name?: string, id: int} $subject */
                            return match ($type) {
                                'User', 'Event' => $subject->name ?? "{$type} #{$subject->id}",
                                'Order' => "Order #{$subject->id}",
                                default => "{$type} #{$subject->id}",
                            };
                        }

                        if (! $type) {
                            return null;
                        }

                        return "{$type} #{$record->subject_id}";
                    }),

                TextColumn::make('description')
                    ->label('Description')
                    ->wrap()
                    ->formatStateUsing(function (string $state, Activity $record) {
                        $changed = $record->properties->get('attributes', []);
                        $old = $record->properties->get('old', []);

                        if ($state === 'deleted') {
                            $name = $old['name'] ?? $changed['name'] ?? null;

                            return $name ? "deleted: {$name}" : $state;
                        }

                        $ignore = ['updated_at', 'remember_token', 'email_verified_at', 'password'];
                        if ($old) {
                            /** @var array<string, mixed> $changed */
                            $fields = collect($changed)
                                ->filter(fn ($value, $key) => ! in_array($key, $ignore) && ($value !== ($old[$key] ?? null)))
                                ->keys()
                                ->all();
                            if ($fields) {
                                return $state.' ('.implode(', ', $fields).')';
                            }
                        }

                        return $state;
                    }),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('Y-m-d h:i A', 'America/New_York')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No activity found.')
            ->defaultPaginationPageOption(100)
            ->paginated([50, 100, 'all']);
    }
}
