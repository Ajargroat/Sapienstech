<?php

namespace App\Http\Requests\Consultant\Settings;

use App\Models\User;
use App\Support\StudioSchema;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an appearance-studio submission against the config/studio.php
 * whitelist. Rules are built from the schema (not hand-listed) so the form
 * and the guard can never drift, and admin-only groups (the feature
 * switches) are dropped entirely for non-admin users — a staff member cannot
 * even submit them, let alone change them.
 */
class StudioSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && tenant() !== null;
    }

    public function rules(): array
    {
        $isAdmin = $this->user() instanceof User && $this->user()->isTenantAdmin();

        $rules = ['scope' => ['required', 'in:everyone,me,preview']];

        foreach (StudioSchema::rules() as $path => $fieldRules) {
            $field = StudioSchema::field($path);

            if (($field['group_admin'] ?? false) && ! $isAdmin) {
                continue;
            }

            $rules[$path] = $fieldRules;
        }

        // Image fields validate as uploads, not scalars.
        foreach (StudioSchema::imageRules() as $path => $fileRules) {
            $field = StudioSchema::field($path);

            if (($field['group_admin'] ?? false) && ! $isAdmin) {
                continue;
            }

            $rules[$path] = ['nullable', 'file', ...array_slice($fileRules, 1)];
        }

        return $rules;
    }

    /**
     * The whitelisted, validated scalar values keyed by dotted path, ready to
     * be diffed. Images are excluded (handled by the controller as uploads).
     *
     * @return array<string, mixed>
     */
    public function studioValues(): array
    {
        $isAdmin = $this->user() instanceof User && $this->user()->isTenantAdmin();
        $values = [];

        foreach (array_keys(StudioSchema::rules()) as $path) {
            $field = StudioSchema::field($path);

            if (($field['group_admin'] ?? false) && ! $isAdmin) {
                continue;
            }

            // Toggles/checkboxes are absent when unchecked => false.
            if (($field['control'] ?? '') === 'toggle') {
                $values[$path] = $this->boolean($path);
            } else {
                $values[$path] = $this->input($path);
            }
        }

        return StudioSchema::normalize($values);
    }
}
