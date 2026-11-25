<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;

class ProfileController extends Controller
{

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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return view('admin.profile.show', $user);
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
    public function update(ProfileUpdateRequest $request, $id)
    {
        abort_unless((string) $request->user()->id === (string) $id, 403);
        $data = $request->validated();
        unset($data['current_password']);
        $changingPassword = ! empty($data['password']);
        if ($changingPassword) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user = $request->user();
        $user->update($data);
        if ($changingPassword) {
            // Sign out every other session; the alert email goes out from UserObserver.
            $user->forceFill(['remember_token' => \Illuminate\Support\Str::random(60)])->saveQuietly();
            if (config('session.driver') === 'database') {
                \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)
                    ->where('id', '!=', $request->session()->getId())->delete();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
