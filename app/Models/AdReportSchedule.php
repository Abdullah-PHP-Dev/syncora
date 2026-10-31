<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A scheduled ad performance report (see SendAdReports). */
class AdReportSchedule extends Model
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];
    public const PERIODS = [7, 14, 30, 90];

    protected $fillable = [
        'user_id', 'social_account_id', 'platform', 'name', 'frequency', 'weekday', 'day_of_month', 'send_time', 'timezone',
        'period_days', 'recipient_email', 'locale', 'status', 'next_run_at', 'last_sent_at', 'last_error',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'day_of_month' => 'integer',
        'period_days' => 'integer',
        'next_run_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /** The next send time after $from, in the schedule's own time zone, stored as UTC. */
    public function nextRunAfter(?Carbon $from = null): Carbon
    {
        $from = ($from ?? now())->copy()->setTimezone($this->timezone);
        [$h, $m] = array_map('intval', explode(':', $this->send_time ?: '09:00'));
        $at = $from->copy()->setTime($h, $m);

        if ($this->frequency === 'weekly') {
            $at = $at->copy()->addDays(((int) $this->weekday - $at->dayOfWeek + 7) % 7);
            if ($at->lte($from)) {
                $at->addWeek();
            }
        } elseif ($this->frequency === 'monthly') {
            $at->day(min((int) ($this->day_of_month ?: 1), 28));
            if ($at->lte($from)) {
                $at->addMonthNoOverflow()->day(min((int) ($this->day_of_month ?: 1), 28));
            }
        } elseif ($at->lte($from)) {
            $at->addDay();
        }

        return $at->setTimezone('UTC');
    }
}
