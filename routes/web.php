<?php

use App\Models\Event;
use App\Models\Kennel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('dashboard');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function (Request $request) {
        $show = $request->query('show') === 'past' ? 'past' : 'upcoming';

        $events = Event::with('creator')
            ->when(
                $show === 'past',
                fn ($query) => $query->past()->orderByDesc('starts_at'),
                fn ($query) => $query->upcoming()->orderBy('starts_at'),
            )
            ->get();

        return view('dashboard', ['events' => $events, 'show' => $show]);
    })->name('dashboard');
    Route::get('/tracks', function (Request $request) {
        $kennel = $request->integer('kennel') ? Kennel::find($request->integer('kennel')) : null;

        abort_if($request->integer('kennel') && ! $kennel, 404);

        $from = $request->date('from')?->toDateString();
        $to = $request->date('to')?->toDateString();

        $events = Event::query()
            ->past()
            ->has('tracks')
            ->when($kennel, fn ($query) => $query->where('kennel_id', $kennel->id))
            ->when($from, fn ($query) => $query->where('starts_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn ($query) => $query->where('starts_at', '<=', Carbon::parse($to)->endOfDay()))
            ->with('kennel')
            ->withCount('tracks')
            ->orderByDesc('starts_at')
            ->get();

        return view('tracks', [
            'kennel' => $kennel,
            'events' => $events,
            'kennels' => $events->groupBy('kennel_id'),
            'from' => $from,
            'to' => $to,
        ]);
    })->name('tracks.index');
    Route::get('/events/{event}', function (Event $event) {
        $event->load('creator');

        return view('event', ['event' => $event]);
    })->name('events.show');
    // TODO: admin type routes
    // Route::get('/admin/users', function () {
    //     return view('users');
    // })->name('users');
});

Route::get('quicklogin/{key}', function ($key, Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    $result = User::fromQuickLogin($key);
    if ($result) {
        $user = $result['user'];
        Auth::login($user);
        activity()->causedBy($user)->withProperties([
            'expires_at' => $result['expires_at'],
            'ip' => $request->ip(),
        ])->log('quick login');
        $user->email_verified_at = Carbon::now();
        $user->save();

        $action = $request->query('action', 'dashboard');

        if ($action === 'unsubscribe') {
            activity()->causedBy($user)->withProperties([
                'ip' => $request->ip(),
            ])->log('unsubscribe requested');

            return redirect('dashboard');
        }

        return redirect($action);
    }
    abort(403, 'Invalid or expired login link.');
});

Route::get('/canihazemail', function () {
    return view('auth.quicklogin');
})->name('canihazemail');
