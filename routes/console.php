<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:process-investments')->hourly()->withoutOverlapping();
Schedule::command('app:refresh-market-prices')->everyMinute()->withoutOverlapping();
Schedule::command('app:backup-database')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('app:auto-credit-deposits')->everyTwoMinutes()->withoutOverlapping();
