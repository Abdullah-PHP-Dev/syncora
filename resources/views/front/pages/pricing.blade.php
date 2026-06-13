@extends('layouts.front')

@section('content')

    <div class="container py-5 text-center">

        <h1 class="fw-bold">Pricing Plans</h1>
        <p class="text-muted">Choose a plan that fits your business</p>

        <div class="row mt-5">

            <div class="col-md-4">
                <div class="card shadow-sm p-4">
                    <h4>Free</h4>
                    <h2>$0</h2>
                    <p class="text-muted">Basic ad posting</p>

                    <button class="btn btn-outline-primary w-100">
                        Start Free
                    </button>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow p-4 border-primary">
                    <h4>Pro</h4>
                    <h2>$19</h2>
                    <p class="text-muted">Boost ads + CRM tools</p>

                    <button class="btn btn-primary w-100">
                        Buy Now
                    </button>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm p-4">
                    <h4>Enterprise</h4>
                    <h2>$49</h2>
                    <p class="text-muted">Full platform access</p>

                    <button class="btn btn-dark w-100">
                        Contact Sales
                    </button>
                </div>
            </div>

        </div>

    </div>

@endsection