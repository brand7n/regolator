<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** The ceiling `Event` applies: the tag is also the PayPal soft descriptor. */
    private const MAX_TAG = 22;

    public function up(): void
    {
        // Tags were never unique ("GOTENGAGED" covers 64 events), so the index
        // would fail on existing data. Suffix repeats with the start date and
        // then a counter, exactly as `Event::uniqueTag()` now does, and make the
        // slug mirror the final tag.
        $events = DB::table('events')->whereNotNull('event_tag')->where('event_tag', '<>', '')
            ->orderBy('id')->get(['id', 'event_tag', 'starts_at']);

        // The oldest event of a repeated tag keeps it verbatim; the rest move.
        $keeper = [];
        foreach ($events as $event) {
            $keeper[$event->event_tag] ??= (int) $event->id;
        }

        foreach ($events as $event) {
            $tag = (string) $event->event_tag;

            if ($keeper[$tag] !== (int) $event->id) {
                $dated = $event->starts_at
                    ? $this->suffix($tag, Carbon::parse($event->starts_at)->format('Ymd'))
                    : $tag;

                $tag = $this->free($dated, (int) $event->id) ? $dated : '';

                for ($n = 2; $tag === ''; $n++) {
                    $candidate = $this->suffix($dated, (string) $n);
                    $tag = $this->free($candidate, (int) $event->id) ? $candidate : '';
                }
            }

            DB::table('events')->where('id', $event->id)->update(['event_tag' => $tag, 'slug' => $tag]);
        }

        foreach (DB::table('events')->where(fn ($query) => $query->whereNull('event_tag')->orWhere('event_tag', ''))->get(['id', 'name']) as $event) {
            $base = Str::slug((string) $event->name) ?: 'event';
            $slug = $base;

            for ($n = 2; ! $this->free($slug, (int) $event->id, 'slug'); $n++) {
                $slug = "{$base}-{$n}";
            }

            DB::table('events')->where('id', $event->id)->update(['slug' => $slug]);
        }

        Schema::table('events', function (Blueprint $table) {
            $table->unique('event_tag');
        });
    }

    /** Nothing to undo: the suffixing and the slug rewrite are not reversible. */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['event_tag']);
        });
    }

    /** No other row holds this value in `$column`. */
    private function free(string $value, int $id, string $column = 'event_tag'): bool
    {
        return ! DB::table('events')->where($column, $value)->where('id', '<>', $id)->exists();
    }

    private function suffix(string $tag, string $suffix): string
    {
        $suffix = '-'.$suffix;

        return mb_substr($tag, 0, max(1, self::MAX_TAG - mb_strlen($suffix))).$suffix;
    }
};
