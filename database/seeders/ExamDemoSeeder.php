<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demo exam data for the consultant exams workspace.
 *
 * Reads database/seeders/exams-demo.json (Gregorian datetimes, Persian titles) and
 * writes real rows into tests / student_assigned_quizzes / student_test_attempts
 * so the card grid renders from the database. Re-running updates rows in place
 * (matched on test title, and on test + student for assignments).
 */
class ExamDemoSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('seeders/exams-demo.json');

        if (! is_file($file)) {
            $this->command?->warn("Not found: {$file}");

            return;
        }

        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        $tenantId = (int) DB::table('tenants')->orderBy('id')->value('id');
        abort_unless($tenantId > 0, 1, 'دیتابیس هیچ tenant ندارد.');

        $userId = (int) DB::table('users')->where('tenant_id', $tenantId)->orderBy('id')->value('id');
        abort_unless($userId > 0, 1, 'برای سِید آزمون‌ها دست‌کم یک کاربر (مشاور) لازم است.');

        $studentIds = array_map(
            'intval',
            DB::table('students')->where('tenant_id', $tenantId)->pluck('id')->all()
        );

        $tests = 0;
        $assignments = 0;

        foreach ($data['tests'] as $row) {
            $testId = DB::table('tests')
                ->where('tenant_id', $tenantId)
                ->where('test_title', $row['title'])
                ->value('id');

            $testAttributes = [
                'lesson' => $row['lesson'],
                'exam_type' => $row['exam_type'],
                'description' => $row['description'] ?? null,
                'question_count' => $row['question_count'] ?? null,
                'time_limit_minutes' => $row['time_limit_minutes'] ?? null,
                'total_marks' => $row['total_marks'] ?? 20,
            ];

            if ($testId) {
                DB::table('tests')->where('id', $testId)->update($testAttributes);
            } else {
                $testId = DB::table('tests')->insertGetId($testAttributes + [
                    'tenant_id' => $tenantId,
                    'test_title' => $row['title'],
                    'created_by_user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $tests++;
            }

            foreach ($row['assignments'] ?? [] as $assignment) {
                $studentId = (int) $assignment['student_id'];

                if (! in_array($studentId, $studentIds, true)) {
                    $this->command?->line("  skipped student {$studentId} (not in this tenant)");

                    continue;
                }

                $scheduledAt = trim($assignment['scheduled_at']);

                if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $scheduledAt)) {
                    $this->command?->line("  skipped invalid date: {$scheduledAt}");

                    continue;
                }

                $scheduledAt .= ':00';


                $assignmentId = DB::table('student_assigned_quizzes')
                    ->where('test_id', $testId)
                    ->where('student_id', $studentId)
                    ->value('id');

                $assignmentAttributes = [
                    'scheduled_at' => $scheduledAt,
                    'status' => $assignment['status'] ?? 'scheduled',
                    'is_completed' => ($assignment['status'] ?? '') === 'completed',
                    'updated_at' => now(),
                ];

                if ($assignmentId) {
                    DB::table('student_assigned_quizzes')
                        ->where('id', $assignmentId)
                        ->update($assignmentAttributes);
                } else {
                    $assignmentId = DB::table('student_assigned_quizzes')->insertGetId($assignmentAttributes + [
                        'tenant_id' => $tenantId,
                        'test_id' => $testId,
                        'student_id' => $studentId,
                        'assigned_by_user_id' => $userId,
                        'assigned_at' => now(),
                        'created_at' => now(),
                    ]);
                    $assignments++;
                }

                if (($assignment['status'] ?? '') === 'completed' && isset($assignment['score_raw'])) {
                    $this->seedAttempt($tenantId, $assignmentId, $studentId, (int) $testId, $assignment, $row, $scheduledAt);
                }
            }
        }

        $this->command?->info("Exam demo data: {$tests} آزمون جدید، {$assignments} اختصاص جدید.");
    }

    private function seedAttempt(
        int $tenantId,
        int $assignmentId,
        int $studentId,
        int $testId,
        array $assignment,
        array $test,
        string $scheduledAt
    ): void {
        $total = (float) ($test['total_marks'] ?? 20) ?: 20;
        $score = (float) $assignment['score_raw'];
        $percent = round($score / $total * 100, 2);
        $seconds = (int) round(((int) ($test['time_limit_minutes'] ?? 60)) * 60 * 0.85);

        $existing = DB::table('student_test_attempts')
            ->where('assignment_id', $assignmentId)
            ->orderByDesc('id')
            ->value('id');

        $attributes = [
            'status' => 'completed',
            'started_at' => $scheduledAt,
            'completed_at' => date('Y-m-d H:i:s', strtotime($scheduledAt) + $seconds),
            'score_raw' => $score,
            'score_simple_percent' => $percent,
            'score_negative_percent' => $percent,
            'time_taken_seconds' => $seconds,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('student_test_attempts')->where('id', $existing)->update($attributes);

            return;
        }

        DB::table('student_test_attempts')->insert($attributes + [
            'tenant_id' => $tenantId,
            'assignment_id' => $assignmentId,
            'student_id' => $studentId,
            'test_id' => $testId,
            'created_at' => now(),
        ]);
    }
}
