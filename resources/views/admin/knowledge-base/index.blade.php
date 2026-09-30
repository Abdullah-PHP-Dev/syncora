@extends('layouts.app')

@section('title', 'Knowledge Base')

@section('content')

    @php
        // route()-generated so every URL carries the locale prefix.
        $kbUrls = [
            'index'         => route('admin.knowledge-base.index'),
            'store'         => route('admin.knowledge-base.store'),
            'update'        => route('admin.knowledge-base.update', ['faq' => 'FAQ_ID']),
            'bulk'          => route('admin.knowledge-base.bulk'),
            'improve'       => route('admin.knowledge-base.improve'),
            'import'        => route('admin.knowledge-base.import'),
            'export'        => route('admin.knowledge-base.export'),
            'categoryStore' => route('admin.knowledge-base.categories.store'),
            'knowledgeGaps' => route('admin.ai-copilot.knowledge-gaps.index'),
        ];
    @endphp

    <knowledge-base-manager
        :initial-faqs='@json($faqs)'
        :initial-categories='@json($categories)'
        :initial-stats='@json($stats)'
        :urls='@json($kbUrls)'
    ></knowledge-base-manager>

@endsection
