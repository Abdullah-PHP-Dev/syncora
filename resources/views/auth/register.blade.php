@extends('layouts.auth')

@section('title', 'Register')

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

                        <h4 class="mb-1">Create Account 🚀</h4>
                        <p class="mb-4">Start your journey with us</p>

                        <form method="POST" action="{{ route('register') }}">
                            @csrf

                            <!-- Name -->
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>

                            <!-- Email -->
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>

                            <!-- Password -->
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="password_confirmation" class="form-control" required>
                            </div>

                            <!-- Terms -->
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" required>
                                <label class="form-check-label">
                                    I agree to terms & conditions
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                Create Account
                            </button>
                        </form>

                        <p class="text-center mt-3">
                            Already have an account?
                            <a href="{{ route('login') }}">Login</a>
                        </p>

                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection