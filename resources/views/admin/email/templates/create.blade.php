@extends('layouts.app')

@section('title', 'New Email Template')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('admin.email.templates.index') }}" class="small text-muted"><i class="bx bx-arrow-back"></i> Templates</a>
        <h4 class="mb-0">New Template</h4>
    </div>
</div>

<form action="{{ route('admin.email.templates.store') }}" method="POST">
    @csrf
    @include('admin.email.templates._form', ['template' => null])
</form>
@endsection
