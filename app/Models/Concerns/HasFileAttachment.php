<?php

namespace App\Models\Concerns;

use App\Support\TenantUploads;

/**
 * A nullable `file_path` column holding one attachment in the tenant's own
 * asset tree (TenantUploads), plus the display helpers the assignment views
 * need: a URL to link to, and an image test for rendering a thumbnail
 * rather than a paperclip.
 *
 * The model using this must list `file_path` in its $fillable.
 */
trait HasFileAttachment
{
    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    /** Public URL for the stored file — null when nothing is attached. */
    public function fileUrl(): ?string
    {
        return TenantUploads::url($this->file_path);
    }

    /** Whether the attachment is a picture the board can thumbnail inline. */
    public function isImageFile(): bool
    {
        $extension = strtolower(pathinfo((string) $this->file_path, PATHINFO_EXTENSION));

        return in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true);
    }
}
