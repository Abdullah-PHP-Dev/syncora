<?php

namespace App\Support\Ads;

use Illuminate\Support\Facades\Lang;

/**
 * Business goals for the Facebook campaign builder, mapped to Meta's
 * outcome-based objectives (ODAX). For each goal: the objective, the
 * conversion locations ("destinations") it supports, and per destination
 * the optimization goals / billing event / promoted_object fields Meta
 * accepts, plus what the account needs (Page, Pixel, app, lead form).
 *
 * Kept to combinations the Marketing API documents for these objectives;
 * anything else (catalog sales, store traffic, ...) is managed in Ads
 * Manager. The guidance text explains choices - it never promises results.
 */
class MetaGoals
{
    public const CONVERSION_EVENTS = [
        'PURCHASE' => 'Purchase',
        'ADD_TO_CART' => 'Add to cart',
        'INITIATE_CHECKOUT' => 'Initiate checkout',
        'ADD_PAYMENT_INFO' => 'Add payment info',
        'LEAD' => 'Lead',
        'COMPLETE_REGISTRATION' => 'Complete registration',
        'CONTACT' => 'Contact',
        'SUBSCRIBE' => 'Subscribe',
        'START_TRIAL' => 'Start trial',
        'SCHEDULE' => 'Schedule',
        'CONTENT_VIEW' => 'View content',
        'SEARCH' => 'Search',
    ];

