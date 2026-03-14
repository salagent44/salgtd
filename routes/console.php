<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('items:promote-tickler')->everyFiveMinutes();
Schedule::command('sync:cleanup')->weekly();
