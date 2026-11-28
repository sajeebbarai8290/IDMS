<?php

namespace App\Modules\Volunteer\Requests;

use App\Modules\Volunteer\Models\Volunteer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSkillCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageSkill', Volunteer::class);
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'uuid'],
            'skill_code' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9_]+$/'],
            'name_en' => ['required', 'string', 'max:120'],
            'name_bn' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'requires_certification' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('skill_code')) {
            $this->merge(['skill_code' => strtoupper(trim((string) $this->input('skill_code')))]);
        }
    }
}
