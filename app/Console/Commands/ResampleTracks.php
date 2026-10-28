<?php

namespace App\Console\Commands;

use App\Models\EventTrack;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ResampleTracks extends Command
{
    protected $signature = 'app:resample-tracks';

    protected $description = 'Re-derive both stored polylines from the uploaded GPX files';

    public function handle(): int
    {
        $updated = 0;
        $unchanged = 0;
        $missing = 0;

        EventTrack::query()->orderBy('id')->chunkById(100, function ($tracks) use (&$updated, &$unchanged, &$missing) {
            foreach ($tracks as $track) {
                $disk = Storage::disk('public');

                if (! $disk->exists($track->path)) {
                    $missing++;

                    continue;
                }

                $points = EventTrack::pointsFrom($disk->get($track->path));

                if (count($points) < 2) {
                    $unchanged++;

                    continue;
                }

                $overview = EventTrack::overview($points);

                if ($points === $track->points && $overview === $track->points_overview) {
                    $unchanged++;

                    continue;
                }

                $track->update(['points' => $points, 'points_overview' => $overview]);
                $updated++;
            }
        });

        $this->info("Resampled {$updated} track(s); {$unchanged} unchanged, {$missing} missing files.");

        return self::SUCCESS;
    }
}
