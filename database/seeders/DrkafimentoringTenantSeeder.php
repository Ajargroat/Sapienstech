<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\CompanyExamResult;
use App\Models\DealNotification;
use App\Models\ExamCompany;
use App\Models\ScheduleItem;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use App\Models\StudentDeal;
use App\Models\StudentTestAttempt;
use App\Models\Tenant;
use App\Models\Test;
use App\Models\User;
use App\Support\ChatActor;
use App\Support\ChatService;
use App\Support\DealService;
use App\Support\SiteConfig;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Makes the drkafimentoring tenant usable: one consultant owner, eight
 * students with portal logins, and content on every surface the dashboard
 * renders. Idempotent and safe on production — rerunning it never duplicates
 * a row, and it never overwrites an existing password or the owner link.
 */
class DrkafimentoringTenantSeeder extends Seeder
{
    public const SLUG = 'drkafimentoring';

    /** Q1=A: the documented dev/demo credential. */
    private const PASSWORD = 'password';

    /** Single non-hierarchical consultant — never invent a staff cohort. */
    private const OWNER_EMAIL = 'kafi@drkafimentoring.ir';

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', self::SLUG)->firstOrFail();

        // The seeder runs outside a request, so nothing stamps tenant_id for
        // us and every global scope would otherwise resolve to no tenant.
        app()->instance('tenant', $tenant);
        app(SiteConfig::class)->flush();

        $owner = $this->owner($tenant);
        $students = $this->students($tenant, $owner);
        $this->blogPosts($tenant, $owner);
        $this->deals($owner, $students);
        $this->examResults($tenant, $students);
        $this->exams($tenant, $owner, $students);
        $this->schedule($tenant, $owner, $students);
        $this->chat($owner, $students);

        // Leave no residue: a bound tenant leaking out of a seeder would
        // scope the caller's next query.
        app()->forgetInstance('tenant');
        app(SiteConfig::class)->flush();