    public static function all(): array
    {
        return [
            'awareness' => [
                'label' => 'Brand awareness & reach',
                'icon' => 'bx-broadcast',
                'objective' => 'OUTCOME_AWARENESS',
                'summary' => 'Show your ads to as many relevant people as possible and stay top of mind.',
                'guidance' => 'Best when you want people to know your business. Meta optimizes for reach or ad recall, not clicks - expect fewer website visits than a Traffic campaign.',
                'destinations' => [
                    'NONE' => [
                        'label' => 'No destination needed',
                        'optimization_goals' => [
                            'REACH' => 'Reach (show to the most people)',
                            'IMPRESSIONS' => 'Impressions (show as often as possible)',
                            'AD_RECALL_LIFT' => 'Ad recall lift (people likely to remember the ad)',
                            'THRUPLAY' => 'ThruPlay (video views - video ads only)',
                        ],
                        'billing_event' => 'IMPRESSIONS',
                        'promoted_object' => ['page_id'],
                        'ctas' => ['LEARN_MORE', 'SHOP_NOW', 'CONTACT_US', 'SIGN_UP', 'NO_BUTTON'],
                    ],
                ],
            ],
            'traffic' => [
                'label' => 'Website traffic',
                'icon' => 'bx-pointer',
                'objective' => 'OUTCOME_TRAFFIC',
                'summary' => 'Send people to your website or landing page.',
                'guidance' => '"Landing page views" counts people who waited for your page to load (needs the Meta Pixel on your site to be measured well); "Link clicks" counts every click.',
                'destinations' => [
                    'WEBSITE' => [
                        'label' => 'Website',
                        'optimization_goals' => [
                            'LANDING_PAGE_VIEWS' => 'Landing page views (recommended)',
                            'LINK_CLICKS' => 'Link clicks',
                            'REACH' => 'Daily unique reach',
                            'IMPRESSIONS' => 'Impressions',
                        ],
                        'billing_event' => 'IMPRESSIONS',
                        'promoted_object' => [],
                        'needs_link' => true,
                        'ctas' => ['LEARN_MORE', 'SHOP_NOW', 'SIGN_UP', 'BOOK_NOW', 'CONTACT_US', 'GET_OFFER', 'ORDER_NOW', 'SUBSCRIBE', 'APPLY_NOW', 'DOWNLOAD'],
                    ],
                ],
            ],
            'leads' => [
                'label' => 'Leads',
                'icon' => 'bx-id-card',
                'objective' => 'OUTCOME_LEADS',
                'summary' => 'Collect contact details from people interested in your business.',
                'guidance' => 'An Instant form collects details inside Facebook/Instagram (no website needed - you create the form on your Page). A website goal needs the Meta Pixel sending a Lead (or similar) event.',
                'destinations' => [
                    'ON_AD' => [
                        'label' => 'Instant form',
                        'optimization_goals' => [
                            'LEAD_GENERATION' => 'Leads',
                            'QUALITY_LEAD' => 'Conversion leads (optimizes for leads more likely to convert)',
                        ],
                        'billing_event' => 'IMPRESSIONS',
                        'promoted_object' => ['page_id'],
                        'needs_lead_form' => true,
                        'ctas' => ['SIGN_UP', 'LEARN_MORE', 'APPLY_NOW', 'GET_QUOTE', 'SUBSCRIBE', 'DOWNLOAD', 'BOOK_NOW'],
                    ],
                    'WEBSITE' => [
                        'label' => 'Website',
                        'optimization_goals' => ['OFFSITE_CONVERSIONS' => 'Conversions (Pixel event)'],
                        'billing_event' => 'IMPRESSIONS',
                        'promoted_object' => ['pixel_id', 'custom_event_type'],
                        'needs_pixel' => true,
                        'needs_link' => true,
                        'default_event' => 'LEAD',
                        'ctas' => ['SIGN_UP', 'LEARN_MORE', 'APPLY_NOW', 'GET_QUOTE', 'CONTACT_US', 'BOOK_NOW', 'SUBSCRIBE'],
                    ],
                ],
            ],
            'sales' => [
                'label' => 'Sales & conversions',
                'icon' => 'bx-cart',
                'objective' => 'OUTCOME_SALES',
                'summary' => 'Find people likely to buy or complete an action on your website.',
                'guidance' => 'Needs the Meta Pixel (dataset) on your website sending the event you choose (eg. Purchase). Meta learns from those events - choose the event that actually happens most often if purchases are rare. Product catalog ads are set up in Commerce Manager / Ads Manager.',
                'destinations' => [
                    'WEBSITE' => [
                        'label' => 'Website',
                        'optimization_goals' => ['OFFSITE_CONVERSIONS' => 'Conversions (Pixel event)'],
                        'billing_event' => 'IMPRESSIONS',
                        'promoted_object' => ['pixel_id', 'custom_event_type'],
                        'needs_pixel' => true,
                        'needs_link' => true,
                        'default_event' => 'PURCHASE',
                        'ctas' => ['SHOP_NOW', 'BUY_NOW', 'ORDER_NOW', 'GET_OFFER', 'LEARN_MORE', 'SIGN_UP', 'BOOK_NOW'],
                    ],
                ],
            ],
            'messages' => [
                'label' => 'Messages & inquiries',
                'icon' => 'bx-message-rounded-dots',
                'objective' => 'OUTCOME_ENGAGEMENT',
                'summary' => 'Start conversations with customers on Messenger, WhatsApp or Instagram.',
                'guidance' => 'People tap the ad to message your Page. WhatsApp needs a WhatsApp Business number connected to the Page; Instagram Direct needs the Instagram account linked to it.',
                'destinations' => [
                    'MESSENGER' => [
                        'label' => 'Messenger',
                        'optimization_goals' => ['CONVERSATIONS' => 'Conversations'],
                        'billing_event' => 'IMPRESSIONS',
                        'promoted_object' => ['page_id'],
                        'ctas' => ['MESSAGE_PAGE'],
                        'message_app' => 'MESSENGER',
                    ],
                    'WHATSAPP' => [
                        'label' => 'WhatsApp',
                        'optimization_goals' => ['CONVERSATIONS' => 'Conversations'],
                        'billing_event' => 'IMPRESSIONS',
                        'promoted_object' => ['page_id'],
                        'ctas' => ['WHATSAPP_MESSAGE'],
                        'message_app' => 'WHATSAPP',
                    ],
                    'INSTAGRAM_DIRECT' => [
                        'label' => 'Instagram Direct',
                        'optimization_goals' => ['CONVERSATIONS' => 'Conversations'],
                        'billing_event' => 'IMPRESSIONS',
                        'promoted_object' => ['page_id'],
                        'ctas' => ['INSTAGRAM_MESSAGE'],
                        'message_app' => 'INSTAGRAM_DIRECT',
                        'needs_instagram' => true,
                    ],
                ],
            ],
            'engagement' => [
                'label' => 'Post engagement',
                'icon' => 'bx-like',
                'objective' => 'OUTCOME_ENGAGEMENT',
                'summary' => 'Get more likes, comments and shares on your ad.',
                'guidance' => 'Good for social proof. Engagement does not mean sales - use Sales or Leads when those are what you need.',
                'destinations' => [
                    'ON_POST' => [
                        'label' => 'On your ad',
                        'optimization_goals' => [
                            'POST_ENGAGEMENT' => 'Post engagement',
                            'IMPRESSIONS' => 'Impressions',
                            'REACH' => 'Daily unique reach',
                        ],
                        'billing_event' => 'IMPRESSIONS',
                        'promoted_object' => ['page_id'],
                        'ctas' => ['LEARN_MORE', 'SHOP_NOW', 'NO_BUTTON'],
                    ],
                ],
            ],
            'app' => [
                'label' => 'App promotion',
                'icon' => 'bx-mobile-alt',
                'objective' => 'OUTCOME_APP_PROMOTION',
                'summary' => 'Get people to install your mobile app.',
                'guidance' => 'Your app must be registered as a Meta app and you need its App Store / Google Play URL. Installs are measured with the Meta SDK or a mobile measurement partner.',
                'destinations' => [
                    'APP' => [
                        'label' => 'App store',
                        'optimization_goals' => ['APP_INSTALLS' => 'App installs'],
                        'billing_event' => 'IMPRESSIONS',
                        'promoted_object' => ['application_id', 'object_store_url'],
                        'needs_app' => true,
                        'needs_link' => true,
                        'ctas' => ['INSTALL_MOBILE_APP', 'DOWNLOAD', 'USE_APP', 'PLAY_GAME'],
                    ],
                ],
            ],
        ];
    }

