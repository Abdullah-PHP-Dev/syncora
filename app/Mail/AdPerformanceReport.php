<?php

namespace App\Mail;

use App\Models\AdReportSchedule;
use App\Models\SocialAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Scheduled ad performance report: summary in the body, full data as CSV. */
class AdPerformanceReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AdReportSchedule $schedule, public SocialAccount $account, public array $report)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('ads_campaigns.report.subject', [
            'name' => $this->schedule->name,
            'account' => $this->account->name,
            'since' => $this->report['insights']['since'],
            'until' => $this->report['insights']['until'],
        ]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ad-performance-report', with: [
            'schedule' => $this->schedule,
            'account' => $this->account,
            'insights' => $this->report['insights'],
            // Meta reports carry a summary; the other platforms (PlatformReportService) carry metrics.
            'summary' => $this->report['summary'] ?? null,
            'metrics' => $this->report['metrics'] ?? null,
            'campaigns' => array_slice($this->report['campaigns'], 0, 10),
            'rtl' => app()->getLocale() === 'ar',
        ]);
    }

    public function attachments(): array
    {
        $file = sprintf('%s-ads-%s-to-%s.csv', $this->schedule->platform ?: 'facebook', $this->report['insights']['since'], $this->report['insights']['until']);

        return [Attachment::fromData(fn () => $this->report['csv'], $file)->withMime('text/csv')];
    }
}
