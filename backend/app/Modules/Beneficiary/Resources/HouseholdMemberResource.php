<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HouseholdMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $decryptPiiRequested = $request->boolean('decrypt_pii');
        $canDecrypt = $user && $user->can('decryptPii', $this->household);

        $showCleartext = $decryptPiiRequested && $canDecrypt;

        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'full_name_en' => $this->full_name_en,
            'full_name_bn' => $this->full_name_bn,
            'relationship_to_hoh' => $this->relationship_to_hoh,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'age' => $this->age,
            'nid' => $showCleartext ? $this->getDecryptedNid() : $this->nid_masked,
            'nid_masked' => $this->nid_masked,
            'is_nid_unmasked' => $showCleartext,
            'has_disability' => (bool) $this->has_disability,
            'disability_type' => $this->disability_type,
            'is_pregnant_lactating' => (bool) $this->is_pregnant_lactating,
            'occupation' => $this->occupation,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
