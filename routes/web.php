<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Crypt;
use App\Models\User;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

Route::get('quicklogin/{key}', function($key) {
    $user_data = json_decode(Crypt::decryptString($key), true);
    // now find the user
    $user = User::where('id', $user_data['id'])->first();
    if ($user && $user->password === $user_data['hash']){
        Auth::login($user); // login user automatically
        return redirect('dashboard');
    } else {
        return redirect('/');
    }
});

