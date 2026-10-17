<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\PackagePresentationService;

class PricingController extends Controller
{
    public function __invoke(PackagePresentationService $presentation)
    {
        return view('front.pages.pricing', $presentation->catalog() + compact('presentation'));
    }
}
