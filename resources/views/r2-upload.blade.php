<!DOCTYPE html>
<html>

<head>
    <title>Cloudflare R2 Upload Test</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<div class="container mt-5">

    <div class="card">

        <div class="card-header">
            <h3>Cloudflare R2 Upload Test</h3>
        </div>

        <div class="card-body">

            @if(session('success'))

                <div class="alert alert-success">

                    {{ session('success') }}

                </div>

                <div class="mb-3">
                    <strong>Path:</strong><br>

                    {{ session('path') }}
                </div>

                <div class="mb-3">
                    <strong>URL:</strong><br>

                    <a href="{{ session('url') }}" target="_blank">
                        {{ session('url') }}
                    </a>
                </div>

                <img src="{{ session('url') }}"
                     class="img-fluid"
                     style="max-width:400px">

            @endif

            <form method="POST"
                  action="{{ route('r2.upload') }}"
                  enctype="multipart/form-data">

                @csrf

                <div class="mb-3">

                    <label>Select Image</label>

                    <input
                            type="file"
                            class="form-control"
                            name="image"
                            required>

                    @error('image')

                    <div class="text-danger mt-2">
                        {{ $message }}
                    </div>

                    @enderror

                </div>

                <button class="btn btn-primary">

                    Upload Image

                </button>

            </form>

        </div>

    </div>

</div>

</body>
</html>