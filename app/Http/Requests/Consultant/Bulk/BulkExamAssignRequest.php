<?php

namespace App\Http\Requests\Consultant\Bulk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkExamAssignRequest extends FormRequest
{
    use SelectsStudents;

    public function authorize(): bool
    {
        return $this->user() !== null && tenant() !== null;
    }

    public function rules(): array
    {
        return array_merge($this->selectionRules(), [
            // exists must be tenant-scoped: the bare rule would accept an id
            // belonging to another tenant's test bank.
            'test_id' => [
                'required', 'integer',
                Rule::exists('tests', 'id')->where(fn ($q) => $q->where('tenant_id', tenant()->id)),
            ],
            'scheduled_at' => ['nullable', 'date'],
        ]);
    }

    public function messages(): array
    {
        return $this->selectionMessages();
    }
}
