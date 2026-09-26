@extends('layouts.app')

@section('title', 'New Support Ticket')

@section('content')

    <ticket-create-form
        store-url="{{ route('admin.tickets.store') }}"
        index-url="{{ route('admin.tickets.index') }}"
        help-center-url="{{ route('admin.help-center.index') }}"
        :initial-subject='@json($initialSubject)'
        :initial-body='@json($initialBody)'
    ></ticket-create-form>

@endsection