    public static function find(string $goal): ?array
    {
        return self::all()[$goal] ?? null;
    }

    /** The goal an existing campaign's objective belongs to (messages vs engagement share OUTCOME_ENGAGEMENT). */
    public static function forObjective(string $objective): array
    {
        return array_keys(array_filter(self::all(), fn ($g) => $g['objective'] === $objective));
    }

    /** destination rules, or null when the goal doesn't offer that destination. */
    public static function destination(string $goal, string $destination): ?array
    {
        return self::find($goal)['destinations'][$destination] ?? null;
    }

    /**
     * all() with the text in the current locale (lang/{en,ar}/ads_builder.php
     * goals.*) - for the wizard. Codes and rules are unchanged; anything
     * without a translation keeps the English text above.
     */
    public static function localized(): array
    {
        $tr = fn (string $key, string $fallback) => Lang::has("ads_builder.goals.{$key}") ? __("ads_builder.goals.{$key}") : $fallback;

        $goals = [];
        foreach (self::all() as $goal => $g) {
            foreach (['label', 'summary', 'guidance'] as $field) {
                $g[$field] = $tr("{$goal}.{$field}", $g[$field]);
            }
            foreach ($g['destinations'] as $code => $d) {
                $d['label'] = $tr("destinations.{$code}", $d['label']);
                foreach ($d['optimization_goals'] as $opt => $label) {
                    $d['optimization_goals'][$opt] = Lang::has("ads_builder.goals.optimization_by_goal.{$goal}.{$opt}")
                        ? __("ads_builder.goals.optimization_by_goal.{$goal}.{$opt}")
                        : $tr("optimization.{$opt}", $label);
                }
                $g['destinations'][$code] = $d;
            }
            $goals[$goal] = $g;
        }

        return $goals;
    }

    /** CONVERSION_EVENTS in the current locale. */
    public static function localizedEvents(): array
    {
        $events = [];
        foreach (self::CONVERSION_EVENTS as $code => $label) {
            $events[$code] = Lang::has("ads_builder.goals.events.{$code}") ? __("ads_builder.goals.events.{$code}") : $label;
        }

        return $events;
    }

    /** Meta's destination_type for the ad set (null = don't send one). */
    public static function destinationType(string $destination): ?string
    {
        return $destination === 'NONE' ? null : $destination;
    }
}
