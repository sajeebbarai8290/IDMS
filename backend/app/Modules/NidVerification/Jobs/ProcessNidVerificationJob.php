<?php

namespace App\Modules\NidVerification\Jobs;

use App\Core\Models\User;
use App\Modules\NidVerification\Models\NidVerification;
use App\Modules\NidVerification\Services\NidVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Class ProcessNidVerificationJob
 *
 * Processes queued NID verification requests asynchronously via Redis queue.
 * Decrypts the temporary NID payload in memory and clears it immediately after processing.
 */
class ProcessNidVerificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(
        public readonly string $verificationId,
        public readonly ?string $actorUserId = null,
    ) {}

    public function handle(NidVerificationService $service): void
    {
        $verification = NidVerification::find($this->verificationId);
        if (!$verification) {
            return;
        }

        $rawNid = $verification->decryptTemporaryPayload();
        if (!$rawNid) {
            Log::error('Queued NID verification missing encrypted payload.', [
                'verification_id' => $verification->id,
                'reference' => $verification->verification_reference,
            ]);

            $verification->update([
                'verification_status' => NidVerification::STATUS_FAILED,
                'last_error_code' => 'MISSING_PAYLOAD',
                'last_error_message' => 'Encrypted verification payload unavailable for background processing.',
                'nid_encrypted_payload' => null,
            ]);
            return;
        }

        $actor = $this->actorUserId ? User::find($this->actorUserId) : null;
        $service->executeVerification($verification, $rawNid, $actor);
    }
}
