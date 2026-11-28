<?php

namespace App\Modules\Notification\Jobs;

use App\Modules\Notification\Services\AlertEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAlertEscalationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function handle(AlertEngineService $alertEngine): void
    {
        $result = $alertEngine->processPendingEscalations();
        Log::info('Notification alert escalation sweep completed.', $result);
    }
}
