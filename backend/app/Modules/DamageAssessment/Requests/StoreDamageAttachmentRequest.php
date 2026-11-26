<?php

namespace App\Modules\DamageAssessment\Requests;

use App\Modules\DamageAssessment\Models\DamageAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreDamageAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('assessment');
        return Gate::allows('updateAssessment', $assessment);
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv',
            ],
            'damage_sector_item_id' => ['nullable', 'uuid'],
            'attachment_kind' => ['nullable', 'string', Rule::in(DamageAttachment::KINDS)],
            'title' => ['nullable', 'string', 'max:180'],
            'caption' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'captured_at' => ['nullable', 'date'],
        ];
    }
}