        $this->command?->info(sprintf(
            'مجموعهٔ %s آماده شد: ۱ مشاور، %d دانش‌آموز، %d پست وبلاگ.',
            $tenant->name,
            count($students),
            4
        ));
    }

    private function owner(Tenant $tenant): User
    {
        $owner = User::withoutGlobalScopes()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'email' => self::OWNER_EMAIL,
        ], [
            'name' => 'علیرضا کافی',
            // NULL on purpose: a tenant_admin may enter through any of their
            // tenant's domains (User::canLoginThrough).
            'domain_id' => null,
            'role' => User::ROLE_TENANT_ADMIN,
            'password' => self::PASSWORD,   // 'hashed' cast on the model
            'bio' => 'مشاور تحصیلی؛ همراهی فردی دانش‌آموزان متوسطهٔ دوم در انتخاب رشته و برنامه‌ریزی مطالعه.',
        ]);

        // The tidy record; isTenantOwner() already passes via the
        // hierarchy_type === null fallback. Never repoint an existing owner.
        Tenant::query()->whereKey($tenant->id)->whereNull('owner_user_id')
            ->update(['owner_user_id' => $owner->id]);

        return $owner;
    }

    /**
     * @return array<int, Student>
     */
    private function students(Tenant $tenant, User $owner): array
    {
        $roster = [
            ['آوا',   'رستگار',  'دهم',     'ریاضی و فیزیک'],
            ['پارسا', 'صادقی',   'دهم',     'علوم تجربی'],
            ['رها',   'مهرابی',  'یازدهم',  'علوم تجربی'],
            ['آرین',  'فراهانی', 'یازدهم',  'ریاضی و فیزیک'],
            ['نیلوفر', 'رستگار',  'یازدهم',  'علوم انسانی'],
            ['کیان',  'صادقی',   'دوازدهم', 'علوم تجربی'],
            ['هستی',  'مهرابی',  'دوازدهم', 'ریاضی و فیزیک'],
            ['سام',   'فراهانی', 'دوازدهم', 'علوم انسانی'],
        ];

        $students = [];

        foreach ($roster as $index => [$first, $family, $grade, $major]) {
            $email = sprintf('student%02d@drkafimentoring.ir', $index + 1);

            $student = Student::withoutGlobalScopes()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'email' => $email,
            ], [
                'name' => "{$first} {$family}",
                'password' => self::PASSWORD,   // 'hashed' cast on the model
                'grade' => $grade,
                'gender' => $index % 2 === 0 ? 'دختر' : 'پسر',
                'major' => $major,
                'bio' => "دانش‌آموز پایهٔ {$grade}، رشتهٔ {$major}؛ مسیر فردی زیر نظر علیرضا کافی.",
            ]);

            // Composite-PK link, inserted only when absent.
            $link = [
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'user_id' => $owner->id,
            ];

            if (! DB::table('student_staff')->where($link)->exists()) {
                DB::table('student_staff')->insert($link);
            }

            $students[] = $student;
        }

        return $students;
    }

    private function blogPosts(Tenant $tenant, User $owner): void
    {
        $posts = [
            ['drm-select-field', 'انتخاب رشته؛ از علاقه تا واقعیت',
                'سه پرسشی که پیش از انتخاب رشته باید پاسخشان را بدانید.',
                '<p>انتخاب رشته یک تصمیم یک‌شبه نیست؛ پیش از هر فهرست، باید علاقه، توان و واقعیت بازار کار را کنار هم گذاشت.</p>'],
            ['drm-study-plan', 'برنامهٔ مطالعهٔ هفتگی که واقعاً اجرا می‌شود',
                'چرا برنامه‌های بلندپروازانه شکست می‌خورند و چه جایگزینی دارد.',
                '<p>برنامهٔ خوب برنامهٔ کوتاه است؛ هر هفته چند هدف روشن و قابل اندازه‌گیری، نه فهرستی از آرزوها.</p>'],
            ['drm-exam-analysis', 'کارنامهٔ آزمون را چطور بخوانیم',
                'از درصد خام تا تصمیم هفتهٔ بعد؛ روش تحلیل کارنامه.',
                '<p>درصد خام تنها عدد کارنامه نیست؛ تراز، رتبه و روند چند آزمون با هم معنا پیدا می‌کنند.</p>'],
            ['drm-final-exams', 'امتحان نهایی و کنکور، دو هدف یک مسیر',
                'چطور برنامهٔ مطالعه را طوری بچینیم که هر دو هدف را پوشش دهد.',
                '<p>دو هدف، یک برنامه؛ اگر مرور امتحان نهایی درست چیده شود، همان مطالعه به کنکور هم خدمت می‌کند.</p>'],
        ];

        foreach ($posts as $index => [$slug, $title, $excerpt, $body]) {
            BlogPost::withoutGlobalScopes()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'slug' => $slug,
            ], [
                'author_user_id' => $owner->id,
                'title' => $title,
                'excerpt' => $excerpt,
                'body' => $body,
                'status' => BlogPost::STATUS_PUBLISHED,
                'published_at' => Carbon::today()->subDays(7 * (count($posts) - $index)),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<int, Student>  $students
     */
    private function deals(User $owner, array $students): void
    {
        $today = Carbon::today();

        foreach ($students as $index => $student) {
            // createDeal() throws when an open (unrenewed) period already
            // exists — that throw is what makes a naive rerun non-idempotent.
            if (StudentDeal::withoutGlobalScopes()->where('student_id', $student->id)->exists()) {
                continue;
            }

            // Three students sit inside the decision window (WINDOW_DAYS = 5)
            // so the consultant's due list and the student's renewal banner
            // are populated; the rest run long.
            $endsOn = $index < 3
                ? $today->copy()->addDays([3, 4, 2][$index])
                : $today->copy()->addDays(45 + $index * 10);

            $deal = DealService::createDeal(
                $student,
                $owner,
                $today->copy()->subDays(87)->toDateString(),
                $endsOn->toDateString(),
                4_500_000,
                $owner,
            );

            match ($index) {
                // A student awaiting the owner's verification of a receipt.
                0 => tap($deal, function (StudentDeal $deal) {
                    DealService::decide($deal, StudentDeal::DECISION_CONTINUE, null);
                    DealService::submitPayment($deal, 'DRM-1405-0001');
                }),
                // A student who declined to renew — the withdraw branch.
                2 => DealService::decide($deal, StudentDeal::DECISION_WITHDRAW, 'امسال تمرکز روی امتحان نهایی است.'),
                // Everyone else: the reminder the nightly command would write.
                default => DealNotification::updateOrCreate(
                    ['deal_id' => $deal->id, 'kind' => DealNotification::KIND_REMINDER, 'day' => $today->toDateString()],
                    [
                        'tenant_id' => $deal->tenant_id,
                        'student_id' => $deal->student_id,
                        'message' => sprintf(
                            '%s عزیز، دورهٔ مشاورهٔ شما در %s به پایان می‌رسد. لطفاً دربارهٔ ادامه یا انصراف خود تصمیم بگیرید.',
                            $student->name,
                            persian_digits($endsOn->format('Y/m/d'))
                        ),
                    ]
                ),
            };
        }
    }

    /**
     * @param  array<int, Student>  $students
     */
    private function examResults(Tenant $tenant, array $students): void
    {
        $companies = [];

        foreach ([['ghalamchi', 'قلم‌چی', '#E11D48'], ['maz', 'ماز', '#7C3AED']] as [$slug, $name, $color]) {
            $companies[$slug] = ExamCompany::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                ['name' => $name, 'color' => $color]
            );
        }

        $lessons = ['زیست‌شناسی' => 74, 'شیمی' => 68, 'فیزیک' => 61, 'ریاضی' => 55];

        foreach ($students as $index => $student) {
            $correct = 72 + ($index * 3) % 24;

            CompanyExamResult::withoutGlobalScopes()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'company_id' => $companies['ghalamchi']->id,
                'title' => 'آزمون آزمایشی ۲۵ — جامع',
            ], [
                'exam_date' => Carbon::today()->subDays(9),
                'status' => 'completed',
                'total_questions' => 120,
                'correct_count' => $correct,
                'wrong_count' => 120 - $correct - 12,
                'blank_count' => 12,
                'percent' => round($correct / 120 * 100, 2),
                'exam_rank' => 900 + $index * 240,
                'participants' => 92_000,
                'lesson_percents' => $lessons,
            ]);
        }
    }

    /**
     * @param  array<int, Student>  $students
     */
    private function exams(Tenant $tenant, User $owner, array $students): void
    {
        $tests = [];

        foreach ([
            ['آزمون جامع ریاضی — نوبت اول', 'ریاضی', 'comprehensive'],
            ['کوئیز فصلی فیزیک (حرکت‌شناسی)', 'فیزیک', 'quiz'],
        ] as [$title, $lesson, $type]) {
            $tests[] = Test::withoutGlobalScopes()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'test_title' => $title,
            ], [
                'lesson' => $lesson,
                'exam_type' => $type,
                'time_limit_minutes' => 90,
                'total_marks' => 20,
                'created_by_user_id' => $owner->id,
            ]);
        }

        foreach ($students as $index => $student) {
            foreach ($tests as $slot => $test) {
                $completed = ($index + $slot) % 2 === 0;

                $assignment = StudentAssignedQuiz::withoutGlobalScopes()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'test_id' => $test->id,
                    'student_id' => $student->id,
                ], [
                    'assigned_by_user_id' => $owner->id,
                    'assigned_at' => Carbon::today()->subDays($completed ? 20 : 0),
                    'scheduled_at' => $completed
                        ? Carbon::today()->subDays(12)
                        : Carbon::today()->addDays(3 + $index % 5),
                    'ends_at' => $completed ? null : Carbon::today()->addDays(20),
                    'status' => $completed ? 'completed' : 'scheduled',
                    'is_completed' => $completed,
                ]);

                if (! $completed) {
                    continue;
                }

                StudentTestAttempt::withoutGlobalScopes()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'assignment_id' => $assignment->id,
                ], [
                    'student_id' => $student->id,
                    'test_id' => $test->id,
                    'status' => 'completed',
                    'started_at' => Carbon::today()->subDays(12),
                    'score_raw' => 16.5,
                    'score_simple_percent' => 82.5,
                    'score_negative_percent' => 79.0,
                    'time_taken_seconds' => 4200,
                    'completed_at' => Carbon::today()->subDays(12),
                ]);
            }
        }
    }

    /**
     * @param  array<int, Student>  $students
     */
    private function schedule(Tenant $tenant, User $owner, array $students): void
    {
        $today = Carbon::today();
        $weekStart = $today->copy()->subDays(($today->dayOfWeek + 1) % 7)->startOfDay();

        foreach ($students as $student) {
            foreach ([[0, 17, 'حل تمرین ریاضی'], [2, 18, 'مرور زیست‌شناسی'], [4, 16, 'تحلیل کارنامه']] as [$offset, $hour, $title]) {
                $start = $weekStart->copy()->addDays($offset)->setTime($hour, 0);

                // The `date` cast stores a full datetime, so match on the
                // Carbon (bound as Y-m-d H:i:s) rather than a 'Y-m-d' string —
                // a plain date-string match misses and a rerun re-creates rows.
                ScheduleItem::withoutGlobalScopes()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'student_id' => $student->id,
                    'title' => $title,
                    'week_start_date' => $weekStart,
                ], [
                    'description' => 'جلسهٔ هفتگی با علیرضا کافی.',
                    'start_datetime' => $start,
                    'end_datetime' => $start->copy()->addMinutes(60),
                    'color' => '#2F5D62',
                    'item_type' => 'consultant_event',
                    'created_by_type' => 'user',
                    'created_by_user_id' => $owner->id,
                    'created_by_student_id' => null,
                    'is_completed' => $offset < ($today->dayOfWeek + 1) % 7,
                ]);
            }
        }
    }

    /**
     * @param  array<int, Student>  $students
     */
    private function chat(User $owner, array $students): void
    {
        $actor = ChatActor::make($owner);

        foreach (array_slice($students, 0, 3) as $student) {
            $conversation = ChatService::directThread($owner, $student);

            // wasRecentlyCreated survives refresh(): only the first run writes
            // messages, so a rerun adds nothing.
            if (! $conversation->wasRecentlyCreated) {
                continue;
            }

            // skipRateLimit: the guard is per conversation+actor and would
            // abort(429) from a console context rather than degrade.
            ChatService::sendMessage($conversation, $actor, 'سلام، برنامهٔ این هفته را در بخش برنامهٔ هفتگی گذاشتم.', skipRateLimit: true);
            ChatService::sendMessage($conversation, $actor, 'اگر جایی سؤال داشتید همین‌جا بنویسید.', skipRateLimit: true);
        }
    }
}
