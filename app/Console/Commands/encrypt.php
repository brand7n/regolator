<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Models\User;

class encrypt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:encrypt {email} {name} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = new User;
        $user->name = $this->argument('name');
        $user->email = $this->argument('email');
        $user->password = $this->argument('password');// Str::random(40);
        $user->save();
        
        $user_data = json_encode([
            'id' => $user->id,
            //'email' => $user->email,
            'hash' => $user->password,
            //'salt' => Str::random(5),
        ]);
        $this->info($user_data);
        $this->info(Crypt::encryptString($user_data));
    }
}
