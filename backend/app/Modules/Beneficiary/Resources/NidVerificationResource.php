<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\NidVerification\Resources\NidVerificationResource as CoreNidVerificationResource;

/**
 * Backward-compatible alias resource for Beneficiary module callers.
 */
class NidVerificationResource extends CoreNidVerificationResource
{
}
