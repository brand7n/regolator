<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Carbon;
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
        return view('dashboard');
    })->name('dashboard');
    // TODO: admin type routes
    Route::get('/activity', function () {
        return view('activity');
    })->name('activity');
    Route::get('/users', function () {
        return view('users');
    })->name('users');
});

Route::get('quicklogin/{key}', function($key) {
    $user_data = json_decode(Crypt::decryptString($key), true);
    // now find the user
    $user = User::where('id', $user_data['id'])->first();
    if ($user && $user->password === $user_data['hash']) {
        Auth::login($user); // login user automatically
        activity()->causedBy($user)->log('quick login');
        $user->email_verified_at = Carbon::now();
        $user->save();
        return redirect('dashboard');

    }
});

Route::get('/waiting', function () {
    return view('waiting');
})->name('waiting');

Route::get('/canihazemail', function () {
    return view('waiting');
})->name('waiting');