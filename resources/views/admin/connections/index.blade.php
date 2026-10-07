@extends('layouts.app')

@section('title', __('admin.connections.title'))

@section('content')
    {{-- Connection Hub (docs/connection-hub-design.md §10). The OAuth
         callbacks send the user back here with a success/error flash. --}}
    <connection-hub
        :hub='@json($hub)'
        :urls='@json($urls)'
        :wizard='@json($wizard)'
        :flash='@json(array_filter(['success' => session('success'), 'error' => session('error')]))'
    ></connection-hub>
@endsection
