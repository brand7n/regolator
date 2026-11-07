<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:verify-pending-orders')->everyFiveMinutes();
Schedule::command('app:sync-google-calendar')->hourly();
Schedule::command('queue:work --stop-when-empty --tries=3')->everyMinute();
