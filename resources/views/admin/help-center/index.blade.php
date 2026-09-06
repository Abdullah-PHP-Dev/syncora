@extends('layouts.app')

@section('title', 'Help Center')

@section('content')

    <help-center-browser
        :initial-faqs='@json($faqs)'
        :initial-categories='@json($categories)'
        fetch-url="{{ route('admin.help-center.index') }}"
        tickets-create-url="{{ route('admin.tickets.create') }}"
    ></help-center-browser>

@endsection
