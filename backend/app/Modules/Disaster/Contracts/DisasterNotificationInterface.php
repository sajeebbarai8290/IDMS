<?php

namespace App\Modules\Disaster\Contracts;

use App\Modules\Disaster\Models\DisasterWarning;

interface DisasterNotificationInterface
{
    /**
     * Dispatch an early warning bulletin to the underlying alert transport (Module 13 adapter contract).
     *
     * @return array{status: string, reference: string}
     */
    public function dispatchWarning(DisasterWarning $warning): array;
}
