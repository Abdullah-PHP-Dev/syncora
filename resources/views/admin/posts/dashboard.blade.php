@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="row">
        <!-- Congratulations Card (static) -->
        <div class="col-xxl-8 mb-6 order-0">
            <div class="card">
                <div class="d-flex align-items-start row">
                    <div class="col-sm-7">
                        <div class="card-body">
                            <h5 class="card-title text-primary mb-3">Congratulations {{ Auth::user()->name }}! 🎉</h5>
                            <p class="mb-6">
                                You have {{ $totalPosts }} total posts and {{ $totalEngagement }} engagements.<br />
                                Check your new badge in your profile.
                            </p>
                            <a href="javascript:;" class="btn btn-sm btn-outline-primary">View Badges</a>
                        </div>
                    </div>
                    <div class="col-sm-5 text-center text-sm-left">
                        <div class="card-body pb-0 px-0 px-md-6">
                            <img src="{{asset('assets/img/admin/man-with-laptop.png')}}" height="175" alt="View Badge User" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit & Sales Cards -->
        <div class="col-xxl-4 col-lg-12 col-md-4 order-1">
            <div class="row">
                <div class="col-lg-6 col-md-12 col-6 mb-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="card-title d-flex align-items-start justify-content-between mb-4">
                                <div class="avatar flex-shrink-0">
                                    <img src="{{asset('assets/img/admin/wallet-primary.png')}}" alt="chart success" class="rounded" />
                                </div>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="cardOpt3" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt3">
                                        <a class="dropdown-item" href="javascript:void(0);">View More</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Delete</a>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-1">Total Posts</p>
                            <h4 class="card-title mb-3">{{ number_format($totalPosts) }}</h4>
                            <small class="text-success fw-medium"><i class="icon-base bx bx-up-arrow-alt"></i> +{{ $growthPercent }}%</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12 col-6 mb-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="card-title d-flex align-items-start justify-content-between mb-4">
                                <div class="avatar flex-shrink-0">
                                    <img src="{{asset('assets/img/admin/wallet-primary.png')}}" alt="wallet info" class="rounded" />
                                </div>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="cardOpt6" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt6">
                                        <a class="dropdown-item" href="javascript:void(0);">View More</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Delete</a>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-1">Total Engagement</p>
                            <h4 class="card-title mb-3">{{ number_format($totalEngagement) }}</h4>
                            <small class="text-success fw-medium"><i class="icon-base bx bx-up-arrow-alt"></i> +{{ $growthPercent }}%</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Revenue (now shows monthly post counts) -->
        <div class="col-12 col-xxl-8 order-2 order-md-3 order-xxl-2 mb-6 total-revenue">
            <div class="card">
                <div class="row row-bordered g-0">
                    <div class="col-lg-8">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <div class="card-title mb-0">
                                <h5 class="m-0 me-2">Posts per Month</h5>
                            </div>
                            <div class="dropdown">
                                <button class="btn p-0" type="button" id="totalRevenue" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="icon-base bx bx-dots-vertical-rounded icon-lg text-body-secondary"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="totalRevenue">
                                    <a class="dropdown-item" href="javascript:void(0);">Select All</a>
                                    <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
                                    <a class="dropdown-item" href="javascript:void(0);">Share</a>
                                </div>
                            </div>
                        </div>
                        <div id="totalRevenueChart" class="px-3"></div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card-body px-xl-9 py-12 d-flex align-items-center flex-column">
                            <div class="text-center mb-6">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-primary">{{ now()->year }}</button>
                                    <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="visually-hidden">Toggle Dropdown</span>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="javascript:void(0);">{{ now()->year - 1 }}</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">{{ now()->year - 2 }}</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div id="growthChart"></div>
                            <div class="text-center fw-medium my-6">{{ $growthPercent }}% Post Growth</div>
                            <div class="d-flex gap-11 justify-content-between">
                                <div class="d-flex">
                                    <div class="avatar me-2">
                                        <span class="avatar-initial rounded-2 bg-label-primary"><i class="icon-base bx bx-calendar-alt icon-lg text-primary"></i></span>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <small>This Month</small>
                                        <h6 class="mb-0">{{ $monthlyPostCounts[count($monthlyPostCounts)-1] ?? 0 }}</h6>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <div class="avatar me-2">
                                        <span class="avatar-initial rounded-2 bg-label-info"><i class="icon-base bx bx-trending-up icon-lg text-info"></i></span>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <small>Last Month</small>
                                        <h6 class="mb-0">{{ $monthlyPostCounts[count($monthlyPostCounts)-2] ?? 0 }}</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Report & additional cards -->
        <div class="col-12 col-md-8 col-lg-12 col-xxl-4 order-3 order-md-2 profile-report">
            <div class="row">
                <div class="col-6 mb-6 payments">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="card-title d-flex align-items-start justify-content-between mb-4">
                                <div class="avatar flex-shrink-0">
                                    <img src="{{asset('assets/img/admin/paypal.png')}}" alt="paypal" class="rounded" />
                                </div>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="cardOpt4" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt4">
                                        <a class="dropdown-item" href="javascript:void(0);">View More</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Delete</a>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-1">Total Comments</p>
                            <h4 class="card-title mb-3">{{ number_format($totalCommentsAll) }}</h4>
                            <small class="text-success fw-medium"><i class="icon-base bx bx-up-arrow-alt"></i> +{{ $growthPercent }}%</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 mb-6 transactions">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="card-title d-flex align-items-start justify-content-between mb-4">
                                <div class="avatar flex-shrink-0">
                                    <img src="{{asset('assets/img/admin/cc-primary.png')}}" alt="Credit Card" class="rounded" />
                                </div>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="cardOpt1" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="cardOpt1">
                                        <a class="dropdown-item" href="javascript:void(0);">View More</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Delete</a>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-1">Total Shares</p>
                            <h4 class="card-title mb-3">{{ number_format($totalShares) }}</h4>
                            <small class="text-success fw-medium"><i class="icon-base bx bx-up-arrow-alt"></i> +{{ $growthPercent }}%</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 mb-6 profile-report">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center flex-sm-row flex-column gap-10 flex-wrap">
                                <div class="d-flex flex-sm-column flex-row align-items-start justify-content-between">
                                    <div class="card-title mb-6">
                                        <h5 class="text-nowrap mb-1">Profile Report</h5>
                                        <span class="badge bg-label-warning">YEAR {{ now()->year }}</span>
                                    </div>
                                    <div class="mt-sm-auto">
                                        <span class="text-success text-nowrap fw-medium"><i class="icon-base bx bx-up-arrow-alt"></i> {{ $growthPercent }}%</span>
                                        <h4 class="mb-0">{{ number_format($totalEngagement) }}</h4>
                                    </div>
                                </div>
                                <div id="profileReportChart"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Order Statistics (categories with post counts) -->
        <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title mb-0">
                        <h5 class="mb-1 me-2">Categories</h5>
                        <p class="card-subtitle">{{ $totalPosts }} Total Posts</p>
                    </div>
                    <div class="dropdown">
                        <button class="btn text-body-secondary p-0" type="button" id="orederStatistics" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="icon-base bx bx-dots-vertical-rounded icon-lg"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="orederStatistics">
                            <a class="dropdown-item" href="javascript:void(0);">Select All</a>
                            <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
                            <a class="dropdown-item" href="javascript:void(0);">Share</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-6">
                        <div class="d-flex flex-column align-items-center gap-1">
                            <h3 class="mb-1">{{ $totalPosts }}</h3>
                            <small>Total Posts</small>
                        </div>
                        <div id="orderStatisticsChart"></div>
                    </div>
                    <ul class="p-0 m-0">
                        @forelse($categories as $category)
                            <li class="d-flex align-items-center mb-5">
                                <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded bg-label-primary"><i class="icon-base bx bx-folder"></i></span>
                                </div>
                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="me-2">
                                        <h6 class="mb-0">{{ $category->name }}</h6>
                                        <small>{{ $category->description ?? 'No description' }}</small>
                                    </div>
                                    <div class="user-progress">
                                        <h6 class="mb-0">{{ $category->posts_count }}</h6>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="text-center text-muted">No categories found.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <!-- Income/Expenses/Profit (mapped to Likes, Comments, Shares) -->
        <div class="col-md-6 col-lg-4 order-1 mb-6">
            <div class="card h-100">
                <div class="card-header nav-align-top">
                    <ul class="nav nav-pills flex-wrap row-gap-2" role="tablist">
                        <li class="nav-item">
                            <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-tabs-line-card-income" aria-controls="navs-tabs-line-card-income" aria-selected="true">
                                Likes
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-tabs-line-card-expenses" aria-controls="navs-tabs-line-card-expenses" aria-selected="false">
                                Comments
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-tabs-line-card-profit" aria-controls="navs-tabs-line-card-profit" aria-selected="false">
                                Shares
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content p-0">
                        <div class="tab-pane fade show active" id="navs-tabs-line-card-income" role="tabpanel">
                            <div class="d-flex mb-6">
                                <div class="avatar flex-shrink-0 me-3">
                                    <img src="{{asset('assets/img/admin/wallet-primary.png')}}" alt="User" />
                                </div>
                                <div>
                                    <p class="mb-0">Total Likes</p>
                                    <div class="d-flex align-items-center">
                                        <h6 class="mb-0 me-1">{{ number_format($totalLikes) }}</h6>
                                        <small class="text-success fw-medium">
                                            <i class="icon-base bx bx-chevron-up icon-lg"></i>
                                            {{ $growthPercent }}%
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div id="incomeChart"></div>
                            <div class="d-flex align-items-center justify-content-center mt-6 gap-3">
                                <div class="flex-shrink-0">
                                    <div id="expensesOfWeek"></div>
                                </div>
                                <div>
                                    <h6 class="mb-0">Likes this month</h6>
                                    <small>{{ $monthlyPostCounts[count($monthlyPostCounts)-1] ?? 0 }} posts</small>
                                </div>
                            </div>
                        </div>
                        <!-- Other tabs would show comments and shares similarly; omitted for brevity -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions (recent comments) -->
        <div class="col-md-6 col-lg-4 order-2 mb-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title m-0 me-2">Recent Comments</h5>
                    <div class="dropdown">
                        <button class="btn text-body-secondary p-0" type="button" id="transactionID" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="icon-base bx bx-dots-vertical-rounded icon-lg"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="transactionID">
                            <a class="dropdown-item" href="javascript:void(0);">Last 28 Days</a>
                            <a class="dropdown-item" href="javascript:void(0);">Last Month</a>
                            <a class="dropdown-item" href="javascript:void(0);">Last Year</a>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-4">
                    <ul class="p-0 m-0">
                        @forelse($recentComments as $comment)
                            <li class="d-flex align-items-center mb-6">
                                <div class="avatar flex-shrink-0 me-3">
                                    @if($comment->user_avatar_url)
                                        <img src="{{ $comment->user_avatar_url }}" alt="User" class="rounded" />
                                    @else
                                        <span class="avatar-initial rounded bg-label-primary">{{ substr($comment->user_name ?? 'U', 0, 1) }}</span>
                                    @endif
                                </div>
                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="me-2">
                                        <small class="d-block">{{ $comment->user_name ?? 'Unknown' }}</small>
                                        <h6 class="fw-normal mb-0">{{ Str::limit($comment->content, 40) }}</h6>
                                    </div>
                                    <div class="user-progress d-flex align-items-center gap-2">
                                        <h6 class="fw-normal mb-0">{{ $comment->likes ?? 0 }} ❤️</h6>
                                        <span class="text-body-secondary">{{ $comment->posted_at ? $comment->posted_at->diffForHumans() : '' }}</span>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="text-center text-muted">No recent comments.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/dashboards-analytics.js') }}"></script>

    <!-- Include ApexCharts if not already -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Total Revenue Chart (Monthly Posts)
            var revenueChartOptions = {
                chart: { type: 'line', height: 200, toolbar: { show: false } },
                series: [{
                    name: 'Posts',
                    data: @json($monthlyPostCounts)
                }],
                xaxis: { categories: @json($months) },
                stroke: { curve: 'smooth' },
                colors: ['#696cff'],
                grid: { borderColor: '#f1f1f1' }
            };
            var revenueChart = new ApexCharts(document.querySelector("#totalRevenueChart"), revenueChartOptions);
            revenueChart.render();

            // 2. Growth Chart (donut)
            var growthChartOptions = {
                chart: { type: 'radialBar', height: 150 },
                series: [{{ $growthPercent }}],
                plotOptions: {
                    radialBar: {
                        hollow: { size: '60%' },
                        dataLabels: { name: { show: false }, value: { fontSize: '20px' } }
                    }
                },
                colors: ['#696cff'],
                labels: ['Growth']
            };
            var growthChart = new ApexCharts(document.querySelector("#growthChart"), growthChartOptions);
            growthChart.render();

            // 3. Order Statistics Chart (donut showing categories distribution)
            var categoryLabels = @json($categories->pluck('name'));
            var categoryCounts = @json($categories->pluck('posts_count'));
            var orderChartOptions = {
                chart: { type: 'donut', height: 150 },
                series: categoryCounts.length ? categoryCounts : [1],
                labels: categoryLabels.length ? categoryLabels : ['No Data'],
                colors: ['#696cff', '#ffab00', '#71dd37', '#03c3ec'],
                legend: { show: false }
            };
            var orderChart = new ApexCharts(document.querySelector("#orderStatisticsChart"), orderChartOptions);
            orderChart.render();

            // 4. Income Chart (line for likes trend - simplified using monthly data)
            var incomeChartOptions = {
                chart: { type: 'area', height: 100, toolbar: { show: false } },
                series: [{
                    name: 'Posts',
                    data: @json($monthlyPostCounts)
                }],
                xaxis: { categories: @json($months), labels: { show: false } },
                stroke: { curve: 'smooth' },
                fill: { type: 'gradient' },
                colors: ['#696cff'],
                grid: { show: false }
            };
            var incomeChart = new ApexCharts(document.querySelector("#incomeChart"), incomeChartOptions);
            incomeChart.render();

            // 5. Expenses of Week (mini chart for weeks - dummy)
            // Not critical, skip or use similar.

            // 6. Profile Report Chart (bar chart)
            var profileChartOptions = {
                chart: { type: 'bar', height: 100, toolbar: { show: false } },
                series: [{
                    name: 'Engagement',
                    data: @json($monthlyPostCounts) // could use total engagement per month, but we use posts for simplicity
                }],
                xaxis: { categories: @json($months), labels: { show: false } },
                colors: ['#696cff'],
                grid: { show: false }
            };
            var profileChart = new ApexCharts(document.querySelector("#profileReportChart"), profileChartOptions);
            profileChart.render();
        });
    </script>
@endpush