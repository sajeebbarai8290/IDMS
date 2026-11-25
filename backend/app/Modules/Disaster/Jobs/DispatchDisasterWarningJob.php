<?php

namespace App\Modules\Disaster\Jobs;

use App\Modules\Disaster\Contracts\DisasterNotificationInterface;
use App\Modules\Disaster\Models\DisasterWarning;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchDisasterWarningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly string $warningId
    ) {}

    public function handle(DisasterNotificationInterface $notifier): void
    {
        $warning = DisasterWarning::find($this->warningId);
        if (!$warning) {
            return;
        }

        $result = $notifier->dispatchWarning($warning);

        $warning->update([
            'notification_dispatch_status' => $result['status'] ?? 'DISPATCHED',
            'notification_reference' => $result['reference'] ?? null,
        ]);
    }
}
