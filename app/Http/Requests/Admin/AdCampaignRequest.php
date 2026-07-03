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
}