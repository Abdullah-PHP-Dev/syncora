@extends('layouts.auth-modern')

@section('content')

    <div class="container d-flex align-items-center justify-content-center min-vh-100">

        <div class="row w-100 shadow-lg rounded-4 overflow-hidden" style="max-width: 1000px;">

            <!-- LEFT SIDE -->
            <div class="col-md-6 p-5 text-white d-none d-md-flex flex-column justify-content-center"
                 style="background: rgba(0,0,0,0.15);">

                <div class="logo mb-4">
                    <a href="/" class="text-decoration-none">
                        <div class="logo text-white">Syncro</div>
                    </a>
                </div>

                <h2 class="fw-bold">Start your journey</h2>

                <p class="text-white-50 mt-2">
                    Build your marketplace, manage ads and grow your business globally.
                </p>

            </div>

            <!-- RIGHT SIDE -->
            <div class="col-md-6 bg-white p-5">

                <div class="text-center mb-4 d-md-none">
                    <div class="logo">🐝 Social Bee</div>
                </div>

                <h3 class="fw-bold mb-4">Create Account</h3>

                <form method="POST" action="{{ route('register') }}">

                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Enter name">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Enter email">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Create password">
                    </div>

                    <button class="btn btn-success w-100">
                        Create Account
                    </button>

                </form>

                <div class="text-center mt-3">
                    <small>
                        Already have account?
                        <a href="/login">Login</a>
                    </small>
                </div>

            </div>

        </div>

    </div>

@endsection