<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:verify-pending-orders')->everyFiveMinutes();
