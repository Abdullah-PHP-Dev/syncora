<?php

namespace App\Support\Google;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * A reporting window and the equally long window before it. Presets end
 * yesterday - the last complete day in both GA4 and Search Console -
 * except "today". Custom ranges stay within Search Console's 16 months.
 */
class ReportRange
{
    public const PRESETS = ['today' => 1, '7d' => 7, '28d' => 28, '30d' => 30, '90d' => 90, '3m' => 90];

    public const MAX_DAYS = 366;

    private function __construct(
        public readonly string $key,
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly Carbon $previousStart,
        public readonly Carbon $previousEnd,
    ) {
    }

    public static function make(?string $key, ?string $start = null, ?string $end = null): self
    {
        $key = $key ?: '28d';

        if ($key === 'custom') {
            try {
                $from = Carbon::createFromFormat('Y-m-d', (string) $start)->startOfDay();
                $to = Carbon::createFromFormat('Y-m-d', (string) $end)->startOfDay();
            } catch (\Throwable $e) {
                throw ValidationException::withMessages(['start' => __('google_suite.validation.custom_dates')]);
            }

            if ($from->gt($to) || $to->gt(today()) || $from->lt(today()->subMonths(16)) || $from->diffInDays($to) + 1 > self::MAX_DAYS) {
                throw ValidationException::withMessages(['start' => __('google_suite.validation.custom_range')]);
            }
        } else {
            abort_unless(isset(self::PRESETS[$key]), 422, 'Unknown range');
            $days = self::PRESETS[$key];
            $to = $key === 'today' ? today() : today()->subDay();
            $from = $to->copy()->subDays($days - 1);
        }

        $length = (int) $from->diffInDays($to) + 1;
        $previousEnd = $from->copy()->subDay();

        return new self($key, $from, $to, $previousEnd->copy()->subDays($length - 1), $previousEnd);
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    /** @return array{0: string, 1: string} */
    public function current(): array
    {
        return [$this->start->toDateString(), $this->end->toDateString()];
    }

    /** @return array{0: string, 1: string} */
    public function previous(): array
    {
        return [$this->previousStart->toDateString(), $this->previousEnd->toDateString()];
    }

    public function cacheKey(): string
    {
        return implode('_', [...$this->current(), ...$this->previous()]);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'previous_start' => $this->previousStart->toDateString(),
            'previous_end' => $this->previousEnd->toDateString(),
            'days' => $this->days(),
        ];
    }
}
