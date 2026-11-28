<?php

use App\Modules\Workflow\Jobs\ProcessWorkflowEscalationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('workflow:escalate-overdue', function () {
    ProcessWorkflowEscalationsJob::dispatchSync();
    $this->info('Overdue workflow escalations processed.');
})->purpose('Process overdue workflow tasks and dispatch escalation notifications');

Schedule::job(new ProcessWorkflowEscalationsJob())->hourly();
