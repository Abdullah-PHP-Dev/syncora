@extends('layouts.app')

@section('title', 'New Support Ticket')

@section('content')

    <ticket-create-form
        store-url="{{ route('admin.tickets.store') }}"
        index-url="{{ route('admin.tickets.index') }}"
        help-center-url="{{ route('admin.help-center.index') }}"
    ></ticket-create-form>

@endsection
