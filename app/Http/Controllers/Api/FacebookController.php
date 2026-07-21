<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\PostComment;
use App\Models\Post;
use Webkul\Admin\Models\SocialPublishAccount;

class FacebookController
{
    private const VERIFY_TOKEN = 'socialeaz-98897';

    public function store($companyId, Request $request)
    {
        try {
            /**
             * 1. Webhook Verification (GET)
             */
            if ($request->isMethod('get')) {
                return $this->verifyWebhook($request);
            }

            /**
             * 2. Incoming Webhook (POST)
             */
            $payload = $request->all();

            Log::info('Facebook/Instagram Webhook Payload:', $payload);

            $objectType = $payload['object'] ?? null;
            if (!in_array($objectType, ['page', 'instagram'])) {
                return response('EVENT_IGNORED', 200);
            }

            $platform = $objectType == 'page' ? 'facebook' : 'instagram';

            $chatAccount = ChatAccount::where([
                'company_id' => $companyId,
                'platform'   => $platform,
            ])->first();

            $mediaAccount = MediaAccount::where([
                'company_id' => $companyId,
                'platform'   => $platform,
            ])->first();

            foreach ($payload['entry'] ?? [] as $entry) {
                $externalPageId = $entry['id'] ?? null;

                // Dynamically fetch the correct connected page/profile based on the incoming webhook ID
                $socialAccount = SocialPublishAccount::where([
                    'company_id'  => $companyId,
                    'platform'    => $platform,
                    'external_id' => $externalPageId,
                ])->first();

                /**
                 * Handle Direct Messages
                 */
                if (!empty($entry['messaging'])) {
                    $this->handleMessages(
                        $entry['messaging'],
                        $companyId,
                        $chatAccount,
                        $mediaAccount,
                        $socialAccount,
                        $platform
                    );
                    continue;
                }

                /**
                 * Handle Comments
                 */
                if (!empty($entry['changes'])) {
                    $this->handleComments(
                        $entry,
                        $companyId,
                        $mediaAccount,
                        $socialAccount,
                        $platform
                    );
                    continue;
                }
            }

            return response('EVENT_RECEIVED', 200);

        } catch (\Throwable $e) {
            return $this->handleException($e, $companyId);
        }
    }

