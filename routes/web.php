<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

Route::get('/', function () {
    return redirect('dashboard');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        $events = \App\Models\Event::where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->get();
        return view('dashboard', ['events' => $events]);
    })->name('dashboard');
    Route::get('/events/{event}', function (\App\Models\Event $event) {
        return view('event', ['event' => $event]);
    })->name('events.show');
    Route::get('/activity', function () {
        return view('activity');
    })->name('activity');
    // TODO: admin type routes
    // Route::get('/admin/users', function () {
    //     return view('users');
    // })->name('users');
});

Route::get('quicklogin/{key}', function($key, \Illuminate\Http\Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    $user = User::fromQuickLogin($key);
    if ($user) {
        Auth::login($user); // login user automatically
        activity()->causedBy($user)->log('quick login');
        $user->email_verified_at = Carbon::now();
        $user->save();
        return redirect($request->query('action', 'dashboard'));
    }
    abort(403, 'Invalid or expired login link.');
});

Route::get('/canihazemail', function () {
    return view('auth.quicklogin');
})->name('canihazemail');