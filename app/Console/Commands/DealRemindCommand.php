<?php

namespace App\Console\Commands;

use App\Support\DealService;
use Illuminate\Console\Command;

/**
 * Daily renewal reminders: for every tenant, persist a student-portal
 * notification for each unrenewed, non-withdrawn deal that is within the
 * decision window (or already overdue). The unique (deal, kind, day) index
 * makes reruns on the same day a no-op, so a missed nightly run heals itself
 * the next time anything triggers it.
 */
class DealRemindCommand extends Command
{
    protected $signature = 'deals:remind-due';

    protected $description = 'ثبت یادآوری تصمیم (ادامه/انصراف) برای دوره‌های نزدیک به پایان یا معوق';

    public function handle(): int
    {
        $count = DealService::remindDue();

        $this->info("یادآوری برای {$count} دوره ثبت شد.");

        return self::SUCCESS;
    }
}
