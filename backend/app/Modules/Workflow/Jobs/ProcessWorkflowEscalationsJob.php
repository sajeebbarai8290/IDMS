<?php

namespace App\Modules\Workflow\Jobs;

use App\Modules\Workflow\Services\WorkflowEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWorkflowEscalationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function handle(WorkflowEngineService $engine): void
    {
        $result = $engine->processOverdueEscalations();

        Log::info('Workflow SLA escalation sweep completed.', $result);
    }
}
