<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\RegoInvite;

class encrypt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:encrypt {email} {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create user and encrypt quick URL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = new User;
        $user->name = $this->argument('name');
        $user->email = $this->argument('email');
        $actual_password = Str::random(40);
        $user->password = $actual_password;
        $user->save();
        
        $user_data = json_encode([
            'id' => $user->id,
            'hash' => $user->password,
        ]);
        $quick_login = Crypt::encryptString($user_data);

        $this->info('Actual password: ' . $actual_password);
        $this->info('Quick login: ' . $quick_login);

        Mail::to($user)->send(new RegoInvite());
    }
}
