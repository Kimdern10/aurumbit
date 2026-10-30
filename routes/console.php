<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:process-investments')->hourly()->withoutOverlapping();
