<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailTemplateController extends Controller
{
    public function index()
    {
        $templates = EmailTemplate::where('user_id', Auth::id())->latest()->paginate(50);

        return view('admin.email.templates.index', compact('templates'));
    }

    public function create()
    {
        return view('admin.email.templates.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $template = EmailTemplate::create(['user_id' => Auth::id(), ...$validated]);

        return redirect()->route('admin.email.templates.index')->with('success', 'Template created.');
    }

    public function edit(EmailTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        return view('admin.email.templates.edit', compact('template'));
    }

    public function update(Request $request, EmailTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $template->update($this->validated($request));

        return redirect()->route('admin.email.templates.index')->with('success', 'Template updated.');
    }

    public function destroy(EmailTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ]);
    }
}
