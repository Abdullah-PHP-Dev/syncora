@extends('layouts.app')

@section('title', 'Ticket ' . $ticket->ticket_number)

@section('content')

    <ticket-thread
        :initial-ticket='@json($ticket)'
        :initial-messages='@json($messages)'
        :is-admin='@json($isAdmin)'
        store-message-url="{{ route('admin.tickets.messages.store', $ticket) }}"
        status-update-url="{{ route('admin.tickets.status', $ticket) }}"
        index-url="{{ route('admin.tickets.index') }}"
    ></ticket-thread>

@endsection
