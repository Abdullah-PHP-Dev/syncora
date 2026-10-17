<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\PackagePresentationService;

class HomeController extends Controller
{
    public function __invoke(PackagePresentationService $presentation)
    {
        $yearly = request('billing') === 'yearly';

        return view('front.pages.home_modern', $presentation->catalog() + compact('presentation', 'yearly'));
    }
}
