<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('traceability:public-demo-seed {--force : Explicitly allow creating fictional production demo records}', function (): int {
    if (app()->environment('production') && ! $this->option('force')) {
        $this->error('Production requires --force; verify the Traceability database first.');
        return 1;
    }

    $this->call('db:seed', [
        '--class' => \Database\Seeders\TraceabilityPublicDemoSeeder::class,
        '--force' => true,
    ]);

    $this->info('Isolated fictional data prepared in TRACE-PUBLIC; public routes remain opt-in.');
    return 0;
})->purpose('Populate an isolated read-only public showcase using fictional box scans and events');
