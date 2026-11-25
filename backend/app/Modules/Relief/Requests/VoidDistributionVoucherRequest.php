<?php

namespace App\Modules\Relief\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidDistributionVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
