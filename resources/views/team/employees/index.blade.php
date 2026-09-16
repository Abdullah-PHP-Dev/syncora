@extends('layouts.app')
@section('title', __('Employees'))
@section('content')
<div class="d-flex justify-content-between mb-5"><h3>{{ __('Employees') }}</h3><a class="btn btn-primary" href="{{ route('employees.create') }}">{{ __('Create employee') }}</a></div>
<div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Role') }}</th><th>{{ __('Status') }}</th><th>{{ __('Action') }}</th></tr></thead><tbody>
@forelse($employees as $employee)<tr><td>{{ $employee->name }}</td><td>{{ $employee->email }}</td><td>{{ $employee->hasRole('admin') ? __('Team administrator') : __('Customer support') }}</td><td>{{ $employee->is_active ? __('Active') : __('Disabled') }}</td><td><a href="{{ route('employees.edit', $employee) }}">{{ __('Edit') }}</a></td></tr>
@empty<tr><td colspan="5">{{ __('No employees yet.') }}</td></tr>@endforelse
</tbody></table></div><div class="card-body">{{ $employees->links() }}</div></div>
@endsection
