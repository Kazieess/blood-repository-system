<?php

use App\Actions\StockEvaluator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stock:evaluate', function (): void {
    app(StockEvaluator::class)->evaluate();
    $this->info('Stock conditions reconciled.');
})->purpose('Reconcile configured stock alerts, including time-driven expiry');

Schedule::command('stock:evaluate')->everyMinute()->withoutOverlapping();
