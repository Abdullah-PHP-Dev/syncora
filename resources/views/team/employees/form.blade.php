@extends('layouts.app')
@section('title', $employee->exists ? __('Edit employee') : __('Create employee'))
@section('content')
<h3>{{ $employee->exists ? __('Edit employee') : __('Create employee') }}</h3>
<form class="card" method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}">
@csrf @if($employee->exists) @method('PUT') @endif
<div class="card-body row g-4">
@foreach(['name' => 'Full name', 'email' => 'Email'] as $key => $label)
<div class="col-md-6"><label class="form-label" for="{{ $key }}">{{ __($label) }}</label><input class="form-control" id="{{ $key }}" name="{{ $key }}" type="{{ $key === 'email' ? 'email' : 'text' }}" value="{{ old($key, $employee->$key) }}" required maxlength="255"></div>
@endforeach
<div class="col-md-6"><label class="form-label" for="role">{{ __('Role') }}</label><select class="form-select" id="role" name="role">
@foreach(['customer_support' => 'Customer support', 'admin' => 'Team administrator'] as $role => $label)<option value="{{ $role }}" @selected(old('role', $employee->exists && $employee->hasRole('admin') ? 'admin' : 'customer_support') === $role)>{{ __($label) }}</option>@endforeach
</select></div>
<div class="col-md-6"><label class="form-label" for="is_active">{{ __('Status') }}</label><select class="form-select" id="is_active" name="is_active"><option value="1" @selected(old('is_active', $employee->is_active ?? true))>{{ __('Active') }}</option><option value="0" @selected(!old('is_active', $employee->is_active ?? true))>{{ __('Disabled') }}</option></select></div>
<div class="col-md-6"><label class="form-label" for="password">{{ __('Password') }}</label><input class="form-control" type="password" id="password" name="password" minlength="12" autocomplete="new-password" @required(!$employee->exists)><small>{{ __('Use at least 12 characters. Leave blank to keep the current password when editing.') }}</small></div>
<div class="col-md-6"><label class="form-label" for="password_confirmation">{{ __('Confirm password') }}</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"></div>
<div><button class="btn btn-primary">{{ __('Save employee') }}</button> <a class="btn btn-outline-secondary" href="{{ route('employees.index') }}">{{ __('Cancel') }}</a></div>
</div></form>
@endsection
