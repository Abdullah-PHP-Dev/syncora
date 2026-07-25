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
        } else if ($platform === 'x') {
            $validations = $this->getXRules($requiredIfPost);
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
            'media' => array_merge($requiredIfPost, ['array']),
            'media.*' => ['file', 'max:1024'],
            'media_type' => ['required', 'in:IMAGE,VIDEO,CAROUSEL'],
            //  'music' => ['required_with:ad_format,CAROUSEL'],
            //'video' => ['required_if:media_type,VIDEO'],
            'description' => ['required', 'string'],
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
                                GET_OFFER,GET_OFFER_VIEW,BUY_NOW,
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
            'optimization_goal'    => array_merge($requiredIfPost, ['in:LINK_CLICKS,LANDING_PAGE_VIEWS,REACH,IMPRESSIONS']),
            'billing_event'    => array_merge($requiredIfPost, ['in:NONE,CLICKS,IMPRESSIONS,LINK_CLICKS,OFFER_CLAIMS,PAGE_LIKES,POST_ENGAGEMENT,THRUPLAY,PURCHASE,LISTING_INTERACTION']),
            'destination_type'    => ['nullable'],
            'bid_amount'    => array_merge($requiredIfPost),
            'pixel_id'           => ['string', 'max:255', 'nullable'],
            'page_id'            => ['string', 'max:255', 'nullable'],
            'application_id'     => ['string', 'max:255', 'nullable'],
            'custom_event_type'     => ['string', 'max:255', 'nullable'],
            'age_to'     => ['required'],
            'age_from'     => ['required'],
            'gender'     => ['required'],
            'languages'     => ['required', 'array'],
            'final_budget' => ['required'],
            'objective' => ['required']
        ];
    }

    private function getTikTokRules(array $requiredIfPost): array
    {
        return [
            'name' => ['required'],
            'objective' => array_merge($requiredIfPost, [
                'in:APP_INSTALLS,BRAND_AWARENESS,CONVERSIONS,EVENT_RESPONSES,LEAD_GENERATION,LINK_CLICKS,LOCAL_AWARENESS,MESSAGES,OFFER_CLAIMS,OUTCOME_APP_PROMOTION,OUTCOME_AWARENESS,OUTCOME_ENGAGEMENT,OUTCOME_LEADS,OUTCOME_SALES,OUTCOME_TRAFFIC,PAGE_LIKES,POST_ENGAGEMENT,PRODUCT_CATALOG_SALES,REACH,STORE_VISITS,VIDEO_VIEWS,TRAFFIC,APP_PROMOTION,WEB_CONVERSIONS,APP_CONVERSION,APP_INSTALL,CATALOG_SALES,ENGAGEMENT,VIDEO_VIEW,WEB_CONVERSION,PROMOTE_STORIES,PROMOTE_PLACES'
            ]),
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
            'promotion_type' => ['required_without:objective,REACH,VIDEO_VIEWS,ENGAGEMENT', 'in:APP_ANDROID,APP_IOS,GAME,WEBSITE,LEAD_GENERATION,LEAD_GEN_CLICK_TO_TT_DIRECT_MESSAGE,LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE,LEAD_GEN_CLICK_TO_CALL,WEBSITE_OR_DISPLAY,TIKTOK_SHOP,VIDEO_SHOPPING,LIVE_SHOPPING,PSA_PRODUCT'],
            'promotion_target_type' => ['nullable', 'in:INSTANT_PAGE,EXTERNAL_WEBSITE'],
            'optimization_goal' => array_merge($requiredIfPost, ['in:REACH,ENGAGED_VIEW,ENGAGED_VIEW_FIFTEEN,CLICK,TRAFFIC_LANDING_PAGE_VIEW,CONVERT,VALUE,AUTOMATIC_VALUE_OPTIMIZATION,INSTALL,IN_APP_EVENT,LEAD_GENERATION']),
            'gender' => ['nullable', 'in:GENDER_FEMALE,GENDER_MALE,GENDER_UNLIMITED'],
            'placement_type' => ['nullable', 'in:PLACEMENT_TYPE_AUTOMATIC,PLACEMENT_TYPE_NORMAL'],
            'placements' => ['required_if:placement_type,PLACEMENT_TYPE_NORMAL' ,'in:PLACEMENT_TIKTOK,PLACEMENT_PANGLE,PLACEMENT_GLOBAL_APP_BUNDLE'],
            'languages' => ['array', 'required'],
            'final_budget' => ['nullable'],
            'target_link'  => ['required:'],
            'call_to_action' => ['required', 'in:APPLY_NOW,BOOK_NOW,CALL_NOW,CHECK_AVAILABLILITY,CONTACT_US,DOWNLOAD_NOW,EXPERIENCE_NOW,GET_QUOTE,GET_SHOWTIMES,GET_TICKETS_NOW,INSTALL_NOW,INTERESTED,LEARN_MORE,LISTEN_NOW,ORDER_NOW,PLAY_GAME,PREORDER_NOW,READ_MORE,SEND_MESSAGE,SHOP_NOW,SIGN_UP,SUBSCRIBE,VIEW_NOW,VIEW_PROFILE,VISIT_STORE,WATCH_LIVE,WATCH_NOW,JOIN_THIS_HASHTAG,SHOOT_WITH_THIS_EFFECT,VIEW_VIDEO_WITH_THIS_EFFECT'],
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
            'pixel_id' => [
                'nullable',
                'required_if:optimization_goal,CONVERT,VALUE',
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
            'media' => array_merge($requiredIfPost, ['array']),
            'media.*' => ['file', 'max:1024'],
           // 'logo' => ['required', 'file', 'max:1024'],
           'media_type' => ['required', 'in:IMAGE,VIDEO,CAROUSEL'],
           'music' => ['required_if:ad_format,CAROUSEL_ADS'],
            'video' => ['required_if:ad_format,SINGLE_VIDEO'],
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
        ];
    }

    private function getSnapchatRules(array $requiredIfPost): array
    {
        $callToActionMapping = [
            "APP_INSTALL" => ["BOOK_NOW", "DONATE", "DOWNLOAD", "GET_NOW", "INSTALL_NOW", "ORDER_NOW", "PLAY", "SHOP_NOW", "SIGN_UP", "TRY", "USE_APP", "WATCH", "VOTE", "DIRECTIONS", "PLAY_GAME"],
            "LENS_APP_INSTALL" => ["BOOK_NOW", "DONATE", "DOWNLOAD", "GET_NOW", "INSTALL_NOW", "ORDER_NOW", "PLAY", "SHOP_NOW", "SIGN_UP", "TRY", "USE_APP", "WATCH", "PLAY_GAME"],
            "DEEP_LINK" => ["DONATE", "PLAY", "SHOP_NOW", "SIGN_UP", "USE_APP", "MORE", "OPEN_APP", "TRY", "WATCH", "VIEW_PROFILE", "VOTE", "DIRECTIONS", "PRE_REGISTER", "PLAY_GAME", "DOWNLOAD"],
            "LEAD_GENERATION" => ["APPLY_NOW", "MORE", "BOOK_NOW", "GET_NOW", "SIGN_UP", "TEST_DRIVE", "REQUEST_APPOINTMENT", "REQUEST_QUOTE", "FREE_TRIAL", "CLAIM_SAMPLE", "GET_COUPON"],
            "LENS_DEEP_LINK" => ["DONATE", "PLAY", "SHOP_NOW", "SIGN_UP", "USE_APP", "MORE", "OPEN_APP", "TRY", "WATCH", "VIEW_PROFILE", "VOTE", "DIRECTIONS", "VIEW_MENU", "PRE_REGISTER", "PLAY_GAME"],
            "WEB_VIEW" => ["APPLY_NOW", "MORE", "ORDER_NOW", "PLAY", "READ", "SHOP_NOW", "SHOW", "SIGN_UP", "VIEW", "SHOW", "WATCH", "DONATE", "DOWNLOAD", "APPLY_NOW", "ORDER_NOW", "RESPOND", "BUY_TICKETS", "SHOWTIMES", "BOOK_NOW", "GET_NOW", "LISTEN", "TRY", "VOTE", "VIEW_MENU", "PRE_REGISTER", "PLAY_GAME"],
            "LENS_WEB_VIEW" => ["APPLY_NOW", "MORE", "ORDER_NOW", "PLAY", "READ", "SHOP_NOW", "SHOW", "SIGN_UP", "VIEW", "SHOW", "WATCH", "DONATE", "DOWNLOAD", "APPLY_NOW", "ORDER_NOW", "RESPOND", "BUY_TICKETS", "SHOWTIMES", "BOOK_NOW", "GET_NOW", "LISTEN", "TRY", "VOTE", "DIRECTIONS", "VIEW_MENU", "PRE_REGISTER", "PLAY_GAME"],
            "AD_TO_LENS" => ["PLAY", "TRY", "SHOP_NOW", "VOTE"],
            "AD_TO_MESSAGE" => ["MESSAGE_NOW", "OPEN_APP"],
            "AD_TO_CALL" => ["CALL_NOW", "OPEN_APP"],
            "AD_TO_PLACE" => ["SEE_PLACE", "DIRECTIONS", "VIEW_MENU"]
        ];

        return [
            'name' => ['required'],
            'target_link'  => ['required', 'url'],
            'final_budget' => ['nullable'],
            // 'status' => array_merge($requiredIfPost, ['in:ACTIVE,PAUSED']),
            'start_time' => ['required', 'date_format:Y-m-d', 'before:end_time'],
            'end_time'   => ['required', 'date_format:Y-m-d', 'after:start_time'],
            'ios_app_id' => ['nullable', 'required_if:objective,APP_INSTALL'],
            'android_app_url' => ['nullable', 'required_if:objective,APP_INSTALL'],
            'objective' => array_merge($requiredIfPost, [
                'in:AWARENESS_AND_ENGAGEMENT,APP_PROMOTION,SALES,LEADS,TRAFFIC'
            ]),
            'budget' => ['required', 'gt:0'],
            'platform' => ['sometimes'],
            'description' => ['required', 'string', 'max:34'],
            // 'selected_link_type' => ['required'],
            // 'store_url'  => ['required_without_all:product_id,custom_url'],
            // 'product_id' => ['required_without_all:store_url,custom_url'],
            // 'custom_url' => ['required_without_all:store_url,product_id'],
            'countries' => ['array', 'required'],
            'city_id' => ['sometimes'],
            // 'age_from' => ['required'],
            // 'age_to' => ['required'],
            'age_range' => ['required', 'array'],
            'languages'   => ['nullable', 'array'],
            'languages.*' => ['in:en,ar,es,fr'],
            'gender'   => ['required', 'in:male,female,both'],
           // 'gender.*' => ['required', 'in:male,female,both'],
            'media' => array_merge($requiredIfPost, ['array']),
            'media.*' => ['file', 'max:2048'],
            'budget_mode' => ['in:daily,life_time'],
           // 'type' => ['required', 'in:SNAP_ADS,LENS,FILTER'],
            'ios_app_id' => ['nullable', 'required_if:objective,APP_INSTALL'],
            'app_id' => ['nullable', 'required_if:optimization_goal,APP_INSTALLS,APP_PURCHASE,APP_SIGNUP,APP_ADD_TO_CART'],
            'android_app_url' => ['nullable', 'required_if:objective,APP_INSTALL'],
            'optimization_goal'    => array_merge($requiredIfPost, ['in:LEAD_FORM_SUBMISSIONS,IMPRESSIONS,SWIPES,APP_INSTALLS,VIDEO_VIEWS,VIDEO_VIEWS_15_SEC,USES,STORY_OPENS,PIXEL_PAGE_VIEW,PIXEL_ADD_TO_CART,LANDING_PAGE_VIEW,PIXEL_PURCHASE,PIXEL_SIGNUP,APP_ADD_TO_CART,APP_PURCHASE,APP_SIGNUP']),
            'bid_strategy' => array_merge($requiredIfPost, [
                'in:AUTO_BID,LOWEST_COST_WITH_MAX_BID,MIN_ROAS,TARGET_COST'
            ]),

            'bid_amount' => [
                'required_if:bid_strategy,LOWEST_COST_WITH_MAX_BID,TARGET_COST',
                'nullable',
                'numeric',
                'min:0.01',
                'max:500'
            ],
            'pixel_id'     => ['required_if:optimization_goal,PIXEL_PURCHASE,PIXEL_SIGNUP,PIXEL_PAGE_VIEW,PIXEL_ADD_TO_CART'],
            // Ad Squad Validation
            'optimization_goal'    => array_merge($requiredIfPost, ['in:LEAD_FORM_SUBMISSIONS,IMPRESSIONS,SWIPES,APP_INSTALLS,VIDEO_VIEWS,VIDEO_VIEWS_15_SEC,USES,STORY_OPENS,PIXEL_PAGE_VIEW,PIXEL_ADD_TO_CART,LANDING_PAGE_VIEW,PIXEL_PURCHASE,PIXEL_SIGNUP,APP_ADD_TO_CART,APP_PURCHASE,APP_SIGNUP']),
           // 'type'                 => array_merge($requiredIfPost, ['in:SNAP_ADS,LENS,FILTER']),
            //creative validations
            'top_snap_crop_position'  => ['nullable', 'in:OPTIMIZED,MIDDLE,TOP,BOTTOM'],
            'creative_type' => array_merge($requiredIfPost, ['in:REMINDER,SNAP_AD,APP_INSTALL,WEB_VIEW,DEEP_LINK,AD_TO_LENS,AD_TO_CALL,AD_TO_MESSAGE,PREVIEW,COMPOSITE,LENS,LENS_WEB_VIEW,LENS_APP_INSTALL,LENS_DEEP_LINK,COLLECTION,LEAD_GENERATION,AD_TO_PLACE']),
            'call_to_action' => [
                'required_if:type,DEEP_LINK,APP_INSTALL,LENS_APP_INSTALL,LEAD_GENERATION,LENS_DEEP_LINK,WEB_VIEW,LENS_WEB_VIEW,AD_TO_LENS,AD_TO_MESSAGE,AD_TO_CALL,AD_TO_PLACE', 
                function ($attribute, $value, $fail) {
                    $type = $this->input('type');
                    $callToActionMapping = [
                        "APP_INSTALL" => ["BOOK_NOW", "DONATE", "DOWNLOAD", "GET_NOW", "INSTALL_NOW", "ORDER_NOW", "PLAY", "SHOP_NOW", "SIGN_UP", "TRY", "USE_APP", "WATCH", "VOTE", "DIRECTIONS", "PLAY_GAME"],
                        "LENS_APP_INSTALL" => ["BOOK_NOW", "DONATE", "DOWNLOAD", "GET_NOW", "INSTALL_NOW", "ORDER_NOW", "PLAY", "SHOP_NOW", "SIGN_UP", "TRY", "USE_APP", "WATCH", "PLAY_GAME"],
                        "DEEP_LINK" => ["DONATE", "PLAY", "SHOP_NOW", "SIGN_UP", "USE_APP", "MORE", "OPEN_APP", "TRY", "WATCH", "VIEW_PROFILE", "VOTE", "DIRECTIONS", "PRE_REGISTER", "PLAY_GAME", "DOWNLOAD"],
                        "LEAD_GENERATION" => ["APPLY_NOW", "MORE", "BOOK_NOW", "GET_NOW", "SIGN_UP", "TEST_DRIVE", "REQUEST_APPOINTMENT", "REQUEST_QUOTE", "FREE_TRIAL", "CLAIM_SAMPLE", "GET_COUPON"],
                        "LENS_DEEP_LINK" => ["DONATE", "PLAY", "SHOP_NOW", "SIGN_UP", "USE_APP", "MORE", "OPEN_APP", "TRY", "WATCH", "VIEW_PROFILE", "VOTE", "DIRECTIONS", "VIEW_MENU", "PRE_REGISTER", "PLAY_GAME"],
                        "WEB_VIEW" => ["APPLY_NOW", "MORE", "ORDER_NOW", "PLAY", "READ", "SHOP_NOW", "SHOW", "SIGN_UP", "VIEW", "SHOW", "WATCH", "DONATE", "DOWNLOAD", "APPLY_NOW", "ORDER_NOW", "RESPOND", "BUY_TICKETS", "SHOWTIMES", "BOOK_NOW", "GET_NOW", "LISTEN", "TRY", "VOTE", "VIEW_MENU", "PRE_REGISTER", "PLAY_GAME"],
                        "LENS_WEB_VIEW" => ["APPLY_NOW", "MORE", "ORDER_NOW", "PLAY", "READ", "SHOP_NOW", "SHOW", "SIGN_UP", "VIEW", "SHOW", "WATCH", "DONATE", "DOWNLOAD", "APPLY_NOW", "ORDER_NOW", "RESPOND", "BUY_TICKETS", "SHOWTIMES", "BOOK_NOW", "GET_NOW", "LISTEN", "TRY", "VOTE", "DIRECTIONS", "VIEW_MENU", "PRE_REGISTER", "PLAY_GAME"],
                        "AD_TO_LENS" => ["PLAY", "TRY", "SHOP_NOW", "VOTE"],
                        "AD_TO_MESSAGE" => ["MESSAGE_NOW", "OPEN_APP"],
                        "AD_TO_CALL" => ["CALL_NOW", "OPEN_APP"],
                        "AD_TO_PLACE" => ["SEE_PLACE", "DIRECTIONS", "VIEW_MENU"]
                    ];

                    // Ensure the call_to_action value matches the valid options for the selected type
                    if ($type && isset($callToActionMapping[$type]) && !in_array($value, $callToActionMapping[$type])) {
                        $fail("The selected call to action is invalid for the selected type.");
                    }
                }
            ],
            'icon' => [
                'required_if:type,APP_INSTALL,LENS_APP_INSTALL,DEEP_LINK,LENS_DEEP_LINK',
                'nullable',
            ],
            'app_name' => [
                'required_if:type,APP_INSTALL,LENS_APP_INSTALL,DEEP_LINK,LENS_DEEP_LINK'
            ],
            'ios_app_id' => [
                'sometimes',
                function ($attribute, $value, $fail) {
                    if (in_array($this->input('type'), ['APP_INSTALL', 'LENS_APP_INSTALL', 'DEEP_LINK', 'LENS_DEEP_LINK'])) {
                        if (empty($value) && empty($this->input('android_app_url'))) {
                            $fail('Either ios_app_id or android_app_url is required when type is APP_INSTALL, LENS_APP_INSTALL, DEEP_LINK, or LENS_DEEP_LINK.');
                        }
                    }
                }
            ],
            'android_app_url' => [
                'sometimes',
                function ($attribute, $value, $fail) {
                    // Only check when the type is one of the specific values
                    if (in_array($this->input('type'), ['APP_INSTALL', 'LENS_APP_INSTALL', 'DEEP_LINK', 'LENS_DEEP_LINK'])) {
                        // Ensure that either ios_app_id or android_app_url is provided
                        if (empty($value) && empty($this->input('ios_app_id'))) {
                            $fail('Either ios_app_id or android_app_url is required when type is APP_INSTALL, LENS_APP_INSTALL, DEEP_LINK, or LENS_DEEP_LINK.');
                        }
                    }
                }
            ],
            'deep_link_uri' => [
                'required_if:type,DEEP_LINK,LENS_DEEP_LINK'
            ],
            'fallback_type' => [
                'required_if:type,DEEP_LINK,LENS_DEEP_LINK'
            ],
            'web_view_fallback_url' => [
                'required_if:type,DEEP_LINK,LENS_DEEP_LINK'
            ],
            'preview_media_id' => [
                'required_if:type,PREVIEW'
            ],
            'logo_media_id' => [
                'required_if:type,PREVIEW'
            ],
            'preview_headline' => [
                'required_if:type,PREVIEW'
            ],
            'creative_ids' => [
                'required_if:type,COMPOSITE', 
                'array'
            ],
            'creative_ids.*' => [
                'required_if:type,COMPOSITE',
            ],
            'url' => [
                'required_if:type,WEB_VIEW,LENS_WEB_VIEW', 
                'url',
                'nullable'
            ],
        ];
    }
}