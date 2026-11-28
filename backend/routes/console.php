<?php

use App\Modules\Notification\Jobs\ProcessAlertEscalationsJob;
use App\Modules\Notification\Jobs\ProcessScheduledNotificationsJob;
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

Artisan::command('notifications:process-scheduled', function () {
    ProcessScheduledNotificationsJob::dispatchSync();
    $this->info('Scheduled notifications processed.');
})->purpose('Dispatch scheduled Module 13 notifications that are due');

Artisan::command('notifications:escalate-alerts', function () {
    ProcessAlertEscalationsJob::dispatchSync();
    $this->info('Unacknowledged alert escalations processed.');
})->purpose('Escalate unacknowledged Module 13 alerts past SLA delay');

Schedule::job(new ProcessWorkflowEscalationsJob())->hourly();
Schedule::job(new ProcessScheduledNotificationsJob())->everyMinute();
Schedule::job(new ProcessAlertEscalationsJob())->everyFiveMinutes();
