<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Assignment attachments — the teacher's brief on `assignments` and the file
 * the student hands in on the `assignment_students` pivot — stored in the
 * tenant's own asset tree exactly like lesson materials, so both sides share
 * one folder and one set of limits (theme.academics.assignments.*).
 */
class AssignmentFiles
{
    /** The TenantUploads folder both halves of the exchange write into. */
    public static function folder(): string
    {
        return (string) site('academics.assignments.folder', 'assignments');
    }

    /** Validation rules for the shared `file` field (optional on both sides). */
    public static function rules(): array
    {
        return [
            'nullable',
            'file',
            'max:'.self::maxKb(),
            'mimes:'.implode(',', self::types()),
        ];
    }

    /**
     * Store an upload and return its relative path. $existing (the path being
     * replaced, if any) is removed at the same time.
     */
    public static function store(UploadedFile $file, ?string $existing = null): string
    {
        return TenantUploads::store($file, self::folder(), $existing);
    }

    public static function maxKb(): int
    {
        return (int) site('academics.assignments.max_kb', 20480);
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_values((array) site('academics.assignments.types', ['pdf', 'png', 'jpg', 'jpeg']));
    }
}
