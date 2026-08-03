<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class R2Controller extends Controller
{
	public function index()
	{
		return view('r2-upload');
	}

	public function upload(Request $request)
	{
		$request->validate([
			'image' => 'required|image|max:2048',
		]);

		$path = Storage::disk('r2')->putFile(
			'uploads',
			$request->file('image')
		);
	dd($path);
		return back()->with([
			'success' => 'Image uploaded successfully.',
			'path'    => $path,
			'url'     => Storage::disk('r2')->url($path),
		]);
	}
}
