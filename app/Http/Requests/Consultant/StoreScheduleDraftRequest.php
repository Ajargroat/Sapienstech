<?php

namespace App\Http\Requests\Consultant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class StoreScheduleDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant() !== null && $this->user() !== null;
    }

    public function rules(): array
    {
        $itemRules = (new StoreScheduleItemRequest)->rules();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'week_start_date' => $itemRules['week_start_date'],
            'blocks' => ['present', 'array', 'list', 'max:500'],
            'blocks.*' => ['required', 'array'],
        ];

        unset($itemRules['week_start_date']);
        foreach ($itemRules as $field => $fieldRules) {
            $rules['blocks.*.'.$field] = array_map(
                fn ($rule) => $rule === 'after:start_time' ? 'after:blocks.*.start_time' : $rule,
                $fieldRules
            );
        }

        return $rules;
    }

    public function draftData(): array
    {
        $data = $this->safe()->only(['name', 'week_start_date', 'blocks']);
        $fields = array_keys((new StoreScheduleItemRequest)->rules());
        $fields = array_diff($fields, ['week_start_date']);

        // Validating a parent array alone can retain unvalidated child keys.
        $data['blocks'] = array_map(fn (array $block) => Arr::only($block, $fields), $data['blocks']);

        return $data;
    }
}
