<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\RegoReminder;

class SendEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-emails';

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
        // $paidUsers = User::whereNotNull('rego_paid_at')->get();
        // foreach ($paidUsers as $user) {
            $user = User::find(9);
            $user_data = json_encode([
                'id' => $user->id,
                'hash' => $user->password,
            ]);
            $quick_login = Crypt::encryptString($user_data);

            // $this->info('Actual password: ' . $actual_password);
            // $this->info('Quick login: ' . $quick_login);
            $this->info('Sending to ' . $user->name);
            Mail::to($user)->send(new RegoReminder($user, url('/quicklogin/' . $quick_login)));
            sleep(1);
        // }
    }
}
