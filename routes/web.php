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
	Route::get('/admin/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'dashboard'])->name('dashboard');
});
require __DIR__.'/auth.php';

});
