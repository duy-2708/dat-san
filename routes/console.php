<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tác vụ định kỳ. Máy local: CHAY-WEB.bat mở sẵn "php artisan schedule:work".
| Trên server: cron mỗi phút gọi "php artisan schedule:run".
*/

Schedule::command('dat-san:nha-cho')->everyMinute()->withoutOverlapping();
Schedule::command('dat-san:nhac-lich')->everyTenMinutes()->withoutOverlapping();
Schedule::command('keo:quet')->everyMinute()->withoutOverlapping();
Schedule::command('thue-bao:nhac')->hourly()->withoutOverlapping();
Schedule::command('sao-luu:csdl')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [\App\Models\NhatKy::class]])->dailyAt('03:10');
