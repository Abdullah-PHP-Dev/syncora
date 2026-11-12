@extends('layouts.app')

@section('title', $title)

@section('content')
    @php
        // route()-generated so every URL carries the locale prefix. ID is
        // replaced client-side (see resources/js/components/crm/api.js).
        $crmUrls = [
            'pages' => [
                'overview' => route('admin.crm.overview'),
                'contacts' => route('admin.crm.contacts.index'),
                'contact' => route('admin.crm.contacts.show', ['contact' => 0]),
                'companies' => route('admin.crm.companies.index'),
                'company' => route('admin.crm.companies.show', ['company' => 0]),
                'leads' => route('admin.crm.leads.index'),
                'lead' => route('admin.crm.leads.show', ['lead' => 0]),
                'deals' => route('admin.crm.deals.index'),
                'deal' => route('admin.crm.deals.show', ['deal' => 0]),
                'activities' => route('admin.crm.activities.index'),
                'settings' => route('admin.crm.settings.index'),
                'insights' => route('admin.crm.insights.index'),
                'inbox' => route('admin.chats.dashboard'),
                'team' => route('admin.seller-team.index'),
            ],
            'api' => \Illuminate\Support\Str::beforeLast(route('admin.crm.api.overview'), '/overview'),
            'export' => route('admin.crm.contacts.export'),
            'reportsExport' => route('admin.crm.reports.export'),
            'ai' => route('admin.crm.ai.brief', ['contact' => 0]),
            'aiAskContact' => route('admin.crm.ai.ask-contact', ['contact' => 0]),
            'aiQualify' => route('admin.crm.ai.qualify', ['lead' => 0]),
            'aiAsk' => route('admin.crm.ai.ask'),
        ];
    @endphp

    <crm-app
        page="{{ $page }}"
        :props='@json($props)'
        :urls='@json($crmUrls)'
        :messages='@json(__('crm'))'
        locale="{{ app()->getLocale() }}"
        user-name="{{ optional(request()->attributes->get('workspace_actor') ?? auth()->user())->name }}"
    ></crm-app>
@endsection
