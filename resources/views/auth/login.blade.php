@extends('layouts.auth-modern')

@section('content')

    <div class="container d-flex align-items-center justify-content-center min-vh-100">

        <div class="row w-100 shadow-lg rounded-4 overflow-hidden" style="max-width: 1000px;">

            <!-- LEFT SIDE -->
            <div class="col-md-6 p-5 left-panel d-none d-md-flex flex-column justify-content-center"
                 style="background: rgba(0,0,0,0.15);">

                <div class="mb-4">
                    <a href="/" class="text-decoration-none">
                        <div class="logo text-white">Syncro</div>
                    </a>

                </div>

                <h2 class="fw-bold">Welcome back!</h2>

                <p class="text-white-50 mt-2">
                    Manage your marketplace, ads, CRM and growth in one powerful platform.
                </p>

                <ul class="mt-3 text-white-50">
                    <li>✔ Ads Management</li>
                    <li>✔ CRM System</li>
                    <li>✔ Analytics Dashboard</li>
                </ul>

            </div>

            <!-- RIGHT SIDE -->
            <div class="col-md-6 bg-white p-5">

                <!-- LOGO (mobile) -->
                <div class="text-center mb-4 d-md-none">
                    <div class="logo">🐝 Social Bee</div>
                </div>

                <h3 class="fw-bold mb-4">Login</h3>

                <form method="POST" action="{{ route('login') }}">

                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Enter email">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter password">
                    </div>

                    <button class="btn btn-primary w-100">
                        Sign In
                    </button>

                </form>

                <div class="text-center mt-3">
                    <small>
                        Don't have an account?
                        <a href="/register">Create account</a>
                    </small>
                </div>

            </div>

        </div>

    </div>

@endsection