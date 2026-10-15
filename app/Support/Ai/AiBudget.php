<?php

namespace App\Support\Ai;

use App\Models\ScheduleAssistantUsage;

/**
 * The tenant's monthly token meter — the thing standing between "useful
 * assistant" and "surprise invoice".
 *
 * The economics are specific to this feature and worth stating: the API key
 * belongs to the deployment (or the academy), but the *consumption* is driven
 * by every consultant in every tenant. Without a ceiling, one consultant having
 * a long conversation is indistinguishable from an attack. So the budget is
 * checked before the call and recorded after it, and the panel shows the meter
 * continuously rather than only when it is too late.
 *
 * A ceiling of 0 means unlimited, matching the convention `chat.idle_autoclose_days`
 * already uses for "off".
 */
class AiBudget
{
    /**
     * The tenant's consumption for the current month, with the budget resolved
     * and a percentage ready for the meter.
     *
     * @return array{period: string, tokens_in: int, tokens_out: int, tokens: int, requests: int, budget: int, remaining: ?int, percent: float, exceeded: bool, warn: bool}
     */
    public function status(): array
    {
        $period = ScheduleAssistantUsage::periodFor();
        $row = $this->row($period);

        $tokensIn = (int) ($row?->tokens_in ?? 0);
        $tokensOut = (int) ($row?->tokens_out ?? 0);
        $requests = (int) ($row?->request_count ?? 0);
        $tokens = $tokensIn + $tokensOut;

        $budget = $this->budget();
        $unlimited = $budget <= 0;
        $percent = $unlimited ? 0.0 : min(100.0, round($tokens / $budget * 100, 1));

        return [
            'period' => $period,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'tokens' => $tokens,
            'requests' => $requests,
            'budget' => $budget,
            'remaining' => $unlimited ? null : max(0, $budget - $tokens),
            'percent' => $percent,
            'exceeded' => ! $unlimited && $tokens >= $budget,
            'warn' => ! $unlimited && $percent >= $this->warnPercent(),
        ];
    }

    /** True when a new request may be made. */
    public function allows(): bool
    {
        return ! $this->status()['exceeded'];
    }

    /**
     * Record a completed turn's usage.
     *
     * `increment()` rather than read-modify-write, so two consultants asking at
     * the same instant cannot lose one another's tokens. The row is created on
     * first use of the month, and `firstOrCreate` is safe under that race
     * because (tenant_id, period) is unique — the loser of the race re-reads
     * and increments the winner's row.
     */
    public function record(int $inputTokens, int $outputTokens): void
    {
        if ($inputTokens <= 0 && $outputTokens <= 0) {
            return;
        }

        $period = ScheduleAssistantUsage::periodFor();

        ScheduleAssistantUsage::firstOrCreate(
            ['tenant_id' => tenant()?->id, 'period' => $period],
            ['tokens_in' => 0, 'tokens_out' => 0, 'request_count' => 0]
        );

        ScheduleAssistantUsage::query()
            ->where('period', $period)
            ->increment('tokens_in', $inputTokens);
        ScheduleAssistantUsage::query()
            ->where('period', $period)
            ->increment('tokens_out', $outputTokens);
        ScheduleAssistantUsage::query()
            ->where('period', $period)
            ->increment('request_count', 1);
    }

    /** Count a refused request too, so the meter reflects pressure, not just spend. */
    public function recordRefusal(): void
    {
        $period = ScheduleAssistantUsage::periodFor();

        ScheduleAssistantUsage::firstOrCreate(
            ['tenant_id' => tenant()?->id, 'period' => $period],
            ['tokens_in' => 0, 'tokens_out' => 0, 'request_count' => 0]
        );

        ScheduleAssistantUsage::query()->where('period', $period)->increment('request_count', 1);
    }

    private function row(string $period): ?ScheduleAssistantUsage
    {
        return ScheduleAssistantUsage::query()->where('period', $period)->first();
    }

    private function budget(): int
    {
        return max(0, (int) site('ai.monthly_token_budget', 0));
    }

    private function warnPercent(): float
    {
        return (float) max(10, min(100, (int) site('ai.warn_at_percent', 80)));
    }
}