    private function verifyWebhook(Request $request)
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ($mode === 'subscribe' && $token === self::VERIFY_TOKEN) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Invalid verify token', 403);
    }

    /**
     * Handle Facebook & Instagram Direct Messages
     */
    private function handleMessages($messages, $companyId, $chatAccount, $mediaAccount, $socialAccount, $platform)
    {
        foreach ($messages as $messaging) {
            $senderId = $messaging['sender']['id'] ?? null;
            $receiverId = $messaging['recipient']['id'] ?? null;
            $message = $messaging['message'] ?? [];

            if (!$senderId) {
                continue;
            }

            $messageText = $message['text'] ?? '';
            $messageId = $message['mid'] ?? null;
            $attachments = $message['attachments'] ?? [];
            $files = $this->prepareAttachments($attachments);

            // Dynamically choose between \Webkul\Core\Models\FacebookChat or InstagramChat
            $chatModelClass = sprintf('\\Webkul\\Core\\Models\\%sChat', ucfirst($platform));
            
            $modelChat = $chatModelClass::updateOrCreate(
                [
                    'to'         => $receiverId,
                    'from'       => $senderId,
                    'company_id' => $companyId,
                ],
                [
                    'profile_name'              => 'Meta User - ' . time(),
                    'message_id'                => $messageId,
                    'chat_account_id'           => $chatAccount?->id,
                    'media_account_id'          => $mediaAccount?->id,
                    'social_publish_account_id' => $socialAccount?->id,
                    'social_post_id'            => null // Direct messages aren't tied to a specific wall post
                ]
            );

            PostComment::create([
                'company_id'                => $companyId,
                'body'                      => $messageText,
                'sender_type'               => 'customer',
                'sender_name'               => 'Meta User',
                'applicable_id'             => $modelChat->id,
                'applicable_type'           => $platform,
                'type'                      => 'message',
                'is_read'                    => 0,
                'media_account_id'          => $mediaAccount?->id,
                'social_publish_account_id' => $socialAccount?->id,
                'social_post_id'            => null,
                'comment_id'                => $messageId,
                'file'                      => !empty($files) ? json_encode($files) : '',
            ]);
        }
    }

    /**
     * Handle Facebook & Instagram Post Comments
     */
    private function handleComments($entry, $companyId, $mediaAccount, $socialAccount, $platform)
    {
        $commentValue = collect($entry['changes'])->pluck('value')->first();
        
        if (empty($commentValue)) {
            return;
        }

        // Instagram structural variations vs Facebook field checks
        $isInstagram = ($platform === 'instagram');
        $item = $isInstagram ? 'comment' : ($commentValue['item'] ?? '');
        $verb = $isInstagram ? 'add' : ($commentValue['verb'] ?? '');

        if ($item !== 'comment' || $verb !== 'add') {
            return;
        }

        $commentText = $commentValue['text'] ?? $commentValue['message'] ?? '';
        $commentId = $commentValue['id'] ?? $commentValue['comment_id'] ?? null;

        if (empty($commentText) || str_contains($commentText, '--.')) {
            return;
        }

        // Extract native post ID depending on whether payload is Instagram or Facebook
        $pagePostId = $isInstagram 
            ? ($commentValue['media']['id'] ?? null) 
            : ($commentValue['post_id'] ?? null);

        // Map comment to its original outbound record using the external page post ID string
        $socialPost = SocialPost::where('page_post_id', $pagePostId)->first();

        // Dynamically choose between \Webkul\Core\Models\FacebookChat or InstagramChat
        $chatModelClass = sprintf('\\Webkul\\Core\\Models\\%sChat', ucfirst($platform));

        $modelChat = $chatModelClass::updateOrCreate(
            [
                'to'         => $entry['id'] ?? null,
                'from'       => $commentValue['from']['id'] ?? null,
                'company_id' => $companyId,
            ],
            [
                'profile_name'              => $commentValue['from']['username'] ?? $commentValue['from']['name'] ?? 'Anonymous User',
                'message_id'                => $commentId,
                'social_post_id'            => $socialPost?->id,
                'media_account_id'          => $mediaAccount?->id,
                'social_publish_account_id' => $socialAccount?->id ?? $socialPost?->social_publish_account_id,
            ]
        );

        Chat::create([
            'company_id'                => $companyId,
            'body'                      => $commentText,
            'sender_type'               => 'customer',
            'sender_name'               => $commentValue['from']['username'] ?? $commentValue['from']['name'] ?? 'Anonymous User',
            'applicable_id'             => $modelChat->id,
            'applicable_type'           => $platform,
            'type'                      => 'comment',
            'is_read'                    => 0,
            'file'                      => '',
            'social_post_id'            => $socialPost?->id,
            'media_account_id'          => $mediaAccount?->id,
            'social_publish_account_id' => $socialAccount?->id ?? $socialPost?->social_publish_account_id,
            'comment_id'                => $commentId ?? '',
        ]);
    }

    private function prepareAttachments(array $attachments): array
    {
        $files = [];
        foreach ($attachments as $attachment) {
            $url = $attachment['payload']['url'] ?? null;
            if (!$url) {
                continue;
            }
            $files[] = [
                'url' => $url,
                'ext' => pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION),
            ];
        }
        return $files;
    }

    private function handleException($e, $companyId)
    {
        Log::error('Meta Webhook Error: ' . $e->getMessage(), [
            'line'  => $e->getLine(),
            'file'  => $e->getFile(),
        ]);

        return response('SERVER_ERROR', 500);
    }
}