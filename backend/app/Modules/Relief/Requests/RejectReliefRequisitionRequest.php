<?php

namespace App\Modules\Relief\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectReliefRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_notes' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
