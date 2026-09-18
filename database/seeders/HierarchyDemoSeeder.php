<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Domain;
use App\Models\ScheduleItem;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HierarchyDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('HierarchyDemoSeeder is restricted to local/testing databases.');
        }

        $password = (string) env('HIERARCHY_DEMO_PASSWORD', 'HierarchyDemo!2026');
        if (strlen($password) < 12) {
            throw new RuntimeException('HIERARCHY_DEMO_PASSWORD must contain at least 12 characters.');
        }

        $domains = DB::transaction(function () use ($password) {
            [$moein, $moeinDomain] = $this->demoTenant('moein', 'مؤسسه معین', 'school', 'moeinacademy.test');
            [$salam, $salamDomain] = $this->demoTenant('salam', 'آکادمی سلام', 'consultancy', 'salamacademy.test');

            $this->school($moein, $moeinDomain, $password);
            $this->consultancy($salam, $salamDomain, $password);

            return [$moeinDomain, $salamDomain];
        });

        foreach ($domains as $domain) {
            Cache::forget(Domain::cacheKey($domain->domain));
            $this->command?->info("Hierarchy demo host: {$domain->domain}");
        }

        $this->command?->info('Demo cohort: Moein 1 principal, 9 teachers, 54 teacher-class links, 9 classrooms, 45 students; Salam 1 admin, 3 consultants, 12 students, no demo classrooms.');
        $this->command?->info('Existing accounts/passwords and owners were preserved. See README for local-only credentials.');
    }

    private function demoTenant(string $slug, string $name, string $type, string $host): array
    {
        $tenant = Tenant::query()->firstOrCreate(['slug' => $slug], [
            'name' => $name,
            'status' => 'active',
        ]);

        if ($tenant->hierarchy_type !== null && $tenant->hierarchy_type !== $type) {
            throw new RuntimeException("Tenant {$slug} already has a different hierarchy; no demo data was committed.");
        }

        if ($tenant->hierarchy_type === null) {
            // Does not depend on parent-owned Tenant::$fillable changes.
            $tenant->hierarchy_type = $type;
            $tenant->save();
        }

        // Reuse Moein's actual host. Never rename or repoint an existing domain.
        $domain = $slug === 'moein'
            ? Domain::query()->where('tenant_id', $tenant->id)->orderByDesc('is_primary')->orderBy('id')->first()
            : null;

        if (! $domain) {
            $domain = Domain::query()->firstOrCreate(['domain' => $host], [
                'tenant_id' => $tenant->id,
                'is_primary' => ! Domain::query()->where('tenant_id', $tenant->id)->where('is_primary', true)->exists(),
                'verified_at' => now(),
            ]);
        }

        if ((int) $domain->tenant_id !== (int) $tenant->id) {
            throw new RuntimeException("Demo host {$host} belongs to another tenant; no demo data was committed.");
        }

        return [$tenant, $domain];
    }

    private function school(Tenant $tenant, Domain $domain, string $password): void
    {
        $principal = $this->account(User::class, $tenant, $domain, 'hierarchy.principal@moein.test', [
            'name' => 'مریم فرهمند',
            'role' => User::ROLE_TENANT_ADMIN,
            'bio' => 'مدیر دوره متوسطه اول؛ همراه دبیران و خانواده‌ها در مسیر رشد دانش‌آموزان.',
            'preferences' => ['demo' => ['focus' => 'رشد مهارت‌های فردی و همکاری']],
        ], $password);
        $this->ownerIfMissing($tenant, $principal);

        $teachers = [];
        foreach ([
            ['math', 'علی رضایی', 'ریاضی', 'آموزش مفهومی ریاضی با مسئله‌های روزمره و بازی‌های فکری.'],
            ['science', 'نرگس احمدی', 'علوم تجربی', 'یادگیری علوم با آزمایش‌های ساده و مشاهده طبیعت.'],
            ['persian', 'رضا کریمی', 'فارسی', 'علاقه‌مند به داستان‌خوانی، نگارش خلاق و ادبیات نوجوان.'],
            ['english', 'سارا محمدی', 'زبان انگلیسی', 'تمرین مکالمه و واژگان با داستان و فعالیت گروهی.'],
            ['arabic', 'حسین موسوی', 'عربی', 'آموزش واژگان و درک متن عربی با تمرین‌های کوتاه و پیوسته.'],
            ['social', 'الهام نادری', 'مطالعات اجتماعی', 'پیوند تاریخ و جغرافیا با پروژه‌های محلی و مسئولیت اجتماعی.'],
            ['physics', 'آرش کاظمی', 'فیزیک', 'آشنایی با نیرو، حرکت و انرژی از طریق مشاهده و فعالیت‌های ساده متناسب با متوسطه اول.'],
            ['biology', 'مهسا شریفی', 'زیست‌شناسی', 'شناخت بدن انسان، گیاهان و محیط زیست با مشاهده و پروژه‌های گروهی.'],
            ['chemistry', 'پیمان توکلی', 'شیمی', 'شناخت مواد و تغییرات آن‌ها با مثال‌های روزمره و آموزش ایمنی آزمایشگاه.'],
        ] as [$handle, $name, $subject, $bio]) {
            $teachers[] = [$this->account(User::class, $tenant, $domain, "hierarchy.{$handle}@moein.test", [
                'name' => $name,
                'role' => User::ROLE_TEACHER,
                'bio' => $bio,
                'preferences' => ['demo' => ['subject' => $subject, 'feedback' => 'بازخورد توصیفی هفتگی']],
            ], $password), $subject];
        }

        $classIndex = 0;
        foreach (['هفتم', 'هشتم', 'نهم'] as $grade) {
            foreach (['الف', 'ب', 'ج'] as $name) {
                $classroom = Classroom::withoutGlobalScopes()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'grade' => $grade,
                    'name' => $name,
                ]);
                $classTeachers = [];
                foreach ($teachers as $index => [$teacher, $subject]) {
                    // Each teacher has two classes in every grade, not a tenant-wide roster.
                    if ($classIndex % 3 !== $index % 3) {
                        $this->link('classroom_teacher', [
                            'tenant_id' => $tenant->id,
                            'classroom_id' => $classroom->id,
                            'user_id' => $teacher->id,
                        ], ['subject' => $subject]);
                        $classTeachers[] = $teacher;
                    }
                }

                for ($position = 0; $position < 5; $position++) {
                    $number = $classIndex * 5 + $position + 1;
                    $email = sprintf('hierarchy.student%02d@moein.test', $number);
                    $student = $this->account(Student::class, $tenant, $domain, $email,
                        $this->studentProfile($number, $grade, false), $password);
                    $this->link('classroom_student', [
                        'tenant_id' => $tenant->id,
                        'classroom_id' => $classroom->id,
                        'student_id' => $student->id,
                    ]);
                    $this->learningRecords($student, $classTeachers[$position % count($classTeachers)], false);
                }
                $classIndex++;
            }
        }
    }

    private function consultancy(Tenant $tenant, Domain $domain, string $password): void
    {
        $admin = $this->account(User::class, $tenant, $domain, 'hierarchy.admin@salam.test', [
            'name' => 'آرزو بهرامی',
            'role' => User::ROLE_TENANT_ADMIN,
            'bio' => 'مدیر آکادمی سلام؛ هماهنگ‌کننده مشاوره تحصیلی و برنامه‌ریزی دوره متوسطه دوم.',
            'preferences' => ['demo' => ['focus' => 'همکاری مشاوران و پیگیری فردی']],
        ], $password);
        $this->ownerIfMissing($tenant, $admin);

        $consultants = [];
        foreach ([
            ['nazari', 'بهاره نظری', 'برنامه‌ریزی متعادل، عادت‌های مطالعه و کاهش اضطراب آزمون.'],
            ['karimi', 'امیرحسین کریمی', 'تحلیل آزمون‌های ریاضی و پیگیری پیشرفت هفتگی.'],
            ['rahimi', 'سپیده رحیمی', 'انتخاب مسیر تحصیلی و تقویت مهارت مرور و خلاصه‌نویسی.'],
        ] as [$handle, $name, $bio]) {
            $consultants[] = $this->account(User::class, $tenant, $domain, "hierarchy.{$handle}@salam.test", [
                'name' => $name,
                'role' => User::ROLE_CONSULTANT_STAFF,
                'bio' => $bio,
                'preferences' => ['demo' => ['focus' => $bio, 'meeting' => 'گفت‌وگوی هفتگی']],
            ], $password);
        }

        for ($index = 0; $index < 12; $index++) {
            $grade = ['دهم', 'یازدهم', 'دوازدهم'][intdiv($index, 4)];
            $student = $this->account(Student::class, $tenant, $domain,
                sprintf('hierarchy.student%02d@salam.test', $index + 1),
                $this->studentProfile($index + 46, $grade, true), $password);
            $consultant = $consultants[$index % 3];
            $this->link('student_staff', [
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'user_id' => $consultant->id,
            ]);

            // Students 10–12 each have a second consultant; 01–09 remain individually assigned.
            if ($index >= 9) {
                $this->link('student_staff', [
                    'tenant_id' => $tenant->id,
                    'student_id' => $student->id,
                    'user_id' => $consultants[($index + 1) % 3]->id,
                ]);
            }
            $this->learningRecords($student, $consultant, true);
        }
    }

    private function account(string $model, Tenant $tenant, Domain $domain, string $email, array $profile, string $password): Model
    {
        $account = $model::withoutGlobalScopes()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'email' => $email,
        ], $profile + [
            'domain_id' => $domain->id,
            'password' => $password,
        ]);

        // Reuse compatible identities without changing their password/profile/role/domain.
        if (($account instanceof User && $account->role !== $profile['role'])
            || ($account->domain_id !== null && (int) $account->domain_id !== (int) $domain->id)
            || ($account instanceof Student && $account->grade !== $profile['grade'])) {
            throw new RuntimeException("Demo identity conflict for {$email}; no demo data was committed.");
        }

        return $account;
    }

    private function ownerIfMissing(Tenant $tenant, User $admin): void
    {
        Tenant::query()->whereKey($tenant->id)->whereNull('owner_user_id')
            ->update(['owner_user_id' => $admin->id]);
    }

    private function link(string $table, array $key, array $attributes = []): void
    {
        if (! DB::table($table)->where($key)->exists()) {
            DB::table($table)->insert($key + $attributes);
        }
    }

    private function studentProfile(int $number, string $grade, bool $senior): array
    {
        $names = ['آوا', 'پارسا', 'رها', 'آرین', 'نیلوفر', 'کیان', 'هستی', 'سام', 'نازنین', 'بردیا', 'ترانه', 'مانی', 'یاسمن', 'پویا', 'نگار'];
        $families = ['رستگار', 'صادقی', 'مهرابی', 'فراهانی'];
        $index = $number - 1;
        $interest = ['داستان‌خوانی', 'حل مسئله', 'نقاشی', 'آزمایش علمی', 'ورزش'][$index % 5];
        $goal = $senior
            ? ['دهم' => 'شناخت روش مطالعه در رشته جدید', 'یازدهم' => 'مرور منظم و تحلیل آزمون', 'دوازدهم' => 'آمادگی امتحان نهایی و انتخاب رشته'][$grade]
            : ['هفتم' => 'سازگاری با دوره متوسطه و نظم در تکالیف', 'هشتم' => 'تقویت کار گروهی و حل مسئله', 'نهم' => 'شناخت علاقه‌ها برای هدایت تحصیلی'][$grade];

        return [
            'name' => $names[$index % 15].' '.$families[intdiv($index, 15) % 4],
            'grade' => $grade,
            'gender' => ($index % 15) % 2 === 0 ? 'دختر' : 'پسر',
            'major' => $senior ? ['ریاضی و فیزیک', 'علوم تجربی', 'علوم انسانی'][$index % 3] : null,
            'bio' => "دانش‌آموز پایه {$grade} و علاقه‌مند به {$interest}؛ هدف امسال: {$goal}.",
            'preferences' => ['demo' => [
                'interest' => $interest,
                'goal' => $goal,
                'learning_style' => ['دیداری', 'تمرین عملی', 'گفت‌وگو و توضیح'][$index % 3],
            ]],
        ];
    }

    private function learningRecords(Student $student, User $staff, bool $senior): void
    {
        $week = Carbon::now()->startOfWeek(Carbon::SATURDAY);
        foreach ([false, true] as $completed) {
            $start = $week->copy()->addDays($completed ? 1 : 4)->setTime(16, 0);
            $title = $completed ? '[دموی سلسله‌مراتب] مرور هفتگی' : '[دموی سلسله‌مراتب] تمرین و برنامه‌ریزی';
            // Match independently of the current week so reruns never accumulate mock records.
            ScheduleItem::withoutGlobalScopes()->firstOrCreate([
                'tenant_id' => $student->tenant_id,
                'student_id' => $student->id,
                'title' => $title,
            ], [
                'week_start_date' => $week->toDateString(),
                'description' => $senior ? 'مرور درس‌های رشته، تحلیل خطاها و تنظیم هدف مطالعه بعدی.' : 'مرور درس‌های پایه، تمرین کوتاه و ثبت پرسش‌ها برای دبیر.',
                'start_datetime' => $start,
                'end_datetime' => $start->copy()->addMinutes(45),
                'color' => $completed ? '#22c55e' : '#3b82f6',
                'item_type' => 'consultant_event',
                'created_by_type' => 'user',
                'created_by_user_id' => $staff->id,
                'book_name' => 'دفتر تمرین پایه '.$student->grade,
                'page_count' => $senior ? 12 : 5,
                'test_count' => $senior ? 10 : 3,
                'is_completed' => $completed,
                'completion_timestamp' => $completed ? $start->copy()->addMinutes(45) : null,
            ]);
        }
    }
}
