<?php
namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:200']]);
        $subscribers = User::role('seller')->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'customer_support']))
            ->with('subscription.bundle')
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->latest()->paginate(20)->withQueryString();
        return view('team.subscribers', compact('subscribers'));
    }
}
