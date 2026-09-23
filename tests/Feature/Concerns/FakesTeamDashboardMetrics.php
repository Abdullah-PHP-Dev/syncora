<?php
namespace Tests\Feature\Concerns;

trait FakesTeamDashboardMetrics
{
    protected function fakeTeamDashboardMetrics(): void
    {
        $this->mock(\App\Services\TeamDashboardService::class, function ($mock) {
            $mock->shouldReceive('data')->andReturn([
                'counts' => ['subscribers' => 0, 'active' => 0, 'ads' => 0, 'posts' => 0],
                'chart' => ['labels' => [], 'subscribers' => [], 'ads' => [], 'posts' => []],
                'ticketCounts' => ['open' => 0, 'urgent' => 0, 'unassigned' => 0, 'mine' => 0],
                'recentTickets' => collect(),
            ]);
        });
    }
}
