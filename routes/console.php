<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:verify-pending-orders')->everyFiveMinutes();
Schedule::command('queue:work --stop-when-empty --tries=3')->everyMinute();
