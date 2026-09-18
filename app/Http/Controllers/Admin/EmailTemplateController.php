<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailTemplate;
use App\Models\EmailMarketing\EmailTemplateVersion;
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

        // The first save is version 1's content too - snapshotted so
        // "Version History" always has at least one entry, even for a
        // template that's never been edited since creation.
        $template->versions()->create([
            'version'      => 1,
            'html_content' => $template->body,
            'editor_type'  => 'code',
            'created_by'   => Auth::id(),
        ]);
        $template->update(['current_version' => 2]);

        return redirect()->route('admin.email.templates.index')->with('success', 'Template created.');
    }

    public function edit(EmailTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $versions = $template->versions;

        return view('admin.email.templates.edit', compact('template', 'versions'));
    }

    public function update(Request $request, EmailTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $validated = $this->validated($request);

        // Snapshot the body as it stands right now, before overwriting it
        // - every save creates real, browsable history instead of
        // silently clobbering the previous version.
        $template->snapshotVersion(Auth::id());
        $template->update($validated);

        return redirect()->route('admin.email.templates.index')->with('success', 'Template updated.');
    }

    /**
     * Restores an older version's HTML as the template's current body -
     * itself snapshotted first, so rolling back is also just a normal,
     * reversible version (never destructive - the version being rolled
     * back FROM is never lost).
     */
    public function restoreVersion(EmailTemplate $template, EmailTemplateVersion $version)
    {
        abort_unless($template->user_id === Auth::id(), 403);
        abort_unless($version->email_template_id === $template->id, 403);

        $template->snapshotVersion(Auth::id());
        $template->update(['body' => $version->html_content]);

        return redirect()->route('admin.email.templates.edit', $template)->with('success', "Restored version {$version->version}.");
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
            'name'     => ['required', 'string', 'max:255'],
            'subject'  => ['required', 'string', 'max:255'],
            'body'     => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
