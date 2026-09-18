<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\LessonMaterial;
use App\Support\Academics;
use App\Support\TenantUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lesson material management: upload study files (PDF first-class) into
 * the tenant's own asset tree, serve downloads with a hit counter, and
 * delete them (file + row together).
 *
 * Files land under public/tenants/{slug}/materials/ through TenantUploads,
 * the same physically-partitioned per-tenant tree the chat attachments
 * and avatars use — no storage:link dependency, no cross-tenant mixing.
 */
class TeacherMaterialController extends Controller
{
    public function index(Request $request): View
    {
        $teacher = $request->user();

        $materials = $teacher->lessonMaterials()
            ->orderByDesc('created_at')
            ->get();

        $bySubject = $materials->groupBy(fn (LessonMaterial $m) => $m->subject ?: 'عمومی');

        $totalDownloads = $materials->sum('download_count');

        return view('teacher.materials', [
            'materials' => $materials,
            'bySubject' => $bySubject,
            'totalDownloads' => $totalDownloads,
            'gradeOptions' => Academics::gradeOptions(),
            'subjects' => Academics::subjects(),
            'uploadRules' => $this->rules(),
        ]);
    }

    public function store(Request $request): Response
    {
        $rules = $this->rules();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'subject' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:50'],
            'file' => array_merge(['required', 'max:'.(int) $rules['max_kb']], $rules['mimes']),
            'is_published' => ['nullable', 'boolean'],
        ]);

        $teacher = $request->user();

        $file = $request->file('file');

        $material = LessonMaterial::query()->create([
            'tenant_id' => $teacher->tenant_id,
            'teacher_id' => $teacher->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'subject' => $data['subject'] ?? null,
            'grade' => $data['grade'] ?? null,
            'file_path' => TenantUploads::store($file, $rules['folder']),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize() ?? 0,
            'mime_type' => $file->getMimeType(),
            'is_published' => $request->boolean('is_published', true),
        ]);

        return redirect()
            ->route('teacher.materials.index')
            ->with('success', 'جزوه با موفقیت بارگذاری شد.');
    }

    public function download(Request $request, LessonMaterial $material): Response
    {
        $teacher = $request->user();

        // Only the owning teacher (or another teacher of the same tenant) may
        // fetch from here; the tenant scope already bounds the lookup.
        abort_unless($material->teacher_id === $teacher->id, 404);

        $material->increment('download_count');

        return response()->download(
            public_path("tenants/{$material->tenant->slug}/".$material->file_path),
            $material->file_name
        );
    }

    public function destroy(Request $request, LessonMaterial $material): Response
    {
        $teacher = $request->user();

        abort_unless($material->teacher_id === $teacher->id, 404);

        DB::transaction(function () use ($material) {
            TenantUploads::delete($material->file_path);
            $material->delete();
        });

        return redirect()
            ->route('teacher.materials.index')
            ->with('success', 'جزوه حذف شد.');
    }

    /** Config-driven upload constraints (academics.materials.*). */
    private function rules(): array
    {
        $types = (array) site('academics.materials.types', ['pdf', 'jpg', 'jpeg', 'png']);

        return [
            'max_kb' => (int) site('academics.materials.max_kb', 20480),
            'mimes' => ['mimes:'.implode(',', $types)],
            'types' => $types,
            'folder' => (string) site('academics.materials.folder', 'materials'),
        ];
    }
}
