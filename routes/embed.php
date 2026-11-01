<?php

use App\Models\Event;
use App\Models\EventTrack;
use App\Models\Kennel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Public, framable trail map for <iframe> tabs on other sites.
 *
 * These routes run OUTSIDE the `web` group on purpose: no session cookie (a
 * third-party iframe would not get it back anyway) and no global
 * X-Frame-Options header, which would block framing.
 */

$filters = function (Request $request): array {
    $kennel = $request->integer('kennel') ? Kennel::find($request->integer('kennel')) : null;

    abort_if($request->integer('kennel') && ! $kennel, 404);

    return [$kennel, $request->date('from')?->toDateString(), $request->date('to')?->toDateString()];
};

$headers = [
    // Framable on purpose: this is the page meant to be embedded elsewhere.
    'Content-Security-Policy' => 'frame-ancestors *',
    'Cache-Control' => 'public, max-age=300',
    'X-Content-Type-Options' => 'nosniff',
    // Not `no-referrer`: the OSM tile CDN answers referer-less requests with a
    // "denied" tile (`x-blocked: No referer sent`). `strict-origin-...` still
    // leaks only the origin, never the embed path or its query.
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
];

Route::get('/embed/trails', function (Request $request) use ($filters, $headers) {
    [$kennel, $from, $to] = $filters($request);

    $events = Event::query()
        ->withTrails($kennel?->id, $from, $to)
        ->with('tracks.user')
        ->orderByDesc('starts_at')
        ->get();

    $tracks = EventTrack::mapPayload($events);

    return response()->view('embed.trails', [
        'kennels' => Kennel::query()
            ->whereHas('events', fn ($query) => $query->withTrails())
            ->orderBy('abbreviation')
            ->get(['id', 'abbreviation', 'full_name']),
        'kennel' => $kennel,
        'from' => $from,
        'to' => $to,
        'tracks' => $tracks,
        'eventCount' => $events->count(),
        'trackCount' => count($tracks),
    ])->withHeaders($headers);
})->name('embed.trails');

Route::get('/embed/trails/detail', function (Request $request) use ($filters, $headers) {
    [$kennel, $from, $to] = $filters($request);

    $ids = EventTrack::detailPayload(
        Event::query()->withTrails($kennel?->id, $from, $to)->pluck('id'),
        (array) $request->query('ids', []),
    );

    return response()->json($ids)->withHeaders($headers);
})->name('embed.trails.detail');
