@extends('layouts.app')

@section('title', 'Edit Email Template')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('admin.email.templates.index') }}" class="small text-muted"><i class="bx bx-arrow-back"></i> Templates</a>
        <h4 class="mb-0">Edit Template</h4>
    </div>
</div>

<form action="{{ route('admin.email.templates.update', $template) }}" method="POST">
    @csrf
    @method('PATCH')
    @include('admin.email.templates._form', ['template' => $template])
</form>
@endsection
