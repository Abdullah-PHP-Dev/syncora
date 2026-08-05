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
use App\Http\Controllers\Admin\ChatController;
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


Route::group(['prefix' => LaravelLocalization::setLocale(), 'middleware' => [
	'web',
	LaravelLocalizationRoutes::class,
	LocaleSessionRedirect::class,
	LocaleCookieRedirect::class,
	LaravelLocalizationRedirectFilter::class,
	LaravelLocalizationViewPath::class,
]], function () {

	Route::view('/', 'front.pages.home');
	Route::view('/about', 'front.pages.about');
	Route::view('/services', 'front.pages.services');
	Route::view('/product', 'front.pages.product');
	Route::view('/pricing', 'front.pages.pricing');
	Route::view('/terms', 'front.pages.terms')->name('front.terms');
	Route::view('/privacy', 'front.pages.privacy')->name('front.privacy');
	Route::get('/r2-upload', [\App\Http\Controllers\R2Controller::class, 'index']);
	Route::post('/r2-upload', [\App\Http\Controllers\R2Controller::class, 'upload'])->name('r2.upload');


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
				Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout']);
				Route::post('/subscription/activate', [SubscriptionController::class, 'activate']);
				Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);

				Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'dashboard'])
					->name('dashboard');

				Route::view('/dashboard/crm', 'admin.crm-dashboard')
					->name('crm-dashboard');
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

				Route::get('ads/{platform}/callback', [AdController::class, 'callback']);

				Route::resource('ads/{platform}/campaigns', AdCampaignController::class)
					->names('ads.campaigns');

				Route::patch('ads/{platform}/campaigns/{id}/status', [AdCampaignController::class, 'updateStatus'])
					->name('ads.campaigns.status');


				// POSTS
				Route::get('posts/{platform}/redirect', [PostController::class, 'redirect'])
					->name('posts.redirect');
				Route::get('posts/dashboard', [PostController::class, 'dashboard'])->defaults('_config', ['view' => 'admin.posts.dashboard'])->name('posts.dashboard');
				Route::get('posts/listing', [PostController::class, 'index_vue']);
				Route::get('posts/data', [PostController::class, 'index'])->name('posts.data');
				Route::get('posts/{post}/preview/{platform}', [PostController::class, 'preview'])->name('posts.preview');
				Route::post('posts/listing/comments/{comment}/replies', [PostController::class, 'storeReply'])->name('posts.comments.reply');
				Route::post('posts/listing/{post}/comments', [PostController::class, 'storeComment'])->name('posts.comments.store');
				Route::get('posts', [PostController::class, 'dashboard']);
				Route::resource('posts', PostController::class);
				Route::post('post-accounts/whatsapp', [PostAccountController::class, 'storeWhatsApp'])
					->name('post-accounts.whatsapp.store');
				Route::post('post-accounts/whatsapp/embedded', [PostAccountController::class, 'storeWhatsappEmbedded'])
					->name('post-accounts.whatsapp.embedded');
				Route::get('post-accounts/meta/redirect', [PostAccountController::class, 'redirectMeta'])
					->name('post-accounts.meta.redirect');
				Route::get('post-accounts/meta/callback', [PostAccountController::class, 'callbackMeta'])
					->name('post-accounts.meta.callback');
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
				Route::get('post-accounts/linkedin/redirect', [PostAccountController::class, 'redirectLinkedin'])
					->name('post-accounts.linkedin.redirect');
				Route::get('post-accounts/linkedin/callback', [PostAccountController::class, 'callbackLinkedin'])
					->name('post-accounts.linkedin.callback');
				Route::get('post-accounts/tiktok/redirect', [PostAccountController::class, 'redirectTiktok'])
					->name('post-accounts.tiktok.redirect');
				Route::get('post-accounts/tiktok/callback', [PostAccountController::class, 'callbackTiktok'])
					->name('post-accounts.tiktok.callback');
				Route::get('post-accounts/google/redirect', [PostAccountController::class, 'redirectGoogle'])
					->name('post-accounts.google.redirect');
				Route::get('post-accounts/google/callback', [PostAccountController::class, 'callbackGoogle'])
					->name('post-accounts.google.callback');
				Route::delete('post-accounts/{account}', [PostAccountController::class, 'destroy'])
					->name('post-accounts.destroy');
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

				// CHATS - connected channel management (separate from the
				// conversations themselves)
				Route::get('chats/channels', [MessageChannelController::class, 'index'])
					->name('chats.channels');
				Route::get('messaging/auth/meta/redirect', [MessageChannelController::class, 'redirectMeta'])
					->name('messaging.auth.meta.redirect');
				Route::get('messaging/auth/meta/callback', [MessageChannelController::class, 'callbackMeta'])
					->name('messaging.auth.meta.callback');
				Route::get('messaging/auth/x/redirect', [MessageChannelController::class, 'redirectX'])
					->name('messaging.auth.x.redirect');
				Route::get('messaging/auth/x/callback', [MessageChannelController::class, 'callbackX'])
					->name('messaging.auth.x.callback');
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
