<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PostCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Admin\PostCategoryRequest;


class PostCategoryController extends Controller
{
    protected $category;

    public function __construct(PostCategory $category)
    {
        $this->category = $category;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PostCategoryRequest $request)
    {
        $validated = $request->validated();

        $category = $this->category::Create([
            'user_id' => Auth::user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $category,
            'redirect_url' => route('admin.categories.index')
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
