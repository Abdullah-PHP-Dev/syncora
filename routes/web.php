<?php

use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PostAccountController;
use App\Http\Controllers\Admin\SocialAccountController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\MessageChannelController;
use App\Http\Controllers\Admin\PostCommentController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\AdminAPIController;
use App\Http\Controllers\Admin\AdCampaignController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\PostCategoryController;
use App\Http\Controllers\Admin\EmailMarketingController;
use App\Http\Controllers\Admin\EmailListController;
use App\Http\Controllers\Admin\EmailSubscriberController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\EmailCampaignController;
use App\Http\Controllers\EmailUnsubscribeController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\HelpCenterController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\KnowledgeBaseController;
use App\Http\Controllers\Admin\CopilotController;


Route::group(['prefix' => LaravelLocalization::setLocale(), 'middleware' => [
	'web',
	LaravelLocalizationRoutes::class,
	LocaleSessionRedirect::class,
	LocaleCookieRedirect::class,
	LaravelLocalizationRedirectFilter::class,
	LaravelLocalizationViewPath::class,
]], function () {





	/*
	|--------------------------------------------------------------------------
	| Website
	|--------------------------------------------------------------------------
	*/

	Route::view('/', 'front.pages.welcome')->name('home');

	Route::get('/product', function () {
		return view('front.pages.product');
	})->name('product');


	Route::get('/ai-copilot', function () {
		return view('product.ai-copilot');
	})->name('ai-copilot');


	Route::get('/channels', function () {
		return view('product.channels');
	})->name('channels');


	Route::get('/tools', function () {
		return view('product.tools');
	})->name('tools');


	/*
	|--------------------------------------------------------------------------
	| Pricing
	|--------------------------------------------------------------------------
	*/

	Route::get('/pricing', function () {
		return view('pricing');
	})->name('pricing');


	/*
	|--------------------------------------------------------------------------
	| Company
	|--------------------------------------------------------------------------
	*/

	Route::get('/about', function () {
		return view('about');
	})->name('about');


	Route::get('/contact', function () {
		return view('contact');
	})->name('contact');


	/*
	|--------------------------------------------------------------------------
	| Resources
	|--------------------------------------------------------------------------
	*/

	Route::get('/guides', function () {
		return view('guides');
	})->name('guides');


	Route::get('/help', function () {
		return view('help');
	})->name('help');


	Route::get('/api', function () {
		return view('api');
	})->name('api');


	/*
	|--------------------------------------------------------------------------
	| Legal
	|--------------------------------------------------------------------------
	*/

	Route::get('/privacy', function () {
		return view('privacy');
	})->name('privacy');


	Route::get('/terms', function () {
		return view('terms');
	})->name('terms');


	/*
	|--------------------------------------------------------------------------
	| Public post share preview - deliberately OUTSIDE the auth group below.
	| Snap's Creative Kit share flow (and any other social share button)
	| fetches this URL's og and snapchat meta tags server-side, with no
	| session cookie - if this required login it would just see a redirect
	| to /login and the share would show no image/caption at all. Only
	| exposes a post's own public-facing content (caption + first media
	| item), nothing account/owner-identifying.
	|--------------------------------------------------------------------------
	*/
	Route::get('share/posts/{post}', [PostController::class, 'sharePreview'])->name('posts.share');


	/*Route::view('/about', 'front.pages.about');
	Route::view('/services', 'front.pages.services');
	Route::view('/product', 'front.pages.product');
	Route::view('/pricing', 'front.pages.pricing');
	Route::view('/terms', 'front.pages.terms')->name('front.terms');
	Route::view('/privacy', 'front.pages.privacy')->name('front.privacy');
	Route::get('/r2-upload', [\App\Http\Controllers\R2Controller::class, 'index']);
	Route::post('/r2-upload', [\App\Http\Controllers\R2Controller::class, 'upload'])->name('r2.upload');*/


	Route::middleware(['auth'])->group(function () {
		/*
		|--------------------------------------------------------------------------
		| DASHBOARD (NO SUBSCRIPTION REQUIRED)
		|--------------------------------------------------------------------------
		*/
		Route::prefix('admin')
			->name('admin.')
			->group(function () {
				/*
		|--------------------------------------------------------------------------
		| SUBSCRIPTION FLOW (ALWAYS ACCESSIBLE)
		|--------------------------------------------------------------------------
		*/
				// subscription flow (NO middleware restriction)
				Route::get('/subscription/select', [SubscriptionController::class, 'select']);
				Route::get('/subscription/plans', [SubscriptionController::class, 'plans']);
				Route::get('/subscription/checkout', [SubscriptionController::class, 'showCheckout'])->name('subscription.checkout');
				Route::post('/subscription/checkout', [SubscriptionController::class, 'checkoutProcess'])->name('subscription.checkout.process');
				Route::get('/subscription/checkout-data', [SubscriptionController::class, 'checkoutData'])->name('subscription.checkout.data');

			/*	Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])
					->name('subscription.checkout.process');*/
				Route::post('/subscription/activate', [SubscriptionController::class, 'activate']);
				Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);

				Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'dashboard'])
					->name('dashboard');

				Route::view('/dashboard/crm', 'admin.crm-dashboard')
					->name('crm-dashboard');

				/*
				|--------------------------------------------------------------------------
				| SUPPORT: SYSTEM FAQ (admin-role only), HELP CENTER + TICKETS (every seller)
				|--------------------------------------------------------------------------
				| Deliberately outside the ->middleware(['subscription']) group below -
				| EnsureActiveSubscription aborts(403) any non-'seller' user outright,
				| which would make the admin-only FAQ screens unreachable, and a
				| seller whose subscription lapsed should still be able to reach
				| support. See FaqController/TicketController docblocks.
				*/
				Route::get('faqs', [FaqController::class, 'index'])->name('faqs.index');
				Route::post('faqs', [FaqController::class, 'store'])->name('faqs.store');
				Route::put('faqs/{faq}', [FaqController::class, 'update'])->name('faqs.update');
				Route::delete('faqs/{faq}', [FaqController::class, 'destroy'])->name('faqs.destroy');
				Route::post('faqs/categories', [FaqController::class, 'storeCategory'])->name('faqs.categories.store');

				Route::get('help-center', [HelpCenterController::class, 'index'])->name('help-center.index');

				Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
				Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
				Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
				Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
				Route::post('tickets/{ticket}/messages', [TicketController::class, 'storeMessage'])->name('tickets.messages.store');
				Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.status');
			});


		/*
		|--------------------------------------------------------------------------
		| PROTECTED SAAS MODULES (SUBSCRIPTION REQUIRED)
		|--------------------------------------------------------------------------
		*/
		Route::middleware(['subscription'])
			->prefix('admin')
			->name('admin.')
			->group(function () {
				// ADS
				Route::resource('/platform/ads', AdController::class);
				Route::get('ads/dashboard', [AdController::class, 'dashboard'])
					->name('ads.dashboard');

				Route::get('ads/{platform}/redirect', [AdController::class, 'redirect'])
					->name('ads.redirect');

				Route::get('ads/{platform}/callback', [AdController::class, 'callback'])->name('ads.platform.callback');

				// Registered ahead of the resource route below so this
				// literal segment isn't swallowed by the resource's
				// GET ads/{platform}/campaigns/{campaign} (show) route,
				// which would otherwise treat "create-new" as a campaign ID.
				Route::get('ads/{platform}/campaigns/create-new', [AdCampaignController::class, 'createNew'])
					->name('ads.campaigns.create_new');

				Route::resource('ads/{platform}/campaigns', AdCampaignController::class)
					->names('ads.campaigns');

				Route::patch('ads/{platform}/campaigns/{id}/status', [AdCampaignController::class, 'updateStatus'])
					->name('ads.campaigns.status');

				Route::get('ads/{platform}/identities', [AdCampaignController::class, 'identities'])
					->name('ads.identities');


				// INTEGRATIONS - pixels/analytics/AI/ads services with a
				// pasted ID or API key each (no OAuth - see
				// Integration::getCredentialFieldsAttribute()), scoped per
				// user like everything else here.
				Route::get('integrations', [IntegrationController::class, 'index'])
					->name('integrations.index');
				Route::post('integrations/{integration}', [IntegrationController::class, 'store'])
					->name('integrations.store');
				Route::delete('integrations/connections/{userIntegration}', [IntegrationController::class, 'destroy'])
					->name('integrations.destroy');


				// POSTS
				Route::get('posts/{platform}/redirect', [PostController::class, 'redirect'])
					->name('posts.redirect');
				Route::get('posts/dashboard', [PostController::class, 'dashboard'])->defaults('_config', ['view' => 'admin.posts.dashboard'])->name('posts.dashboard');
				Route::get('posts/listing', [PostController::class, 'index_vue'])->name('posts.index');
				Route::get('posts/data', [PostController::class, 'index'])->name('posts.data');
				Route::get('posts/{post}/preview/{platform}', [PostController::class, 'preview'])->name('posts.preview');
				Route::post('posts/quick', [PostController::class, 'quickStore'])->name('posts.quick');
				// New Vue-based Create Post page (PostComposer.vue) -
				// deliberately a separate route from admin.posts.create
				// (still fully intact below via Route::resource) rather
				// than replacing that page's Blade view outright - that
				// page is 2000+ lines with real, working pieces (the
				// WhatsApp Embedded Signup flow, for one) this redesign
				// doesn't attempt to carry over, and silently dropping
				// them wasn't part of what was asked. Submits to the same
				// admin.posts.store PostController::store() the legacy
				// page already uses.
				Route::get('posts/composer', [PostController::class, 'composer'])->name('posts.composer');
				Route::post('posts/generate-ai-content', [PostController::class, 'generateAiContent'])->name('posts.generate-ai-content');
				Route::post('posts/generate-ai-image', [PostController::class, 'generateAiImage'])->name('posts.generate-ai-image');
					Route::get('posts/{post}/quick-view', [PostController::class, 'quickView'])->name('posts.quick-view');
				Route::post('posts/listing/comments/{comment}/replies', [PostController::class, 'storeReply'])->name('posts.comments.reply');
				Route::post('posts/listing/{post}/comments', [PostController::class, 'storeComment'])->name('posts.comments.store');
				Route::get('posts', [PostController::class, 'dashboard']);
				Route::resource('posts', PostController::class);
				Route::post('post-accounts/whatsapp', [PostAccountController::class, 'storeWhatsApp'])
					->name('post-accounts.whatsapp.store');
				Route::post('post-accounts/whatsapp/embedded', [PostAccountController::class, 'storeWhatsappEmbedded'])
					->name('post-accounts.whatsapp.embedded');

				Route::get('post-accounts/instagram/redirect', [PostAccountController::class, 'redirectInstagram'])
					->name('post-accounts.instagram.redirect');
				Route::get('post-accounts/instagram/callback', [PostAccountController::class, 'callbackInstagram'])
					->name('post-accounts.instagram.callback');
				Route::get('post-accounts/threads/redirect', [PostAccountController::class, 'redirectThreads'])
					->name('post-accounts.threads.redirect');
				Route::get('post-accounts/threads/callback', [PostAccountController::class, 'callbackThreads'])
					->name('post-accounts.threads.callback');
				Route::get('post-accounts/pinterest/redirect', [PostAccountController::class, 'redirectPinterest'])
					->name('post-accounts.pinterest.redirect');
				Route::get('post-accounts/pinterest/callback', [PostAccountController::class, 'callbackPinterest'])
					->name('post-accounts.pinterest.callback');
				Route::get('post-accounts/x/redirect', [PostAccountController::class, 'redirectX'])
					->name('post-accounts.x.redirect');
				Route::get('post-accounts/x/callback', [PostAccountController::class, 'callbackX'])
					->name('post-accounts.x.callback');
				Route::delete('post-accounts/{account}', [PostAccountController::class, 'destroy'])
					->name('post-accounts.destroy');

				// Unified combined-consent connect flow (posting + messaging +
				// ads scopes in one redirect) for Facebook, Google, LinkedIn,
				// and TikTok - the platforms whose OAuth model supports
				// requesting all three at once - see SocialAuthService. This
				// is now the ONLY connect route for these four platforms:
				// it replaced their separate post-accounts.*/messaging.auth.*
				// entries (removed below), since every account connected
				// through either used to upsert into the same social_accounts
				// row anyway. Every other platform keeps its existing
				// dedicated route, either because it has no combined-scope
				// option (TikTok Ads has its own separate OAuth app - see
				// ads.redirect) or because it's a genuinely different
				// product (Google Chat vs. YouTube/Business Profile).
				Route::get('social-accounts/{platform}/redirect', [SocialAccountController::class, 'redirect'])
					->name('social-accounts.redirect');
				Route::get('social-accounts/{platform}/callback', [SocialAccountController::class, 'callback'])
					->name('social-accounts.callback');

				Route::resource('categories', PostCategoryController::class);


				// CHATS - unified messaging inbox (Facebook Messenger,
				// Instagram Direct, WhatsApp, Telegram, X DMs)
				Route::get('chats/dashboard', [ChatController::class, 'dashboard'])
					->name('chats.dashboard');
				Route::get('platform/chats/{conversation}', [ChatController::class, 'show'])
					->name('chats.show');
				Route::post('platform/chats', [ChatController::class, 'store'])
					->name('chats.store');
				Route::patch('platform/chats/{conversation}/read', [ChatController::class, 'markRead'])
					->name('chats.read');
				Route::delete('platform/chats/{conversation}', [ChatController::class, 'destroy'])
					->name('chats.destroy');
				Route::patch('platform/chats/messages/{message}', [ChatController::class, 'updateMessage'])
					->name('chats.messages.update');
				Route::delete('platform/chats/messages/{message}', [ChatController::class, 'destroyMessage'])
					->name('chats.messages.destroy');

				// AI COPILOT - Phase 3 of the AI Copilot + FAQ + Ticket
				// System BRD. Scores a conversation's latest customer
				// message against the seller's own Knowledge Base - see
				// AiCopilotService/CopilotController docblocks for the
				// "suggests, never auto-sends" scope boundary.
				Route::post('platform/chats/{conversation}/copilot/find-answer', [CopilotController::class, 'findAnswer'])
					->name('chats.copilot.find-answer');
				Route::post('platform/copilot-messages/{copilotMessage}/feedback', [CopilotController::class, 'feedback'])
					->name('chats.copilot.feedback');

				// NOTIFICATION CENTER - combined unread Comments + Messages
				// badge/dropdown in the navbar. Conversation-type items reuse
				// chats.read above; comments needed their own mark-read route
				// since PostComment had no read-tracking before this.
				Route::get('notifications', [NotificationController::class, 'index'])
					->name('notifications.index');
				Route::patch('platform/comments/{comment}/read', [NotificationController::class, 'markCommentRead'])
					->name('comments.read');

				// CHATS - connected channel management (separate from the
				// conversations themselves)
				Route::get('chats/channels', [MessageChannelController::class, 'index'])
					->name('chats.channels');
				// Facebook Messenger connects through social-accounts.redirect
				// now (platform=facebook) - see the comment above that route.
				Route::get('messaging/auth/instagram/redirect', [MessageChannelController::class, 'redirectInstagram'])
					->name('messaging.auth.instagram.redirect');
				Route::get('messaging/auth/instagram/callback', [MessageChannelController::class, 'callbackInstagram'])
					->name('messaging.auth.instagram.callback');
				Route::get('messaging/auth/x/redirect', [MessageChannelController::class, 'redirectX'])
					->name('messaging.auth.x.redirect');
				Route::get('messaging/auth/x/callback', [MessageChannelController::class, 'callbackX'])
					->name('messaging.auth.x.callback');
				Route::get('messaging/auth/tiktok/redirect', [MessageChannelController::class, 'redirectTiktok'])
					->name('messaging.auth.tiktok.redirect');
				Route::get('messaging/auth/tiktok/callback', [MessageChannelController::class, 'callbackTiktok'])
					->name('messaging.auth.tiktok.callback');
				Route::post('messaging/channels/telegram', [MessageChannelController::class, 'storeTelegram'])
					->name('messaging.channels.telegram.store');
				Route::post('messaging/channels/whatsapp', [MessageChannelController::class, 'storeWhatsApp'])
					->name('messaging.channels.whatsapp.store');
				Route::post('messaging/channels/line', [MessageChannelController::class, 'storeLine'])
					->name('messaging.channels.line.store');
				Route::post('messaging/channels/discord', [MessageChannelController::class, 'storeDiscord'])
					->name('messaging.channels.discord.store');
				Route::get('messaging/auth/discord/redirect', [MessageChannelController::class, 'redirectDiscord'])
					->name('messaging.auth.discord.redirect');
				Route::get('messaging/channels/discord', [MessageChannelController::class, 'callbackDiscord'])
					->name('messaging.channels.discord.callback');
				Route::post('messaging/channels/teams', [MessageChannelController::class, 'storeTeams'])
					->name('messaging.channels.teams.store');
				Route::post('messaging/channels/google-chat', [MessageChannelController::class, 'storeGoogleChat'])
					->name('messaging.channels.google_chat.store');
				Route::get('messaging/auth/google-chat/redirect', [MessageChannelController::class, 'redirectGoogleChatOAuth'])
					->name('messaging.auth.google_chat.redirect');
				Route::get('messaging/auth/google-chat/callback', [MessageChannelController::class, 'callbackGoogleChatOAuth'])
					->name('messaging.auth.google_chat.callback');
				Route::post('messaging/channels/matrix', [MessageChannelController::class, 'storeMatrix'])
					->name('messaging.channels.matrix.store');
				Route::post('messaging/auth/zalo/redirect', [MessageChannelController::class, 'redirectZalo'])
					->name('messaging.auth.zalo.redirect');
				Route::get('messaging/auth/zalo/callback', [MessageChannelController::class, 'callbackZalo'])
					->name('messaging.auth.zalo.callback');
				Route::get('messaging/auth/slack/redirect', [MessageChannelController::class, 'redirectSlack'])
					->name('messaging.auth.slack.redirect');
				Route::get('messaging/auth/slack/callback', [MessageChannelController::class, 'callbackSlack'])
					->name('messaging.auth.slack.callback');
				Route::delete('messaging/channels/{channel}', [MessageChannelController::class, 'destroy'])
					->name('messaging.channels.destroy');


				// COMMENTS
				Route::resource('/platform/comments', PostCommentController::class);
				Route::get('comments/dashboard', [PostCommentController::class, 'dashboard'])
					->name('comments.dashboard');


				// KNOWLEDGE BASE - seller's own business FAQ (Phase 2 of the
				// AI Copilot + FAQ + Ticket System BRD). Scoped to Auth::id()
				// throughout - see KnowledgeBaseController's docblock.
				Route::get('knowledge-base', [KnowledgeBaseController::class, 'index'])->name('knowledge-base.index');
				Route::post('knowledge-base', [KnowledgeBaseController::class, 'store'])->name('knowledge-base.store');
				Route::put('knowledge-base/{faq}', [KnowledgeBaseController::class, 'update'])->name('knowledge-base.update');
				Route::delete('knowledge-base/{faq}', [KnowledgeBaseController::class, 'destroy'])->name('knowledge-base.destroy');
				Route::post('knowledge-base/categories', [KnowledgeBaseController::class, 'storeCategory'])->name('knowledge-base.categories.store');


				// EMAIL MARKETING
				Route::get('email/dashboard', [EmailMarketingController::class, 'dashboard'])
					->name('email.dashboard');

				Route::get('email/lists', [EmailListController::class, 'index'])->name('email.lists.index');
				Route::post('email/lists', [EmailListController::class, 'store'])->name('email.lists.store');
				Route::patch('email/lists/{list}', [EmailListController::class, 'update'])->name('email.lists.update');
				Route::delete('email/lists/{list}', [EmailListController::class, 'destroy'])->name('email.lists.destroy');

				Route::get('email/lists/{list}/subscribers', [EmailSubscriberController::class, 'index'])->name('email.lists.subscribers.index');
				Route::post('email/lists/{list}/subscribers', [EmailSubscriberController::class, 'store'])->name('email.lists.subscribers.store');
				Route::post('email/lists/{list}/subscribers/import', [EmailSubscriberController::class, 'import'])->name('email.lists.subscribers.import');
				Route::delete('email/lists/{list}/subscribers/{subscriber}', [EmailSubscriberController::class, 'destroy'])->name('email.lists.subscribers.destroy');

				Route::resource('email/templates', EmailTemplateController::class)
					->except(['show'])
					->names('email.templates');

				Route::resource('email/campaigns', EmailCampaignController::class)
					->except(['show'])
					->names('email.campaigns');
				Route::get('email/campaigns/{campaign}', [EmailCampaignController::class, 'show'])->name('email.campaigns.show');
				Route::post('email/campaigns/{campaign}/send', [EmailCampaignController::class, 'sendNow'])->name('email.campaigns.send');


				// SYSTEM
				Route::resource('/apis', AdminAPIController::class);
				Route::resource('/profiles', ProfileController::class);
			});
	});
	require __DIR__ . '/auth.php';
});

// Public unsubscribe link embedded in every campaign email - deliberately
// outside the LaravelLocalization group above so its URL is stable and
// never gains/loses a locale prefix depending on app config, since these
// links are baked into emails that may have already been sent. See
// EmailUnsubscribeController and the CSRF exemption in bootstrap/app.php.
Route::get('/email/unsubscribe/{token}', [EmailUnsubscribeController::class, 'show'])->name('email.unsubscribe');
Route::post('/email/unsubscribe/{token}', [EmailUnsubscribeController::class, 'confirm'])->name('email.unsubscribe.confirm');
