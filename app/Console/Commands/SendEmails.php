<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
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
    protected $signature = 'app:send-emails {email}';

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
        $user = User::where('email', $this->argument('email'))->first();

        // $paidUsers = User::whereNotNull('rego_paid_at')->get();
        // foreach ($paidUsers as $user) {
            $user_data = json_encode([
                'id' => $user->id,
                'hash' => $user->password,
            ]);
            $quick_login = Crypt::encryptString($user_data);

            // $this->info('Actual password: ' . $actual_password);
            // $this->info('Quick login: ' . $quick_login);
            $this->info('Sending to ' . $user->name);
            try {
                Mail::to($user)->send(new RegoReminder($user, url('/quicklogin/' . $quick_login)));
            } catch (\Throwable $t) {
                Log::error("failed to send email", [
                    'user' => $user,
                    'error' => $t,
                ]);               
            }
            //sleep(1);
        // }
    }
}
