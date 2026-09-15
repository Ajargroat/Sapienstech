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

    /**
     * A freshly added list row is all-empty until the tenant types into it;
     * dropping those before validation lets "add a row, abandon it" pass
     * cleanly instead of failing the row's own required rules. A list left
     * with no rows at all becomes null, which normalize() treats as
     * "forget" — the file-owned items show through again.
     *
     * For typed rows (a `discriminant` def) only the cells the submitted
     * type actually uses count as content: a spacer or divider carries no
     * text at all and is intentional the moment it exists.
     */
    protected function prepareForValidation(): void
    {
        foreach (StudioSchema::fields() as $path => $field) {
            if (($field['control'] ?? '') !== 'list') {
                continue;
            }

            $rows = $this->input($path);

            if (! is_array($rows)) {
                continue;
            }

            $defs = (array) ($field['item'] ?? []);
            $disc = StudioSchema::discriminantKey($field);

            $kept = array_values(array_filter(
                $rows,
                static function ($row) use ($defs, $disc): bool {
                    if (! is_array($row)) {
                        return false;
                    }

                    $type = $disc !== null && isset($row[$disc]) && is_string($row[$disc])
                        ? trim($row[$disc])
                        : null;

                    $contentKeys = [];

                    foreach ($defs as $def) {
                        if (($def['control'] ?? 'text') === 'toggle' || $def['key'] === $disc) {
                            continue;
                        }

                        if (! StudioSchema::cellApplies($def, $disc, $type)) {
                            continue;
                        }

                        $contentKeys[] = $def['key'];
                    }

                    if ($contentKeys === []) {
                        return true;
                    }

                    foreach ($contentKeys as $key) {
                        $value = $row[$key] ?? null;

                        if ((is_string($value) && trim($value) !== '') || is_numeric($value)) {
                            return true;
                        }
                    }

                    return false;
                }
            ));

            $this->merge([$path => $kept === [] ? null : $kept]);
        }
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

        // List fields validate their rows through wildcard item rules; the
        // admin-only filter applies from the parent field, not the row key.
        foreach (StudioSchema::itemRules() as $path => $itemRules) {
            $field = StudioSchema::field(explode('.*', $path, 2)[0]);

            if (($field['group_admin'] ?? false) && ! $isAdmin) {
                continue;
            }

            $rules[$path] = $itemRules;
        }

        // Typed lists: `show_for` cells are validated per submitted row,
        // because their requiredness depends on that row's discriminant
        // value and required_if cannot name a wildcard sibling here. Rows
        // were re-indexed by prepareForValidation, so the concrete indices
        // below are the ones the validator will actually see.
        foreach (StudioSchema::fields() as $path => $field) {
            if (($field['control'] ?? '') !== 'list'
                || ($field['group_admin'] ?? false) && ! $isAdmin) {
                continue;
            }

            $disc = StudioSchema::discriminantKey($field);

            if ($disc === null) {
                continue;
            }

            foreach (array_values((array) $this->input($path, [])) as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $type = isset($row[$disc]) && is_string($row[$disc]) ? trim($row[$disc]) : null;

                foreach ((array) ($field['item'] ?? []) as $def) {
                    // Ungated cells ride the wildcard item rules above; only
                    // type-gated ones need concrete per-row validation.
                    if (! isset($def['show_for'])
                        || ! StudioSchema::cellApplies($def, $disc, $type)) {
                        continue;
                    }

                    $rules[$path.'.'.$i.'.'.$def['key']] = match (true) {
                        ($def['control'] ?? '') === 'toggle' => ['nullable', 'boolean'],
                        $type !== null && in_array($type, (array) ($def['required_for'] ?? []), true) => array_merge(
                            ['required'], array_slice((array) ($def['rules'] ?? []), 1)
                        ),
                        default => (array) ($def['rules'] ?? []),
                    };
                }
            }
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
