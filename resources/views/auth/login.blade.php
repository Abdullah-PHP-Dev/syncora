@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">

                <div class="card px-sm-6 px-0">
                    <div class="card-body">

                        <!-- Logo -->
                        <div class="app-brand justify-content-center mb-4">
                            <a href="{{ url('/') }}" class="app-brand-link gap-2">
                                <span class="app-brand-text demo text-heading fw-bold">
                                    {{ config('app.name') }}
                                </span>
                            </a>
                        </div>

                        <h4 class="mb-1">Welcome 👋</h4>
                        <p class="mb-4">Sign in to your account</p>

                        <form method="POST" action="{{ route('login') }}">
                            @csrf

                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <input type="checkbox" name="remember">
                                    <label>Remember me</label>
                                </div>

                                <a href="{{ route('password.request') }}">
                                    Forgot password?
                                </a>
                            </div>

                            <button class="btn btn-primary w-100">Login</button>
                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection