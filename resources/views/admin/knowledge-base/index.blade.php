@extends('layouts.app')

@section('title', 'Knowledge Base')

@section('content')

    <faq-manager
        :initial-faqs='@json($faqs)'
        :initial-categories='@json($categories)'
        fetch-url="{{ route('admin.knowledge-base.index') }}"
        store-url="{{ route('admin.knowledge-base.store') }}"
        update-url-template="{{ route('admin.knowledge-base.update', ['faq' => 'FAQ_ID']) }}"
        category-store-url="{{ route('admin.knowledge-base.categories.store') }}"
        heading="Knowledge Base"
        subtitle="Your own business FAQs - hours, delivery, pricing, policies. Only published entries here will be usable by the AI Copilot to answer your customers automatically."
        icon="bx-book-content"
        gradient="linear-gradient(135deg, #10b981 0%, #0ea5e9 55%, #6366f1 100%)"
        empty-text="No FAQs yet. Add your business hours, delivery policy, or pricing here so the AI Copilot can answer your customers with it."
        question-placeholder="Do you deliver to Jeddah?"
        answer-placeholder="Yes, we deliver to Jeddah within 2-3 business days."
    ></faq-manager>

@endsection
