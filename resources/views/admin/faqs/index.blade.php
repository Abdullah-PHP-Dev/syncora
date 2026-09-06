@extends('layouts.app')

@section('title', 'System FAQ Management')

@section('content')

    <faq-manager
        :initial-faqs='@json($faqs)'
        :initial-categories='@json($categories)'
        fetch-url="{{ route('admin.faqs.index') }}"
        store-url="{{ route('admin.faqs.store') }}"
        update-url-template="{{ route('admin.faqs.update', ['faq' => 'FAQ_ID']) }}"
        category-store-url="{{ route('admin.faqs.categories.store') }}"
        heading="System FAQ Management"
        subtitle="Platform-level FAQs shown read-only in every seller's Help Center - billing, account setup, cross-platform posting, troubleshooting."
        icon="bx-help-circle"
        gradient="linear-gradient(135deg, #0ea5e9 0%, #6366f1 55%, #8b5cf6 100%)"
        empty-text="No FAQs yet - create the first one above."
        question-placeholder="How do I connect my Facebook Page?"
        answer-placeholder="Go to Manage Channels and click Connect Facebook."
    ></faq-manager>

@endsection
