<?php

// FAQs for marketing pages that don't carry their own in
// marketing-products / marketing-solutions. Strings are English
// translation keys (lang/ar.json holds the Arabic). The page renders them
// visibly and MarketingSeo::faqs() emits the same items as FAQPage
// JSON-LD, so only add answers that are true for the product today.
// 'link' is optional and only rendered on the page.
return [
    'contact' => [
        ['question' => 'How do I contact support?', 'answer' => 'Sign in and open a support ticket to keep your request and replies in one place. You can also contact us by email.'],
        ['question' => 'Where can I compare subscription plans?', 'answer' => 'Visit our pricing page to compare features and monthly or yearly billing.', 'link' => ['route' => 'pricing', 'label' => 'Compare plans']],
        ['question' => 'What should I include in a support request?', 'answer' => 'Describe what you were trying to do, what happened and the affected channel. Include a screenshot if helpful, and keep passwords and payment details private.'],
        // TODO(content): add answered questions here to deepen the thin /ar/contact page.
    ],
];
