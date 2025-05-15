<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class AddUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-user {email} {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = User::add($this->argument('email'), $this->argument('name'));
        // TODO: send email confirmation request
    }
}
