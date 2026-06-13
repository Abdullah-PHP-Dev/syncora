<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Category;
use Illuminate\Http\Request;

class HomeController extends Controller
{
	public function index()
	{
		return view('front.pages.home', [
			'categories' => Category::take(8)->get(),
			'ads' => Ad::latest()->take(12)->get(),
		]);
	}
}
