<?php

namespace App\Console\Commands;

use App\Ai\EventDetailsExtractor;
use App\Models\Event;
use Illuminate\Console\Command;

/** ponytail: one LLM call per event, sequential. Batch/queue it if a full backfill gets slow or pricey. */
class ExtractEventDetails extends Command
{
    protected $signature = 'app:extract-event-details
        {--event= : Only this event (id or slug)}
        {--limit=0 : Stop after this many events (0 = no limit)}
        {--force : Re-extract events that already have trail details}';

    protected $description = 'Pull hares, start, hash cash and on-after out of event descriptions with an LLM';

    public function handle(EventDetailsExtractor $extractor): int
    {
        $query = Event::query()->whereNotNull('description')->where('description', '!=', '');

        if ($event = $this->option('event')) {
            $query->where(fn ($q) => $q->where('id', is_numeric($event) ? (int) $event : 0)->orWhere('slug', $event));
        }

        if (! $this->option('force')) {
            $query->whereNull('properties->trail');
        }

        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        $events = $query->get();

        if ($events->isEmpty()) {
            $this->info('Nothing to extract.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($events->count());
        $stored = 0;

        foreach ($events as $event) {
            $details = $extractor->extract((string) $event->description, $event->location);

            if ($details !== null) {
                $event->properties = [...($event->properties ?? []), 'trail' => $details->toArray()];
                $event->save();
                $stored++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Stored trail details for {$stored} of {$events->count()} event(s).");

        return self::SUCCESS;
    }
}
