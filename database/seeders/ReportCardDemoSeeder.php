<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo report-card data for the consultant کارنامه workspace.
 *
 * Reads database/seeders/report-cards-demo.json and writes real rows into
 * exam_companies / company_exam_results so the card grid renders from the
 * database. Mirrors ExamDemoSeeder: re-running updates rows in place
 * (companies matched on tenant + slug, results on tenant + student + company +
 * title), and students that do not exist in this tenant are skipped.
 *
 * Runs outside a request, so there is no tenant context to stamp — every
 * insert carries tenant_id explicitly, same as the exam seeder.
 */
class ReportCardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('seeders/report-cards-demo.json');

        if (! is_file($file)) {
            $this->command?->warn("Not found: {$file}");

            return;
        }

        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        $tenantId = (int) DB::table('tenants')->orderBy('id')->value('id');
        abort_unless($tenantId > 0, 1, 'دیتابیس هیچ tenant ندارد.');

        $studentIds = array_map(
            'intval',
            DB::table('students')->where('tenant_id', $tenantId)->pluck('id')->all()
        );

        $companies = $this->seedCompanies($tenantId, $data['companies'] ?? []);
        $results = 0;

        foreach ($data['results'] ?? [] as $row) {
            $studentId = (int) $row['student_id'];
            $companyId = $companies[$row['company']] ?? null;

            if (! $companyId) {
                $this->command?->line("  skipped result '{$row['title']}': unknown company '{$row['company']}'");

                continue;
            }

            if (! in_array($studentId, $studentIds, true)) {
                $this->command?->line("  skipped student {$studentId} (not in this tenant)");

                continue;
            }

            $attributes = [
                'exam_date' => $row['exam_date'],
                'status' => $row['status'] ?? 'completed',
                'total_questions' => $row['total_questions'] ?? null,
                'correct_count' => $row['correct_count'] ?? null,
                'wrong_count' => $row['wrong_count'] ?? null,
                'blank_count' => $row['blank_count'] ?? null,
                'percent' => $row['percent'] ?? null,
                'exam_rank' => $row['exam_rank'] ?? null,
                'participants' => $row['participants'] ?? null,
                'lesson_percents' => isset($row['lesson_percents'])
                    ? json_encode($row['lesson_percents'], JSON_UNESCAPED_UNICODE)
                    : null,
                'updated_at' => now(),
            ];

            $existing = DB::table('company_exam_results')
                ->where('tenant_id', $tenantId)
                ->where('student_id', $studentId)
                ->where('company_id', $companyId)
                ->where('title', $row['title'])
                ->value('id');

            if ($existing) {
                DB::table('company_exam_results')->where('id', $existing)->update($attributes);
            } else {
                DB::table('company_exam_results')->insert($attributes + [
                    'tenant_id' => $tenantId,
                    'student_id' => $studentId,
                    'company_id' => $companyId,
                    'title' => $row['title'],
                    'created_at' => now(),
                ]);
                $results++;
            }
        }

        $this->command?->info('داده‌های نمونه کارنامه: ' . count($companies) . ' شرکت، ' . persian_digits($results) . ' نتیجه جدید.');
    }

    /**
     * @return array<string, int> slug -> company id
     */
    private function seedCompanies(int $tenantId, array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $id = DB::table('exam_companies')
                ->where('tenant_id', $tenantId)
                ->where('slug', $row['slug'])
                ->value('id');

            if ($id) {
                DB::table('exam_companies')->where('id', $id)->update([
                    'name' => $row['name'],
                    'color' => $row['color'] ?? null,
                    'updated_at' => now(),
                ]);
            } else {
                $id = DB::table('exam_companies')->insertGetId([
                    'tenant_id' => $tenantId,
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'color' => $row['color'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $ids[$row['slug']] = (int) $id;
        }

        return $ids;
    }
}
