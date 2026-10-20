<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('loans:apply-penalties')->dailyAt('00:30')->withoutOverlapping();
