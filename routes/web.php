<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

Route::group([
	             'prefix' => LaravelLocalization::setLocale(),
	             'middleware' => [
		             'web'
	             ]
             ], function () {

	Route::view('/', 'front.pages.home');
	Route::view('/about', 'front.pages.about');
	Route::view('/services', 'front.pages.services');
	Route::view('/product', 'front.pages.product');
	Route::view('/pricing', 'front.pages.pricing');



Route::middleware('auth')->group(function () {
	Route::get('/users',[\App\Http\Controllers\Admin\DashboardController::class, 'dashboard'])->name('users.index');
	Route::get('/admin/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'dashboard'])->name('dashboard');
	Route::view('/admin/dashboard/crm', 'admin.crm-dashboard')->name('crm-dashboard');

Route::prefix('layouts')->group(function () {
	Route::view('/without-menu', 'layouts.without-menu')->name('layouts.without-menu');
	Route::view('/without-navbar', 'layouts.without-navbar')->name('layouts.without-navbar');
	Route::view('/fluid', 'layouts.fluid')->name('layouts.fluid');
	Route::view('/container', 'layouts.container')->name('layouts.container');
	Route::view('/blank', 'layouts.blank')->name('layouts.blank');
});

// Front Pages
Route::prefix('front-pages')->group(function () {
	Route::view('/landing', 'front-pages.landing')->name('front.landing');
	Route::view('/pricing', 'front-pages.pricing')->name('front.pricing');
	Route::view('/payment', 'front-pages.payment')->name('front.payment');
	Route::view('/checkout', 'front-pages.checkout')->name('front.checkout');
	Route::view('/help-center', 'front-pages.help-center')->name('front.help-center');
});

// Apps
Route::prefix('apps')->group(function () {
	Route::view('/email', 'apps.email')->name('apps.email');
	Route::view('/chat', 'apps.chat')->name('apps.chat');
	Route::view('/calendar', 'apps.calendar')->name('apps.calendar');
	Route::view('/kanban', 'apps.kanban')->name('apps.kanban');
});

// Account Settings
Route::prefix('account')->group(function () {
	Route::view('/account', 'account.account')->name('account.account');
	Route::view('/notifications', 'account.notifications')->name('account.notifications');
	Route::view('/connections', 'account.connections')->name('account.connections');
});

// Auth pages
Route::prefix('auth')->group(function () {
	Route::view('/login', 'auth.login')->name('auth.login');
	Route::view('/register', 'auth.register')->name('auth.register');
	Route::view('/forgot-password', 'auth.forgot-password')->name('auth.forgot-password');
});

// Components / UI
Route::prefix('ui')->group(function () {
	Route::view('/accordion', 'ui.accordion')->name('ui.accordion');
	Route::view('/alerts', 'ui.alerts')->name('ui.alerts');
	Route::view('/buttons', 'ui.buttons')->name('ui.buttons');
	Route::view('/modals', 'ui.modals')->name('ui.modals');
});

// Tables
Route::view('/tables', 'tables.basic')->name('tables');

// Cards
Route::view('/cards', 'cards.basic')->name('cards.basic');
});
require __DIR__.'/auth.php';

});
