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
}