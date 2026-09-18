@extends('layouts.app')

@section('title', 'Support Tickets')

@section('content')

    <tickets-list
        :initial-tickets='@json($tickets)'
        :is-admin='@json($isAdmin)'
        fetch-url="{{ route('admin.tickets.index') }}"
        create-url="{{ route('admin.tickets.create') }}"
        help-center-url="{{ route('admin.help-center.index') }}"
        show-url-template="{{ route('admin.tickets.show', ['ticket' => 'TICKET_ID']) }}"
    ></tickets-list>

@endsection
