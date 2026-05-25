<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverrideTaskDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operator_name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'override_reason' => ['nullable', 'string'],
            'fields' => ['nullable', 'array'],
            'fields.type' => ['sometimes', Rule::enum(TaskType::class)],
            'fields.title' => ['sometimes', 'string', 'max:255'],
            'fields.summary' => ['sometimes', 'string'],
            'fields.priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'fields.suggested_project' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fields.suggested_team' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fields.suggested_next_action' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
