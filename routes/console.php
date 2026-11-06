<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:process-investments')->hourly()->withoutOverlapping();
Schedule::command('app:refresh-market-prices')->everyMinute()->withoutOverlapping();
