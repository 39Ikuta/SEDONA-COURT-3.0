<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pms:clean-trial', function (): int {
    $this->error('Trial resets are disabled in v3 to protect financial and audit history. Use a separate disposable test database.');

    return 1;
})->purpose('Protect financial history from trial resets');
