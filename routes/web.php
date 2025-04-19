<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Crypt;
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
    Route::get('/activity', function () {
        return view('activity');
    })->name('activity');
});

Route::get('quicklogin/{key}', function($key) {
    $user_data = json_decode(Crypt::decryptString($key), true);
    // now find the user
    $user = User::where('id', $user_data['id'])->first();
    if ($user && $user->password === $user_data['hash']) {
        Auth::login($user); // login user automatically
        activity()->causedBy($user)->log('quick login');
    }
    return redirect('dashboard');
});

