<?php

/*
|--------------------------------------------------------------------------
| Connection Hub
|--------------------------------------------------------------------------
| docs/connection-hub-design.md. `capabilities` maps each capability to the
| granted scopes (any-of) that unlock it, per platform; drivers and the
| backfill derive SocialConnection::capabilities from granted scopes with it.
*/

return [

    'capabilities' => [

        'meta' => [
            'posting' => ['pages_manage_posts', 'instagram_content_publish', 'instagram_business_content_publish', 'whatsapp_business_messaging'],
            'messaging' => ['pages_messaging', 'instagram_manage_messages', 'instagram_business_manage_messages', 'whatsapp_business_messaging'],
            'ads' => ['ads_management', 'ads_read'],
            'insights' => ['read_insights', 'instagram_manage_insights', 'instagram_business_manage_insights'],
        ],

    ],

];
