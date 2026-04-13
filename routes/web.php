<?php

use App\Models\Event;
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
    Route::get('/dashboard', function () {
        $events = Event::where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->get();

        return view('dashboard', ['events' => $events]);
    })->name('dashboard');
    Route::get('/events/{event}', function (Event $event) {
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

        return redirect($request->query('action', 'dashboard'));
    }
    abort(403, 'Invalid or expired login link.');
});

Route::get('/canihazemail', function () {
    return view('auth.quicklogin');
})->name('canihazemail');
