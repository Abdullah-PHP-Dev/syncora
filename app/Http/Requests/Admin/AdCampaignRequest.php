<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isPost = $this->isMethod('post');
        $platform = $this->route('platform');

        $requiredIfPost = $isPost ? ['required'] : ['sometimes'];
        $requiredIfUpdate = $isPost ? ['sometimes'] : ['required'];
       
        $validations = [];
        if ($platform === 'tiktok') {
            $validations = $this->getTikTokRules($requiredIfPost);
        } else if ($platform === 'snapchat') {
            $validations = $this->getSnapchatRules($requiredIfPost);
        } else if ($platform === 'facebook') {
            $validations = $this->getFacebookRules($requiredIfPost);
        } else if ($platform === 'google') {
            $validations = $this->getGoogleRules($requiredIfPost);
        } else if ($platform === 'youtube') {
            $validations = $this->getYoutubeRules($requiredIfPost);
        } else if ($platform === 'x') {
            $validations = $this->getXRules($requiredIfPost);
        } else if ($platform === 'linkedin') {
            $validations = $this->getLinkedinRules($requiredIfPost);
        }

        return $validations;
    }

    private function getFacebookRules(array $requiredIfPost): array
    {
        return [
            'name' => ['required'],
            'final_budget' => ['nullable'],
            'budget' => ['required'],
            'start_time' => ['required', 'date_format:Y-m-d', 'before:end_time'],
            'end_time'   => ['required', 'date_format:Y-m-d', 'after:start_time'],
            //  'brand_name' => ['required', 'string'],
            'media' => array_merge($requiredIfPost, [
                'array',
                function ($attribute, $value, $fail) {
                    if ($this->input('media_type') === 'CAROUSEL' && count($value ?? []) < 2) {
                        $fail('A carousel ad needs at least 2 images.');
                    }
                },
            ]),
            'media.*' => [
                'file',
                function ($attribute, $value, $fail) {
                    $isVideo = $this->input('media_type') === 'VIDEO';
                    $maxKb = $isVideo ? 512000 : 30720; // 500MB video / 30MB image

                    if ($value->getSize() / 1024 > $maxKb) {
                        $fail("The {$attribute} may not be greater than " . ($isVideo ? '500MB' : '30MB') . '.');
                    }

                    $allowedMimes = $isVideo
                        ? ['video/mp4', 'video/quicktime', 'video/x-m4v']
                        : ['image/jpeg', 'image/png', 'image/gif', 'image/bmp'];

                    if (!in_array($value->getMimeType(), $allowedMimes, true)) {
                        $fail("The {$attribute} must be a valid " . ($isVideo ? 'video (mp4/mov)' : 'image (jpg/png/gif/bmp)') . ' file.');
                    }
                },
            ],
            'media_type' => ['required', 'in:IMAGE,VIDEO,CAROUSEL'],
            'thumbnail' => ['nullable', 'required_if:media_type,VIDEO', 'image', 'max:8192'],
            'carousel_cards' => ['nullable', 'required_if:media_type,CAROUSEL', 'json'],
            'description' => ['required', 'string'],
            // Both optional/additive - see FacebookAdService::
            // storeCreative()'s docblock. Only the new Vue create-new.
            // blade.php builder sends these; when absent, description
            // keeps doubling as both message and link description exactly
            // as it always has for the original create.blade.php.
            'headline' => ['nullable', 'string', 'max:255'],
            'primary_text' => ['nullable', 'string', 'max:500'],
            'facebook' => ['nullable', 'required_without:instagram'],
            'instagram' => ['nullable', 'required_without:facebook'],
            'target_link'  => ['required', 'url'],
            //'ad_format' => ['required', 'in:FEED,STORIES,SEARCH_RESULTS,MARKET_PLACE'],
            'countries' => ['array', 'required'],
            'budget_mode' => ['required', 'in:daily_budget,lifetime_budget'],
            'call_to_action' => array_merge($requiredIfPost, ['in:SHOP_NOW,BOOK_TRAVEL,CONTACT_US,DONATE,DONATE_NOW,GET_DIRECTIONS,GO_LIVE,
                                INTERESTED,LEARN_MORE,LIKE_PAGE,MESSAGE_PAGE,
                                RAISE_MONEY,SAVE,SEND_TIP,VIEW_INSTAGRAM_PROFILE,INSTAGRAM_MESSAGE,
                                LOYALTY_LEARN_MORE,PURCHASE_GIFT_CARDS,PAY_TO_ACCESS,SEE_MORE,TRY_IN_CAMERA,
                                WHATSAPP_LINK,GET_IN_TOUCH,BOOK_NOW,CHECK_AVAILABILITY,
                                WHATSAPP_MESSAGE,GET_MOBILE_APP,INSTALL_MOBILE_APP,USE_MOBILE_APP,INSTALL_APP,
                                WATCH_VIDEO,WATCH_MORE,OPEN_LINK,NO_BUTTON,LISTEN_MUSIC,MOBILE_DOWNLOAD,
                                GET_OFFER,GET_OFFER_VIEW,BUY_NOW,DOWNLOAD,SIGN_UP,
                                UPDATE_APP,BET_NOW,ADD_TO_CART,SELL_NOW,GET_SHOWTIMES,LISTEN_NOW,
                                GET_EVENT_TICKETS,REMIND_ME,SEARCH_MORE,PRE_REGISTER,SWIPE_UP_PRODUCT,
                                SWIPE_UP_SHOP,
                                PLAY_GAME_ON_FACEBOOK,VISIT_WORLD,OPEN_INSTANT_APP,JOIN_GROUP,GET_PROMOTIONS,
                                SEND_UPDATES,INQUIRE_NOW,VISIT_PROFILE,CHAT_ON_WHATSAPP,EXPLORE_MORE,
                                CONFIRM,JOIN_CHANNEL,
                                MAKE_AN_APPOINTMENT,ASK_ABOUT_SERVICES,BOOK_A_CONSULTATION,GET_A_QUOTE,
                                BUY_VIA_MESSAGE,ASK_FOR_MORE_INFO,CHAT_WITH_US,VIEW_PRODUCT,VIEW_CHANNEL,
                                CALL,MISSED_CALL,CALL_ME,BUY,GET_QUOTE,SUBSCRIBE,RECORD_NOW,VOTE_NOW,
                                GIVE_FREE_RIDES,REGISTER_NOW,OPEN_MESSENGER_EXT,
                                EVENT_RSVP,CIVIC_ACTION,SEND_INVITES,REFER_FRIENDS,REQUEST_TIME,SEE_MENU,
                                SEARCH,TRY_IT,TRY_ON,LINK_CARD,DIAL_CODE,FIND_YOUR_GROUPS,START_ORDER']),
            //'account_to_use' => ['required', 'in:Twsaa,Store'],
            'optimization_goal' => array_merge($requiredIfPost, ['in:' . implode(',', [
                'LINK_CLICKS', 'LANDING_PAGE_VIEWS', 'REACH', 'IMPRESSIONS', 'AD_RECALL_LIFT',
                'POST_ENGAGEMENT', 'THRUPLAY', 'CONVERSATIONS',
                'LEAD_GENERATION', 'QUALITY_LEAD',
                'APP_INSTALLS', 'APP_INSTALLS_AND_OFFSITE_CONVERSIONS',
                'OFFSITE_CONVERSIONS',
            ])]),
            'billing_event'    => array_merge($requiredIfPost, ['in:NONE,CLICKS,IMPRESSIONS,LINK_CLICKS,OFFER_CLAIMS,PAGE_LIKES,POST_ENGAGEMENT,THRUPLAY,PURCHASE,LISTING_INTERACTION']),
            'destination_type'    => ['nullable', 'in:WEBSITE,APP,MESSENGER,WHATSAPP'],
            'bid_amount'    => array_merge($requiredIfPost),
            'pixel_id'           => ['nullable', 'string', 'max:255', 'required_if:optimization_goal,OFFSITE_CONVERSIONS'],
            'page_id'            => ['string', 'max:255', 'nullable'],
            'application_id'     => ['nullable', 'string', 'max:255', 'required_if:objective,OUTCOME_APP_PROMOTION'],
            'object_store_url'    => ['nullable', 'string', 'max:255', 'required_if:objective,OUTCOME_APP_PROMOTION'],
            'custom_event_type'     => ['nullable', 'string', 'max:255', 'required_if:optimization_goal,OFFSITE_CONVERSIONS'],
            'age_to'     => ['required'],
            'age_from'     => ['required'],
            'gender'     => ['required'],
            'languages'     => ['required', 'array'],
            'final_budget' => ['required'],
            'objective' => ['required', 'in:OUTCOME_TRAFFIC,OUTCOME_SALES,OUTCOME_ENGAGEMENT,OUTCOME_AWARENESS,OUTCOME_APP_PROMOTION,OUTCOME_LEADS'],
            // Housing/Employment/Credit/Social-issues ads are legally
            // barred from age/gender narrowing - FacebookAdService::
            // storeAdGroup()'s docblock forces the broadest range
            // server-side regardless, this just validates the enum itself.
            'special_ad_category' => ['nullable', 'in:HOUSING,EMPLOYMENT,CREDIT,ISSUES_ELECTIONS_POLITICS'],
            // Free-text, typeahead-resolved against Meta's Targeting
            // Search endpoint at submit time (FacebookAdService::
            // resolveDetailedTargeting()) rather than a fixed enum -
            // comma-separated so the form can stay a single text input.
            'detailed_targeting' => ['nullable', 'string', 'max:500'],
            'life_events' => ['nullable', 'string', 'max:500'],
            'industries' => ['nullable', 'string', 'max:500'],
            'income' => ['nullable', 'string', 'max:500'],
            'relationship_statuses' => ['nullable', 'array'],
            'relationship_statuses.*' => ['in:single,in_relationship,married,engaged,its_complicated,open_relationship,widowed,separated,divorced,civil_union,domestic_partnership'],
            'education_statuses' => ['nullable', 'array'],
            'education_statuses.*' => ['in:HIGH_SCHOOL,UNDERGRAD,ALUM,HIGH_SCHOOL_GRAD,SOME_COLLEGE,ASSOCIATE_DEGREE,IN_GRAD_SCHOOL,MASTER_DEGREE,PROFESSIONAL_DEGREE,DOCTORATE_DEGREE,UNSPECIFIED'],
            // Existing Custom/Lookalike Audience IDs pasted in from Ads
            // Manager - see FacebookAdService::buildDetailedTargeting()'s
            // docblock for why these aren't a live-searched dropdown.
            'custom_audiences' => ['nullable', 'string', 'max:255'],
            'excluded_custom_audiences' => ['nullable', 'string', 'max:255'],
            'device_platforms' => ['nullable', 'array'],
            'device_platforms.*' => ['in:mobile,desktop'],
        ];
    }

    private function getTikTokRules(array $requiredIfPost): array
    {
        return [
            'name' => ['required'],
            // TikTok's real objective_type enum (campaign/create/) - trimmed
            // from a much larger mixed bag of Facebook/legacy-TikTok enum
            // names that would have passed validation here but been rejected
            // by TikTok's own API.
            'objective' => array_merge($requiredIfPost, [
                'in:REACH,TRAFFIC,VIDEO_VIEWS,LEAD_GENERATION,ENGAGEMENT,APP_PROMOTION,WEB_CONVERSIONS,PRODUCT_SALES'
            ]),
            'page_id' => ['nullable'],
            'bid_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:500'
            ],
            'app_promotion_type' => [
                'nullable',
                'in:APP_INSTALL,APP_RETARGETING,APP_PREREGISTRATION',
                function ($attribute, $value, $fail) {
                    if (request()->input('objective') === 'APP_PROMOTION' && is_null($value)) {
                        $fail('The App promotion type is required when objective type is APP PROMOTION.');
                    }
                },
            ],
            'campaign_product_source' => [
                'nullable',
                'in:CATALOG,STORE',
                function ($attribute, $value, $fail) {
                    if (request()->input('objective') === 'PRODUCT_SALES' && is_null($value)) {
                        $fail('The campaign product source is required when objective type is Product Sales.');
                    }
                },
            ],
            'budget_mode' => array_merge($requiredIfPost, [
                'in:BUDGET_MODE_TOTAL,BUDGET_MODE_DYNAMIC_DAILY_BUDGET,BUDGET_MODE_DAY,BUDGET_MODE_INFINITE',
                function ($attribute, $value, $fail) {
                    $objectiveType = request()->input('objective');
                    $cboEnabled = request()->input('cbo_enabled');

                    if ($objectiveType === 'RF_REACH' && $value !== 'BUDGET_MODE_INFINITE') {
                        $fail('The budget mode must be BUDGET_MODE_INFINITE when objective type is RF_REACH.');
                    }

                    $validWithCbo = ['BUDGET_MODE_TOTAL', 'BUDGET_MODE_DYNAMIC_DAILY_BUDGET', 'BUDGET_MODE_DAY'];
                    $validWithoutCbo = ['BUDGET_MODE_INFINITE', 'BUDGET_MODE_TOTAL', 'BUDGET_MODE_DAY'];

                    if ($cboEnabled && !in_array($value, $validWithCbo)) {
                        $fail('Invalid budget mode when CBO is enabled.');
                    } elseif (!$cboEnabled && !in_array($value, $validWithoutCbo)) {
                        $fail('Invalid budget mode when CBO is disabled.');
                    }
                },
            ]),
            'budget' => array_merge($requiredIfPost, [
                'numeric',
                // function ($attribute, $value, $fail) {
                //     $budgetMode = request()->input('budget_mode');
                //     $objectiveType = request()->input('objective');
                //     $productSource = request()->input('campaign_product_source');

                //     if (in_array($budgetMode, ['BUDGET_MODE_DAY', 'BUDGET_MODE_DYNAMIC_DAILY_BUDGET', 'BUDGET_MODE_TOTAL'])) {
                //         if ($objectiveType === 'PRODUCT_SALES' && $productSource === 'STORE' && $value < 10) {
                //             $fail('The budget must be at least 10 for PRODUCT SALES campaigns with STORE as the campaign product source.');
                //         } else if ($value < 50) {
                //             $fail('The budget must be at least 50.');
                //         }
                //     }
                // }
            ]),

            // Ad Group validation
            // MINI_APP/MINI_GAME (App Promotion) and EXTERNAL_OR_DISPLAY (Reach/
            // Video Views/Engagement) are real options the blade's own
            // objectiveConfig can populate into this select but were missing
            // here, so picking them would have failed validation.
            'promotion_type' => ['required_without:objective,REACH,VIDEO_VIEWS,ENGAGEMENT', 'in:APP_ANDROID,APP_IOS,MINI_APP,MINI_GAME,GAME,WEBSITE,EXTERNAL_OR_DISPLAY,LEAD_GENERATION,LEAD_GEN_CLICK_TO_TT_DIRECT_MESSAGE,LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE,LEAD_GEN_CLICK_TO_CALL,WEBSITE_OR_DISPLAY,TIKTOK_SHOP,VIDEO_SHOPPING,LIVE_SHOPPING,PSA_PRODUCT'],
            'promotion_target_type' => ['nullable', 'in:INSTANT_PAGE,EXTERNAL_WEBSITE'],
            // FOLLOWERS/PAGE_VISIT (used by the Engagement objective) were
            // missing here despite being referenced by the blade's own
            // objective->goal map and optimization_goal->billing_event map -
            // every Engagement campaign would have failed this rule.
            'optimization_goal' => array_merge($requiredIfPost, ['in:REACH,ENGAGED_VIEW,ENGAGED_VIEW_FIFTEEN,CLICK,TRAFFIC_LANDING_PAGE_VIEW,CONVERT,VALUE,AUTOMATIC_VALUE_OPTIMIZATION,INSTALL,IN_APP_EVENT,LEAD_GENERATION,FOLLOWERS,PAGE_VISIT']),
            'gender' => ['nullable', 'in:GENDER_FEMALE,GENDER_MALE,GENDER_UNLIMITED'],
            'placement_type' => ['nullable', 'in:PLACEMENT_TYPE_AUTOMATIC,PLACEMENT_TYPE_NORMAL'],
            // Was a plain 'in:...' rule directly on 'placements' - that
            // validates a single scalar, but the form submits placements[]
            // as an array (multiple placements can be picked), so every
            // manual-placement submission would have failed validation
            // outright. 'array' + 'placements.*' checks each selected
            // value instead.
            'placements' => ['required_if:placement_type,PLACEMENT_TYPE_NORMAL', 'array'],
            'placements.*' => ['in:PLACEMENT_TIKTOK,PLACEMENT_PANGLE,PLACEMENT_GLOBAL_APP_BUNDLE'],
            // Ad-level text (TikTok's creatives[].ad_text, see
            // TiktokAdService::buildCreative()) - missing here meant
            // FormRequest::validated() silently dropped it before it ever
            // reached TiktokAdService, even though the create/edit forms'
            // "description" textarea submitted it correctly all along.
            'description' => ['required', 'string'],
            'languages' => ['array', 'required'],
            'final_budget' => ['nullable'],
            'target_link'  => ['required'],
            'call_to_action' => ['required', 'in:APPLY_NOW,BOOK_NOW,CALL_NOW,CHECK_AVAILABLILITY,CONTACT_US,DOWNLOAD_NOW,EXPERIENCE_NOW,GET_QUOTE,GET_SHOWTIMES,GET_TICKETS_NOW,INSTALL_NOW,INTERESTED,LEARN_MORE,LISTEN_NOW,ORDER_NOW,PLAY_GAME,PREORDER_NOW,READ_MORE,SEND_MESSAGE,SHOP_NOW,SIGN_UP,SUBSCRIBE,VIEW_NOW,VIEW_PROFILE,VISIT_STORE,WATCH_LIVE,WATCH_NOW,JOIN_THIS_HASHTAG,SHOOT_WITH_THIS_EFFECT,VIEW_VIDEO_WITH_THIS_EFFECT'],
            // billing_event pairs 1:1 with optimization_goal (see the blade's
            // optimizationGoalBillingMap) - this was previously commented out
            // entirely, so any value (or none) passed straight through to
            // TiktokAdService without being checked.
            'billing_event' => array_merge($requiredIfPost, ['in:CPC,CPM,CPV,OCPM']),
          //  'page_id' => array_merge($requiredIfPost, []),
            // Companion to page_id (see TiktokAdService::buildCreative()) -
            // which identity_type a given page_id actually is, since the
            // "TikTok Identity" select sets both together.
            'identity_type' => ['nullable', 'in:TT_USER,CUSTOMIZED_USER,AUTH_CODE,BC_AUTH_TT'],
           // 'operation_status' => ['nullable', 'in:ENABLE,DISABLE'],
            // 'budget_mode' => array_merge($requiredIfPost, ['
            //     in:BUDGET_MODE_DAY,BUDGET_MODE_TOTAL,BUDGET_MODE_DYNAMIC_DAILY_BUDGET,BUDGET_MODE_INFINITE',
            //     function ($attribute, $value, $fail) use ($mediaCampaign) {
            //         if ($mediaCampaign->budget_mode !== $value) {
            //             $fail('The adGroup budget mode must be same as campaign which is ' . $mediaCampaign->budget_mode);
            //         }
            //     }

            //     ]),
           // 'budget' => array_merge($requiredIfPost, ['numeric', "min:20"]),
            // 'schedule_type' => array_merge($requiredIfPost, [
            //     'in:SCHEDULE_START_END,SCHEDULE_FROM_NOW',
            //     function ($attribute, $value, $fail) {
            //         // Custom rule for SCHEDULE_START_END when budget_mode is BUDGET_MODE_TOTAL
            //         if ($this->input('budget_mode') === 'BUDGET_MODE_TOTAL' && $value !== 'SCHEDULE_START_END') {
            //             $fail('When budget mode is BUDGET MODE TOTAL then schedule type must be Schedule start end.');
            //         }
            //     },
            // ]),
            'start_time' => ['required', 'date_format:Y-m-d', 'before:end_time'],
            'end_time'   => ['required', 'date_format:Y-m-d', 'after:start_time'],
            'app_id' => ['nullable', 'required_if:objective,APP_PROMOTION'],
            'optimization_event' => [
                'nullable',
                'required_with:pixel_id',
                'required_if:optimization_goal,IN_APP_EVENT,VALUE',
            ],
            // TikTok requires a pixel for WEB_CONVERSIONS regardless of
            // which of its three optimization goals is picked (confirmed
            // live: AUTOMATIC_VALUE_OPTIMIZATION was still rejected with
            // "Please select a pixel." even with a real pixel_id, until
            // TiktokAdService::storeAdGroup()'s own gate - which had the
            // same CONVERT/VALUE-only mistake - was widened). Keying off
            // objective rather than optimization_goal catches all three
            // goals without needing to enumerate them.
            'pixel_id' => [
                'nullable',
                'required_if:objective,WEB_CONVERSIONS',
            ],
            //'schedule_start_time' => array_merge($requiredIfPost, ['date', 'before:schedule_end_time', 'date_format:Y-m-d\TH:i']),
            // 'schedule_end_time' => [
            //     'date',
            //     'after:schedule_start_time',
            //     'date_format:Y-m-d\TH:i',
            //     function ($attribute, $value, $fail) {
            //         $budgetMode = request()->input('budget_mode');
            //         $scheduleType = request()->input('schedule_type');
    
            //         if ($budgetMode === 'BUDGET_MODE_TOTAL' && empty($value)) {
            //             $fail('If budget_mode is BUDGET_MODE_TOTAL, schedule_end_time is required.');
            //         }
    
            //         // if ($budgetMode === 'BUDGET_MODE_DAY' && !in_array($scheduleType, ['SCHEDULE_START_END', 'SCHEDULE_FROM_NOW'])) {
            //         //     $fail('If budget_mode is BUDGET_MODE_DAY, schedule_type must be either SCHEDULE_START_END or SCHEDULE_FROM_NOW.');
            //         // }
            //     }
            // ],
            //'bid_type' => ['nullable','in:BID_TYPE_CUSTOM,BID_TYPE_NO_BID'],
           // 'deep_bid_type' => ['nullable','in:VO_MIN_ROAS,VO_HIGHEST_VALUE'],
           // 'roas_bid' => ['nullable','required_if:deep_bid_type,VO_MIN_ROAS', 'numeric'],
          //  'billing_event' => array_merge($requiredIfPost, ['in:CPC,CPM,CPV,OCPC,GD,OCPM']),
           // 'pacing' => array_merge($requiredIfPost, ['in:PACING_MODE_SMOOTH,PACING_MODE_FAST']),
           'countries' => ['array', 'required'],
            'age_range' => ['required', 'array'],
            'media' => array_merge($requiredIfPost, [
                'array',
                function ($attribute, $value, $fail) {
                    if (request()->input('media_type') === 'CAROUSEL' && count($value) < 2) {
                        $fail('Carousel ads need at least 2 images.');
                    }

                    // REACH only supports SINGLE_VIDEO on TikTok's core
                    // placement - IMAGE and CAROUSEL both get rejected at
                    // ad/create/ with a confusing "Unsupported image
                    // size" error regardless of the actual image's
                    // dimensions (confirmed live with both a 1080x1080
                    // and a 1080x1920 image, both rejected identically;
                    // a video succeeded immediately with no other change).
                    if (request()->input('objective') === 'REACH' && request()->input('media_type') !== 'VIDEO') {
                        $fail('Reach campaigns on TikTok only support video ads - please upload a video instead of an image.');
                    }
                },
            ]),
            // Video files need far more headroom than images - TikTok allows
            // uploads up to ~500MB for video vs a few MB for images.
            'media.*' => [
                'file',
                function ($attribute, $value, $fail) {
                    $maxKb = request()->input('media_type') === 'VIDEO' ? 512000 : 30720;

                    if ($value->getSize() > $maxKb * 1024) {
                        $fail('Each file must not exceed ' . ($maxKb / 1024) . 'MB.');
                    }
                },
            ],
           'media_type' => ['required', 'in:IMAGE,VIDEO,CAROUSEL'],
            // Optional - TiktokAdService falls back to auto-extracting a
            // cover frame from the video itself (fetchVideoCoverImageId())
            // when no explicit cover is uploaded, so this is never
            // required_if the way 'music' below is for Carousel.
            'video_cover' => ['nullable', 'image', 'max:8192'],
            // TikTok rejects Carousel ad creation without a music track
            // (see TiktokAdService::buildCreative()/uploadMusic()) -
            // required, not just nullable, since there's no reliable
            // automatic default confirmed to satisfy TikTok's own
            // "valid music for Carousel Ads" check.
            'music' => [
                'required_if:media_type,CAROUSEL',
                'nullable',
                'file',
                'mimes:mp3,wav,m4a,flac',
                'max:10240',
            ],
            // 'bid_display_mode' => [
            //     'nullable', 
            //     'in:CPV',
            //     function ($attribute, $value, $fail) use ($mediaCampaign) {
            //         if ($mediaCampaign->objective === 'VIDEO_VIEW' && empty($value)) {
            //             $fail('The bid display mode is required due to selected campaign objective is Video View.');
            //         }
            //     }
            // ],

            // 'bid_price' => [
            //     'nullable',
            //     'numeric',
            //     'lt:budget',
            //     function ($attribute, $value, $fail) use ($mediaCampaign) {
            //         // Check if bid_type is BID_TYPE_CUSTOM and billing_event is CPC, CPM, or CPV
            //         if ($this->input("bid_type") === 'BID_TYPE_CUSTOM' &&
            //             in_array($this->input("billing_event"), ['CPC', 'CPM', 'CPV'])) {
            //             // If conditions are met, ensure bid_price is provided
            //             if (empty($value)) {
            //                 $fail('The bid price is required when bid type is BID_TYPE_CUSTOM and billing event is CPC, CPM, or CPV.');
            //             }
            //         }
            //         if ($mediaCampaign->budget < $value) {
            //             $fail('The bid price must be lower then the selected campaign budget');
            //         }
            //     },
            // ],
            // 'conversion_bid_price' => [
            //     'nullable',
            //     'numeric',
            //     function ($attribute, $value, $fail) use ($mediaCampaign) {
            //         // Check if bid_type is BID_TYPE_CUSTOM and billing_event is CPC, CPM, or CPV
            //         if ($this->input("bid_type") === 'BID_TYPE_CUSTOM' && $this->input("billing_event") === 'OCPM') {
            //             // If conditions are met, ensure bid_price is provided
            //             if (empty($value)) {
            //                 $fail('The bid price is required when bid type is BID_TYPE_CUSTOM and billing event is OCPM.');
            //             }
            //         }
            //         if ($mediaCampaign->budget < $value) {
            //             $fail('The conversion bid price must be lower then the selected campaign budget.');
            //         }
            //     },

            // ]
            // Free-text, resolved against TikTok's Tool API at submit time
            // (TiktokAdService::resolveByName()/resolveByKeyword()) rather
            // than a fixed enum - comma-separated so the form can stay a
            // single text input instead of a live-search widget.
            'interest_categories' => ['nullable', 'string', 'max:500'],
            'interest_keywords' => ['nullable', 'string', 'max:500'],
            'behaviors' => ['nullable', 'string', 'max:500'],
            'operating_systems' => ['nullable', 'array'],
            'operating_systems.*' => ['in:ANDROID,IOS'],
            'network_types' => ['nullable', 'array'],
            'network_types.*' => ['in:WIFI,2G,3G,4G'],
            'device_price_min' => ['nullable', 'integer', 'min:0'],
            'device_price_max' => ['nullable', 'integer', 'min:0', 'gte:device_price_min'],
            // Existing Custom Audience IDs pasted in from TikTok Ads
            // Manager - see TiktokAdService::buildAudienceTargeting()'s
            // docblock for why these aren't a live-searched dropdown.
            'audience_ids' => ['nullable', 'string', 'max:255'],
            'excluded_audience_ids' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function getSnapchatRules(array $requiredIfPost): array
    {
        // Every rule below that used to key off an input named "type" was
        // dead code - the create/edit forms only ever submit
        // "creative_type", never "type" - so none of these cross-checks
        // (call_to_action-per-type, icon/app_name, ios_app_id/
        // android_app_url, deep_link_uri, etc) ever actually ran.
        $callToActionMapping = [
            "APP_INSTALL" => ["BOOK_NOW", "DONATE", "DOWNLOAD", "GET_NOW", "INSTALL_NOW", "ORDER_NOW", "PLAY", "SHOP_NOW", "SIGN_UP", "TRY", "USE_APP", "WATCH", "VOTE", "DIRECTIONS", "PLAY_GAME"],
            "LENS_APP_INSTALL" => ["BOOK_NOW", "DONATE", "DOWNLOAD", "GET_NOW", "INSTALL_NOW", "ORDER_NOW", "PLAY", "SHOP_NOW", "SIGN_UP", "TRY", "USE_APP", "WATCH", "PLAY_GAME"],
            "DEEP_LINK" => ["DONATE", "PLAY", "SHOP_NOW", "SIGN_UP", "USE_APP", "MORE", "OPEN_APP", "TRY", "WATCH", "VIEW_PROFILE", "VOTE", "DIRECTIONS", "PRE_REGISTER", "PLAY_GAME", "DOWNLOAD"],
            "LEAD_GENERATION" => ["APPLY_NOW", "MORE", "BOOK_NOW", "GET_NOW", "SIGN_UP", "TEST_DRIVE", "REQUEST_APPOINTMENT", "REQUEST_QUOTE", "FREE_TRIAL", "CLAIM_SAMPLE", "GET_COUPON"],
            "LENS_DEEP_LINK" => ["DONATE", "PLAY", "SHOP_NOW", "SIGN_UP", "USE_APP", "MORE", "OPEN_APP", "TRY", "WATCH", "VIEW_PROFILE", "VOTE", "DIRECTIONS", "VIEW_MENU", "PRE_REGISTER", "PLAY_GAME"],
            "WEB_VIEW" => ["APPLY_NOW", "MORE", "ORDER_NOW", "PLAY", "READ", "SHOP_NOW", "SHOW", "SIGN_UP", "VIEW", "WATCH", "DONATE", "DOWNLOAD", "RESPOND", "BUY_TICKETS", "SHOWTIMES", "BOOK_NOW", "GET_NOW", "LISTEN", "TRY", "VOTE", "VIEW_MENU", "PRE_REGISTER", "PLAY_GAME"],
            "LENS_WEB_VIEW" => ["APPLY_NOW", "MORE", "ORDER_NOW", "PLAY", "READ", "SHOP_NOW", "SHOW", "SIGN_UP", "VIEW", "WATCH", "DONATE", "DOWNLOAD", "RESPOND", "BUY_TICKETS", "SHOWTIMES", "BOOK_NOW", "GET_NOW", "LISTEN", "TRY", "VOTE", "DIRECTIONS", "VIEW_MENU", "PRE_REGISTER", "PLAY_GAME"],
            "AD_TO_LENS" => ["PLAY", "TRY", "SHOP_NOW", "VOTE"],
            "AD_TO_MESSAGE" => ["MESSAGE_NOW", "OPEN_APP"],
            "AD_TO_CALL" => ["CALL_NOW", "OPEN_APP"],
            "REMINDER" => ["REMIND_ME"],
        ];

        $requiresAppFields = ['APP_INSTALL', 'LENS_APP_INSTALL', 'DEEP_LINK', 'LENS_DEEP_LINK'];

        return [
            'name' => ['required'],
            'target_link'  => ['required', 'url'],
            'final_budget' => ['nullable'],
            'start_time' => ['required', 'date_format:Y-m-d', 'before:end_time'],
            'end_time'   => ['required', 'date_format:Y-m-d', 'after:start_time'],
            'objective' => array_merge($requiredIfPost, [
                'in:AWARENESS_AND_ENGAGEMENT,APP_PROMOTION,SALES,LEADS,TRAFFIC'
            ]),
            // objective_v2_properties.promotion_type is documented as
            // Optional and only meaningful for AWARENESS_AND_ENGAGEMENT
            // (PROMOTE_PLACES/PROMOTE_SHOWS) and APP_PROMOTION (APP_INSTALL/
            // APP_REENGAGEMENT) - SALES/TRAFFIC/LEADS don't use it at all, so
            // it can't be required unconditionally. The blade sends an empty
            // string (not the literal "NONE") for objectives/UI states where
            // it doesn't apply.
            'promotion_type' => ['nullable', 'in:PROMOTE_PLACES,PROMOTE_SHOWS,APP_INSTALL,APP_REENGAGEMENT'],
            // Real conversion_location enum per Snap's ad-squad-ui-render-data
            // docs - was previously WEB,APP,ON_SNAPCHAT,MIXED, none of which
            // (besides WEB/APP) are real values.
            'conversion_location' => ['nullable', 'in:APP,CALL,LEAD_FORM,PUBLIC_PROFILE,TEXT,WEB'],
            'budget' => ['required', 'gt:0'],
            'platform' => ['sometimes'],
            'description' => ['required', 'string', 'max:34'],
            'countries' => ['array', 'required'],
            'city_id' => ['sometimes'],
            'age_range' => ['required', 'array'],
            'languages'   => ['nullable', 'array'],
            'languages.*' => ['in:en,ar,es,fr,de,ja,ko,pt,ru,zh'],
            'gender'   => ['required', 'in:male,female,both'],
            'media' => array_merge($requiredIfPost, [
                'array',
                function ($attribute, $value, $fail) {
                    if (request()->input('media_type') === 'CAROUSEL' && count($value) < 2) {
                        $fail('Carousel ads need at least 2 images.');
                    }
                },
            ]),
            // Video needs far more headroom than an image (Snapchat allows
            // up to 1GB via chunked upload; this app uploads in one shot,
            // so 500MB is a reasonable single-request ceiling).
            'media.*' => [
                'file',
                function ($attribute, $value, $fail) {
                    $maxKb = request()->input('media_type') === 'VIDEO' ? 512000 : 30720;

                    if ($value->getSize() > $maxKb * 1024) {
                        $fail('Each file must not exceed ' . ($maxKb / 1024) . 'MB.');
                    }
                },
            ],
            'media_type' => ['required', 'in:IMAGE,VIDEO,CAROUSEL'],
            'carousel_cards' => ['nullable', 'required_if:media_type,CAROUSEL', 'json'],
            'budget_mode' => ['in:daily,life_time'],
            'app_id' => ['nullable', 'required_if:optimization_goal,APP_INSTALLS,APP_PURCHASE,APP_SIGNUP,APP_ADD_TO_CART'],
            // Full real optimization_goal enum per Snap's ad-squads docs.
            // APP_REENGAGE_OPEN/APP_REENGAGE_PURCHASE were missing despite
            // being real values the blade's App Re-engagement / Sales-via-App
            // / Traffic-via-App options actually send.
            'optimization_goal' => array_merge($requiredIfPost, ['in:LEAD_FORM_SUBMISSIONS,IMPRESSIONS,SWIPES,APP_INSTALLS,VIDEO_VIEWS,VIDEO_VIEWS_15_SEC,USES,STORY_OPENS,PIXEL_PAGE_VIEW,PIXEL_ADD_TO_CART,LANDING_PAGE_VIEW,PIXEL_PURCHASE,PIXEL_SIGNUP,APP_ADD_TO_CART,APP_PURCHASE,APP_SIGNUP,APP_REENGAGE_OPEN,APP_REENGAGE_PURCHASE']),
            // MIN_ROAS was deprecated by Snap on 2025-02-10 and is no longer
            // a valid bid_strategy value.
            'bid_strategy' => array_merge($requiredIfPost, [
                'in:AUTO_BID,LOWEST_COST_WITH_MAX_BID,TARGET_COST'
            ]),
            'bid_amount' => [
                'required_if:bid_strategy,LOWEST_COST_WITH_MAX_BID,TARGET_COST',
                'nullable',
                'numeric',
                'min:0.01',
                'max:500'
            ],
            'pixel_id' => ['required_if:optimization_goal,PIXEL_PURCHASE,PIXEL_SIGNUP,PIXEL_PAGE_VIEW,PIXEL_ADD_TO_CART'],
            'top_snap_crop_position' => ['nullable', 'in:OPTIMIZED,MIDDLE,TOP,BOTTOM'],
            'creative_type' => array_merge($requiredIfPost, ['in:REMINDER,SNAP_AD,APP_INSTALL,WEB_VIEW,DEEP_LINK,AD_TO_LENS,AD_TO_CALL,AD_TO_MESSAGE,COMPOSITE,LENS,LENS_WEB_VIEW,LENS_APP_INSTALL,LENS_DEEP_LINK,LEAD_GENERATION']),
            'call_to_action' => [
                'required_if:creative_type,DEEP_LINK,APP_INSTALL,LENS_APP_INSTALL,LEAD_GENERATION,LENS_DEEP_LINK,WEB_VIEW,LENS_WEB_VIEW,AD_TO_LENS,AD_TO_MESSAGE,AD_TO_CALL',
                function ($attribute, $value, $fail) use ($callToActionMapping) {
                    $type = request()->input('creative_type');

                    if ($type && isset($callToActionMapping[$type]) && !in_array($value, $callToActionMapping[$type])) {
                        $fail("The selected call to action is invalid for the selected creative type.");
                    }
                }
            ],
            'icon_media_id' => [
                'nullable',
                'required_if:creative_type,APP_INSTALL,LENS_APP_INSTALL,DEEP_LINK,LENS_DEEP_LINK',
            ],
            'app_name' => [
                'required_if:creative_type,APP_INSTALL,LENS_APP_INSTALL,DEEP_LINK,LENS_DEEP_LINK',
                'nullable',
                'max:30',
            ],
            'ios_app_id' => [
                'sometimes',
                function ($attribute, $value, $fail) use ($requiresAppFields) {
                    if (in_array(request()->input('creative_type'), $requiresAppFields) && empty($value) && empty(request()->input('android_app_url'))) {
                        $fail('Either ios_app_id or android_app_url is required for this creative type.');
                    }
                }
            ],
            'android_app_url' => [
                'sometimes',
                function ($attribute, $value, $fail) use ($requiresAppFields) {
                    if (in_array(request()->input('creative_type'), $requiresAppFields) && empty($value) && empty(request()->input('ios_app_id'))) {
                        $fail('Either ios_app_id or android_app_url is required for this creative type.');
                    }
                }
            ],
            'deep_link_uri' => ['required_if:creative_type,DEEP_LINK,LENS_DEEP_LINK'],
            'fallback_type' => ['nullable', 'in:APP_INSTALL,WEB_SITE'],
            'web_view_fallback_url' => ['nullable', 'url', 'required_if:fallback_type,WEB_SITE'],
            'phone_number_id' => ['required_if:creative_type,AD_TO_CALL,AD_TO_MESSAGE'],
            'message' => ['nullable', 'max:160'],
            'lens_media_id' => ['required_if:creative_type,AD_TO_LENS'],
            'url' => [
                'required_if:creative_type,WEB_VIEW,LENS_WEB_VIEW',
                'url',
                'nullable'
            ],
        ];
    }

    /**
     * Google Search (Responsive Search Ad) campaigns. Unlike the other
     * platforms there's no "objective" enum to validate here - Search
     * campaigns are keyword-targeted and the only real campaign-shape
     * choice is the bidding strategy.
     */
    private function getGoogleRules(array $requiredIfPost): array
    {
        return [
            'name' => ['required', 'max:255'],
            'budget_mode' => array_merge($requiredIfPost, ['in:daily,total']),
            'budget' => array_merge($requiredIfPost, ['numeric', 'gt:0']),
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'bid_strategy' => array_merge($requiredIfPost, [
                'in:MAXIMIZE_CONVERSIONS,TARGET_CPA,TARGET_ROAS,MANUAL_CPC,TARGET_SPEND'
            ]),
            'bid_amount' => [
                'required_if:bid_strategy,TARGET_CPA,TARGET_ROAS',
                'nullable',
                'numeric',
                'min:0.01',
            ],
            // Keywords/countries are locked in at creation - the edit form
            // shows them read-only and GoogleAdService::update() only ever
            // touches name/dates/ad copy, so these are create-only.
            'keywords' => array_merge($requiredIfPost, ['string']),
            'match_type' => array_merge($requiredIfPost, ['in:BROAD,PHRASE,EXACT']),
            'countries' => array_merge($requiredIfPost, ['array']),
            'languages' => ['nullable', 'array'],
            'languages.*' => ['in:en,ar,es,fr,de,ja,ko,pt,ru,zh'],
            'gender' => ['nullable', 'in:male,female,both'],
            'age_range' => ['nullable', 'array'],
            'age_range.*' => ['in:AGE_RANGE_18_24,AGE_RANGE_25_34,AGE_RANGE_35_44,AGE_RANGE_45_54,AGE_RANGE_55_64,AGE_RANGE_65_UP'],
            'headlines' => ['required', 'string'],
            'descriptions' => ['required', 'string'],
            'target_link' => ['required', 'url'],
        ];
    }

    /**
     * YouTube Demand Gen campaigns. Google Ads API can no longer create
     * classic VIDEO campaigns (that surface is API-read-only now) - Demand
     * Gen is the only API-creatable path to YouTube video ads, so this
     * validates a DemandGenVideoResponsiveAd shape (video + headlines/
     * descriptions/CTA/final URL) rather than a Responsive Search Ad.
     */
    private function getYoutubeRules(array $requiredIfPost): array
    {
        return [
            'name' => ['required', 'max:255'],
            'budget_mode' => array_merge($requiredIfPost, ['in:daily,total']),
            'budget' => array_merge($requiredIfPost, ['numeric', 'gt:0']),
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'bid_strategy' => array_merge($requiredIfPost, [
                'in:MAXIMIZE_CONVERSIONS,TARGET_CPA,MAXIMIZE_CONVERSION_VALUE'
            ]),
            'bid_amount' => [
                'required_if:bid_strategy,TARGET_CPA',
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'countries' => array_merge($requiredIfPost, ['array']),
            'languages' => ['nullable', 'array'],
            'languages.*' => ['in:en,ar,es,fr,de,ja,ko,pt,ru,zh'],
            'gender' => ['nullable', 'in:male,female,both'],
            'age_range' => ['nullable', 'array'],
            'age_range.*' => ['in:AGE_RANGE_18_24,AGE_RANGE_25_34,AGE_RANGE_35_44,AGE_RANGE_45_54,AGE_RANGE_55_64,AGE_RANGE_65_UP'],
            // The video/logo assets are locked in at creation -
            // YoutubeAdService::update() only ever touches name/dates/
            // headlines/descriptions/business_name/final URL.
            'video_id' => array_merge($requiredIfPost, ['string']),
            'headlines' => ['required', 'string'],
            // Optional - YoutubeAdService::storeAd()/update() fall back to
            // reusing headlines[] for the ad's required longHeadlines
            // asset when this is left blank.
            'long_headlines' => ['nullable', 'string'],
            'descriptions' => ['required', 'string'],
            'business_name' => ['required', 'string', 'max:25'],
            'call_to_action' => array_merge($requiredIfPost, [
                'in:LEARN_MORE,SHOP_NOW,SIGN_UP,DOWNLOAD,SUBSCRIBE,BOOK_NOW,CONTACT_US,APPLY_NOW,GET_QUOTE,ORDER_NOW,SEE_MORE,WATCH_NOW'
            ]),
            'target_link' => ['required', 'url'],
            'media' => $requiredIfPost,
            'media.*' => [
                'file',
                function ($attribute, $value, $fail) {
                    if ($value->getSize() > 5120 * 1024) {
                        $fail('The logo/companion image must not exceed 5MB.');
                    }
                },
            ],
        ];
    }

    /**
     * X (Twitter) Promoted Tweets. Unlike every other platform here, X's
     * Tweets are immutable once created - XAdService::update() only ever
     * touches the campaign's name/dates - so the line item's targeting/bid
     * and the Tweet's own text/media are create-only fields, locked on the
     * edit form.
     */
    private function getXRules(array $requiredIfPost): array
    {
        return [
            'name' => ['required', 'max:255'],
            // Funding instruments can't be created via the API - they must
            // already exist on the X Ads account (credit card via
            // ads.x.com or a credit line set up by X) - so this is a
            // manually-entered ID rather than a dropdown populated from a
            // live lookup, consistent with how this app avoids live API
            // calls at page-render time for every other platform too.
            'funding_instrument_id' => array_merge($requiredIfPost, ['string']),
            'budget_mode' => array_merge($requiredIfPost, ['in:daily,total']),
            'budget' => array_merge($requiredIfPost, ['numeric', 'gt:0']),
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'objective' => array_merge($requiredIfPost, [
                'in:APP_ENGAGEMENTS,APP_INSTALLS,FOLLOWERS,ENGAGEMENTS,REACH,VIDEO_VIEWS,PREROLL_VIEWS,WEBSITE_CLICKS'
            ]),
            'placements' => array_merge($requiredIfPost, ['array']),
            'placements.*' => ['in:ALL_ON_TWITTER,PUBLISHER_NETWORK,TWITTER_PROFILE,TWITTER_SEARCH,TWITTER_TIMELINE,SPOTLIGHT,TREND'],
            'bid_type' => array_merge($requiredIfPost, ['in:AUTO,MAX,TARGET']),
            'bid_amount' => [
                'required_if:bid_type,MAX,TARGET',
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'countries' => array_merge($requiredIfPost, ['array']),
            'languages' => ['nullable', 'array'],
            'languages.*' => ['in:en,ar,es,fr,de,ja,ko,pt,ru,zh'],
            'gender' => ['nullable', 'in:male,female,both'],
            // Tweets (even nullcast/promoted-only ones) share the platform's
            // 280-character limit.
            'message' => array_merge($requiredIfPost, ['string', 'max:280']),
            'target_link' => ['required', 'url'],
            'media' => ['nullable', 'array', 'max:1'],
            'media.*' => [
                'file',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    $maxKb = in_array($extension, ['mp4', 'mov']) ? 512000 : 15360;

                    if ($value->getSize() > $maxKb * 1024) {
                        $fail('Each file must not exceed ' . ($maxKb / 1024) . 'MB.');
                    }
                },
            ],
        ];
    }

    /**
     * LinkedIn Marketing API. Unlike Facebook/Snapchat/TikTok there's no
     * gender targeting facet at all on LinkedIn (see LinkedinAdService::
     * buildTargeting()'s docblock), so there's no 'gender' rule here -
     * countries/seniorities/age_range are the only targeting inputs.
     * Creatives are immutable once created (LinkedinAdService::
     * updateCreative()'s docblock), so 'media'/'creative_type' are
     * create-only, same pattern the X/Google/YouTube rule sets above use
     * for their own locked-at-creation fields.
     */
    private function getLinkedinRules(array $requiredIfPost): array
    {
        return [
            'name' => ['required', 'max:255'],
            // TALENT_LEADS (LinkedIn's Talent Solutions "Talent Leads"
            // objective) is deliberately not included - it requires an
            // active LinkedIn Recruiter contract and isn't documented as
            // a valid objectiveType on the standard Campaign Manager
            // adCampaigns API this module authenticates against (r_ads/
            // rw_ads/r_ads_reporting); LinkedIn's own UI greys it out for
            // accounts without that contract too. JOB_APPLICANT, by
            // contrast, is a documented objectiveType on this same API
            // and works with the STANDARD_UPDATE campaign format this
            // service already creates - see LinkedinAdService::
            // defaultOptimizationGoal()'s docblock.
            'objective' => array_merge($requiredIfPost, [
                'in:BRAND_AWARENESS,WEBSITE_VISIT,ENGAGEMENT,VIDEO_VIEW,LEAD_GENERATION,WEBSITE_CONVERSION,JOB_APPLICANT'
            ]),
            'creative_type' => array_merge($requiredIfPost, ['in:SPONSORED_CONTENT,TEXT_AD']),
            'bid_type' => array_merge($requiredIfPost, ['in:CPC,CPM']),
            'bid_amount' => ['nullable', 'numeric', 'min:0.01'],
            'budget_mode' => array_merge($requiredIfPost, ['in:daily,total']),
            'budget' => array_merge($requiredIfPost, ['numeric', 'gt:0']),
            'final_budget' => ['nullable'],
            'start_time' => ['required', 'date_format:Y-m-d', 'before:end_time'],
            'end_time' => ['nullable', 'date_format:Y-m-d', 'after:start_time'],
            'countries' => ['required', 'array'],
            'seniorities' => ['nullable', 'array'],
            'seniorities.*' => ['in:unpaid,training,entry,senior,manager,director,vp,cxo,partner,owner'],
            'age_range' => ['nullable', 'array'],
            'age_range.*' => ['in:18-24,25-34,35-54,55+'],
            // Real, documented LinkedIn facet (urn:li:adTargetingFacet:
            // genders) - see LinkedinAdService::buildTargeting()'s
            // docblock for why this app must show a discrimination notice
            // wherever it's exposed in the UI.
            'genders' => ['nullable', 'array'],
            'genders.*' => ['in:male,female'],
            // Free-text, typeahead-resolved against LinkedIn at submit
            // time (LinkedinAdService::resolveEntityUrns()) rather than a
            // fixed enum - comma-separated so the form can stay a single
            // text input instead of a live-search widget.
            'titles' => ['nullable', 'string', 'max:500'],
            'industries' => ['nullable', 'string', 'max:500'],
            'skills' => ['nullable', 'string', 'max:500'],
            'employers' => ['nullable', 'string', 'max:500'],
            'company_size' => ['nullable', 'array'],
            'company_size.*' => ['in:1,2-10,11-50,51-200,201-500,501-1000,1001-5000,5001-10000,10001+'],
            'years_experience_min' => ['nullable', 'integer', 'min:1', 'max:12'],
            'years_experience_max' => ['nullable', 'integer', 'min:1', 'max:12', 'gte:years_experience_min'],
            'description' => ['required', 'string', 'max:600'],
            'target_link' => ['required', 'url'],
            'call_to_action' => ['nullable', 'string'],
            'media' => [
                'array',
                function ($attribute, $value, $fail) {
                    if ($this->isMethod('post') && $this->input('creative_type') === 'SPONSORED_CONTENT' && empty($value)) {
                        $fail('At least one image or video is required for Sponsored Content ads.');
                    }
                },
            ],
            'media.*' => [
                'file',
                function ($attribute, $value, $fail) {
                    $isVideo = in_array(strtolower($value->getClientOriginalExtension()), ['mp4', 'mov', 'avi', 'mkv', 'webm']);
                    $maxKb = $isVideo ? 512000 : 30720;

                    if ($value->getSize() / 1024 > $maxKb) {
                        $fail("The {$attribute} may not be greater than " . ($isVideo ? '500MB' : '30MB') . '.');
                    }
                },
            ],
        ];
    }
}