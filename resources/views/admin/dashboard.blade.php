@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12 mb-6">
            <div class="card">
                <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h5 class="card-title mb-1">Connect Social Media</h5>
                        <p class="mb-0 text-body-secondary">
                            @if (count($connectedPlatforms))
                                {{ count($connectedPlatforms) }} platform{{ count($connectedPlatforms) === 1 ? '' : 's' }} connected. Add another account to post, run ads, or manage messages from it.
                            @else
                                Connect a social account to start posting, running ads, and managing messages from one place.
                            @endif
                        </p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#connectSocialModal">
                        <i class="bx bx-link-alt me-1"></i> Connect Social Media
                    </button>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 mb-6 order-0">
            <div class="card">
                <div class="d-flex align-items-start row">
                    <div class="col-sm-7">
                        <div class="card-body">
                            <h5 class="card-title text-primary mb-3">Congratulations John! 🎉</h5>
                            <p class="mb-6">
                                You have done 72% more sales today.<br />Check your new badge in your profile.
                            </p>

                            <a href="javascript:;" class="btn btn-sm btn-outline-primary">View Badges</a>
                        </div>
                    </div>
                    <div class="col-sm-5 text-center text-sm-left">
                        <div class="card-body pb-0 px-0 px-md-6">
                            <img src="../assets/img/illustrations/man-with-laptop.png" height="175"
                                alt="View Badge User" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxl-4 col-lg-12 col-md-4 order-1">
            <div class="row">
                <div class="col-lg-6 col-md-12 col-6 mb-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="card-title d-flex align-items-start justify-content-between mb-4">
                                <div class="avatar flex-shrink-0">
                                    <img src="../assets/img/icons/unicons/chart-success.png" alt="chart success"
                                        class="rounded" />
                                </div>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="cardOpt3" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt3">
                                        <a class="dropdown-item" href="javascript:void(0);">View More</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Delete</a>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-1">Profit</p>
                            <h4 class="card-title mb-3">$12,628</h4>
                            <small class="text-success fw-medium"><i class="icon-base bx bx-up-arrow-alt"></i>
                                +72.80%</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12 col-6 mb-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="card-title d-flex align-items-start justify-content-between mb-4">
                                <div class="avatar flex-shrink-0">
                                    <img src="../assets/img/icons/unicons/wallet-info.png" alt="wallet info"
                                        class="rounded" />
                                </div>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="cardOpt6" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt6">
                                        <a class="dropdown-item" href="javascript:void(0);">View More</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Delete</a>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-1">Sales</p>
                            <h4 class="card-title mb-3">$4,679</h4>
                            <small class="text-success fw-medium"><i class="icon-base bx bx-up-arrow-alt"></i>
                                +28.42%</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Total Revenue -->
        <div class="col-12 col-xxl-8 order-2 order-md-3 order-xxl-2 mb-6 total-revenue">
            <div class="card">
                <div class="row row-bordered g-0">
                    <div class="col-lg-8">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <div class="card-title mb-0">
                                <h5 class="m-0 me-2">Total Revenue</h5>
                            </div>
                            <div class="dropdown">
                                <button class="btn p-0" type="button" id="totalRevenue" data-bs-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
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
                                    <button type="button" class="btn btn-outline-primary">
                                        <script>
                                            document.write(new Date().getFullYear() - 1);
                                        </script>
                                    </button>
                                    <button type="button"
                                        class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="visually-hidden">Toggle Dropdown</span>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="javascript:void(0);">2021</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">2020</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">2019</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div id="growthChart"></div>
                            <div class="text-center fw-medium my-6">62% Company Growth</div>

                            <div class="d-flex gap-11 justify-content-between">
                                <div class="d-flex">
                                    <div class="avatar me-2">
                                        <span class="avatar-initial rounded-2 bg-label-primary"><i
                                                class="icon-base bx bx-dollar icon-lg text-primary"></i></span>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <small>
                                            <script>
                                                document.write(new Date().getFullYear() - 1);
                                            </script>
                                        </small>
                                        <h6 class="mb-0">$32.5k</h6>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <div class="avatar me-2">
                                        <span class="avatar-initial rounded-2 bg-label-info"><i
                                                class="icon-base bx bx-wallet icon-lg text-info"></i></span>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <small>
                                            <script>
                                                document.write(new Date().getFullYear() - 2);
                                            </script>
                                        </small>
                                        <h6 class="mb-0">$41.2k</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--/ Total Revenue -->
        <div class="col-12 col-md-8 col-lg-12 col-xxl-4 order-3 order-md-2 profile-report">
            <div class="row">
                <div class="col-6 mb-6 payments">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="card-title d-flex align-items-start justify-content-between mb-4">
                                <div class="avatar flex-shrink-0">
                                    <img src="../assets/img/icons/unicons/paypal.png" alt="paypal" class="rounded" />
                                </div>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="cardOpt4" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt4">
                                        <a class="dropdown-item" href="javascript:void(0);">View More</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Delete</a>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-1">Payments</p>
                            <h4 class="card-title mb-3">$2,456</h4>
                            <small class="text-danger fw-medium"><i class="icon-base bx bx-down-arrow-alt"></i>
                                -14.82%</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 mb-6 transactions">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="card-title d-flex align-items-start justify-content-between mb-4">
                                <div class="avatar flex-shrink-0">
                                    <img src="../assets/img/icons/unicons/cc-primary.png" alt="Credit Card"
                                        class="rounded" />
                                </div>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="cardOpt1" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="cardOpt1">
                                        <a class="dropdown-item" href="javascript:void(0);">View More</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Delete</a>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-1">Transactions</p>
                            <h4 class="card-title mb-3">$14,857</h4>
                            <small class="text-success fw-medium"><i class="icon-base bx bx-up-arrow-alt"></i>
                                +28.14%</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 mb-6 profile-report">
                    <div class="card h-100">
                        <div class="card-body">
                            <div
                                class="d-flex justify-content-between align-items-center flex-sm-row flex-column gap-10 flex-wrap">
                                <div class="d-flex flex-sm-column flex-row align-items-start justify-content-between">
                                    <div class="card-title mb-6">
                                        <h5 class="text-nowrap mb-1">Profile Report</h5>
                                        <span class="badge bg-label-warning">YEAR 2022</span>
                                    </div>
                                    <div class="mt-sm-auto">
                                        <span class="text-success text-nowrap fw-medium"><i
                                                class="icon-base bx bx-up-arrow-alt"></i> 68.2%</span>
                                        <h4 class="mb-0">$84,686k</h4>
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
        <!-- Order Statistics -->
        <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title mb-0">
                        <h5 class="mb-1 me-2">Order Statistics</h5>
                        <p class="card-subtitle">42.82k Total Sales</p>
                    </div>
                    <div class="dropdown">
                        <button class="btn text-body-secondary p-0" type="button" id="orederStatistics"
                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
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
                            <h3 class="mb-1">8,258</h3>
                            <small>Total Orders</small>
                        </div>
                        <div id="orderStatisticsChart"></div>
                    </div>
                    <ul class="p-0 m-0">
                        <li class="d-flex align-items-center mb-5">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-primary"><i
                                        class="icon-base bx bx-mobile-alt"></i></span>
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                    <h6 class="mb-0">Electronic</h6>
                                    <small>Mobile, Earbuds, TV</small>
                                </div>
                                <div class="user-progress">
                                    <h6 class="mb-0">82.5k</h6>
                                </div>
                            </div>
                        </li>
                        <li class="d-flex align-items-center mb-5">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-success"><i
                                        class="icon-base bx bx-closet"></i></span>
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                    <h6 class="mb-0">Fashion</h6>
                                    <small>T-shirt, Jeans, Shoes</small>
                                </div>
                                <div class="user-progress">
                                    <h6 class="mb-0">23.8k</h6>
                                </div>
                            </div>
                        </li>
                        <li class="d-flex align-items-center mb-5">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-info"><i
                                        class="icon-base bx bx-home-alt"></i></span>
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                    <h6 class="mb-0">Decor</h6>
                                    <small>Fine Art, Dining</small>
                                </div>
                                <div class="user-progress">
                                    <h6 class="mb-0">849k</h6>
                                </div>
                            </div>
                        </li>
                        <li class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-secondary"><i
                                        class="icon-base bx bx-football"></i></span>
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                    <h6 class="mb-0">Sports</h6>
                                    <small>Football, Cricket Kit</small>
                                </div>
                                <div class="user-progress">
                                    <h6 class="mb-0">99</h6>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!--/ Order Statistics -->

        <!-- Expense Overview -->
        <div class="col-md-6 col-lg-4 order-1 mb-6">
            <div class="card h-100">
                <div class="card-header nav-align-top">
                    <ul class="nav nav-pills flex-wrap row-gap-2" role="tablist">
                        <li class="nav-item">
                            <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab"
                                data-bs-target="#navs-tabs-line-card-income" aria-controls="navs-tabs-line-card-income"
                                aria-selected="true">
                                Income
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" role="tab">Expenses</button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" role="tab">Profit</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content p-0">
                        <div class="tab-pane fade show active" id="navs-tabs-line-card-income" role="tabpanel">
                            <div class="d-flex mb-6">
                                <div class="avatar flex-shrink-0 me-3">
                                    <img src="../assets/img/icons/unicons/wallet.png" alt="User" />
                                </div>
                                <div>
                                    <p class="mb-0">Total Balance</p>
                                    <div class="d-flex align-items-center">
                                        <h6 class="mb-0 me-1">$459.10</h6>
                                        <small class="text-success fw-medium">
                                            <i class="icon-base bx bx-chevron-up icon-lg"></i>
                                            42.9%
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
                                    <h6 class="mb-0">Income this week</h6>
                                    <small>$39k less than last week</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--/ Expense Overview -->

        <!-- Transactions -->
        <div class="col-md-6 col-lg-4 order-2 mb-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title m-0 me-2">Transactions</h5>
                    <div class="dropdown">
                        <button class="btn text-body-secondary p-0" type="button" id="transactionID"
                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
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
                        <li class="d-flex align-items-center mb-6">
                            <div class="avatar flex-shrink-0 me-3">
                                <img src="../assets/img/icons/unicons/paypal.png" alt="User" class="rounded" />
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                    <small class="d-block">Paypal</small>
                                    <h6 class="fw-normal mb-0">Send money</h6>
                                </div>
                                <div class="user-progress d-flex align-items-center gap-2">
                                    <h6 class="fw-normal mb-0">+82.6</h6>
                                    <span class="text-body-secondary">USD</span>
                                </div>
                            </div>
                        </li>
                        <li class="d-flex align-items-center mb-6">
                            <div class="avatar flex-shrink-0 me-3">
                                <img src="../assets/img/icons/unicons/wallet.png" alt="User" class="rounded" />
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                    <small class="d-block">Wallet</small>
                                    <h6 class="fw-normal mb-0">Mac'D</h6>
                                </div>
                                <div class="user-progress d-flex align-items-center gap-2">
                                    <h6 class="fw-normal mb-0">+270.69</h6>
                                    <span class="text-body-secondary">USD</span>
                                </div>
                            </div>
                        </li>
                        <li class="d-flex align-items-center mb-6">
                            <div class="avatar flex-shrink-0 me-3">
                                <img src="../assets/img/icons/unicons/chart.png" alt="User" class="rounded" />
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                    <small class="d-block">Transfer</small>
                                    <h6 class="fw-normal mb-0">Refund</h6>
                                </div>
                                <div class="user-progress d-flex align-items-center gap-2">
                                    <h6 class="fw-normal mb-0">+637.91</h6>
                                    <span class="text-body-secondary">USD</span>
                                </div>
                            </div>
                        </li>
                        <li class="d-flex align-items-center mb-6">
                            <div class="avatar flex-shrink-0 me-3">
                                <img src="../assets/img/icons/unicons/cc-primary.png" alt="User" class="rounded" />
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                    <small class="d-block">Credit Card</small>
                                    <h6 class="fw-normal mb-0">Ordered Food</h6>
                                </div>
                                <div class="user-progress d-flex align-items-center gap-2">
                                    <h6 class="fw-normal mb-0">-838.71</h6>
                                    <span class="text-body-secondary">USD</span>
                                </div>
                            </div>
                        </li>
                        <li class="d-flex align-items-center mb-6">
                            <div class="avatar flex-shrink-0 me-3">
                                <img src="../assets/img/icons/unicons/wallet.png" alt="User" class="rounded" />
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                    <small class="d-block">Wallet</small>
                                    <h6 class="fw-normal mb-0">Starbucks</h6>
                                </div>
                                <div class="user-progress d-flex align-items-center gap-2">
                                    <h6 class="fw-normal mb-0">+203.33</h6>
                                    <span class="text-body-secondary">USD</span>
                                </div>
                            </div>
                        </li>
                        <li class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <img src="../assets/img/icons/unicons/cc-warning.png" alt="User" class="rounded" />
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                    <small class="d-block">Mastercard</small>
                                    <h6 class="fw-normal mb-0">Ordered Food</h6>
                                </div>
                                <div class="user-progress d-flex align-items-center gap-2">
                                    <h6 class="fw-normal mb-0">-92.45</h6>
                                    <span class="text-body-secondary">USD</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @php
        // Same social-card-mini / social-icon-mini system already used by
        // the Ads "Connect Account" modal (admin/ads/dashboard.blade.php)
        // and the Posts dashboard's platform badges - reused here instead
        // of inventing a new style, so every "connect a platform" surface
        // in the app looks and behaves the same way.
        $connectPlatforms = [
            'facebook' => ['label' => 'Facebook', 'icon' => 'bxl-facebook', 'class' => 'facebook', 'route' => 'admin.social-accounts.redirect', 'tag' => 'Posting + Ads + Messaging'],
            'google'   => ['label' => 'Google / YouTube', 'icon' => 'bxl-google', 'class' => 'google', 'route' => 'admin.social-accounts.redirect', 'tag' => 'Posting + Ads'],
            'linkedin' => ['label' => 'LinkedIn', 'icon' => 'bxl-linkedin', 'class' => 'linkedin', 'route' => 'admin.social-accounts.redirect', 'tag' => 'Posting + Ads'],
            'tiktok'   => ['label' => 'TikTok', 'icon' => 'bxl-tiktok', 'class' => 'tiktok', 'route' => 'admin.social-accounts.redirect', 'tag' => 'Posting'],
            'instagram'=> ['label' => 'Instagram', 'icon' => 'bxl-instagram', 'class' => 'instagram', 'route' => 'admin.post-accounts.instagram.redirect', 'tag' => 'Posting'],
            'x'        => ['label' => 'X', 'icon' => 'bxl-twitter', 'class' => 'twitter', 'route' => 'admin.post-accounts.x.redirect', 'tag' => 'Posting'],
            'threads'  => ['label' => 'Threads', 'icon' => 'bx-at', 'class' => 'threads', 'route' => 'admin.post-accounts.threads.redirect', 'tag' => 'Posting'],
            'pinterest'=> ['label' => 'Pinterest', 'icon' => 'bx-share-alt', 'class' => 'pinterest', 'route' => 'admin.post-accounts.pinterest.redirect', 'tag' => 'Posting'],
        ];
    @endphp

    {{-- Connect Social Media Modal - the four platforms whose OAuth model
         supports it get one combined redirect for posting + ads + messaging
         consent (see SocialAuthService); the rest use their existing
         posting-only redirect. Platforms that need manual credential entry
         instead of an OAuth redirect (WhatsApp, Telegram, Discord, Slack,
         LINE, Teams, Matrix, Zalo, Google Chat) are managed from
         Messaging > Channels instead of duplicating those forms here. --}}
    <div class="modal fade" id="connectSocialModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg social-modal">
                <div class="modal-header border-0 pb-0 mt-0 pt-0">
                    <div>
                        <h4 class="mb-1 font-weight-bold mb-0 mt-0">Connect Social Media</h4>
                        <small class="text-muted">Choose a platform to authorize - already-connected accounts are marked below.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-4">
                    <div class="row">
                        @foreach ($connectPlatforms as $platform => $meta)
                            @php $isConnected = in_array($platform, $connectedPlatforms); @endphp
                            <div class="col-6 col-md-3 mb-3">
                                <div class="social-card-mini">
                                    <a href="{{ $isConnected ? route('admin.posts.create') : route($meta['route'], $meta['route'] === 'admin.social-accounts.redirect' ? ['platform' => $platform] : []) }}">
                                        <div class="social-icon-mini {{ $meta['class'] }}">
                                            <i class="bx {{ $meta['icon'] }}"></i>
                                        </div>
                                        <h6 class="mt-2 mb-1">{{ $meta['label'] }}</h6>
                                        <small class="text-muted d-block mb-1">{{ $meta['tag'] }}</small>
                                        @if ($isConnected)
                                            <small class="connected-text"><i class="bx bx-check-circle"></i> Connected</small>
                                        @else
                                            <small class="disconnected-text">Connect</small>
                                        @endif
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <p class="text-body-secondary small mb-0 mt-2">
                        Need WhatsApp, Telegram, Discord, Slack, or another messaging channel?
                        <a href="{{ route('admin.chats.channels') }}">Manage channels</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endsection

    @push('scripts')
        <script src="{{ asset('assets/js/dashboards-analytics.js') }}"></script>
    @endpush
