<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Contact;
use App\Services\Crm\ContactImport;
use App\Services\Crm\CrmContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** C-07..C-09 import and C-06 duplicates & merge. */
class ImportController extends Controller
{
    use Presents;

    public function __construct(private ContactImport $import, private CrmContext $context) {}

    public function preview(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

        return response()->json($this->import->preview($request->file('file')));
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'], 'mapping' => ['required', 'array'], 'mapping.*' => ['nullable', Rule::in(ContactImport::FIELDS)],
            'duplicates' => ['required', Rule::in(['skip', 'update', 'create'])],
            'lifecycle' => ['nullable', Rule::in(Contact::LIFECYCLES)], 'source' => ['nullable', 'string', 'max:40'],
            'owner_id' => ['nullable', Rule::in($this->context->memberIds())], 'tags' => ['nullable', 'array'], 'tags.*' => ['string', 'max:40'],
        ]);
        abort_unless(in_array('first_name', $data['mapping'], true) || in_array('full_name', $data['mapping'], true)
            || in_array('phone', $data['mapping'], true) || in_array('email', $data['mapping'], true), 422, __('crm.import.map_required'));

        return response()->json($this->import->import($data['token'], array_filter($data['mapping']), $data['duplicates'],
            array_filter(['lifecycle' => $data['lifecycle'] ?? null, 'source' => $data['source'] ?? null, 'owner_id' => $data['owner_id'] ?? null, 'tags' => $data['tags'] ?? []])));
    }

    public function duplicates()
    {
        // array_merge, not "+": the formatted rows must replace the raw models (which have no "name").
        return response()->json(['data' => collect($this->import->duplicateGroups())->map(fn ($g) => array_merge($g, [
            'contacts' => $g['contacts']->map(fn ($c) => $this->contactRow($c) + ['deals_count' => $c->deals_count, 'leads_count' => $c->leads_count])->values(),
        ]))]);
    }

    public function merge(Request $request)
    {
        $data = $request->validate(['primary_id' => ['required', 'integer'], 'ids' => ['required', 'array', 'min:1', 'max:20'], 'ids.*' => ['integer']]);
        $primary = Contact::withoutGlobalScope('visibility')->findOrFail($data['primary_id']);

        return response()->json(['data' => $this->contactRow($this->import->merge($primary, $data['ids'])->load('owner', 'company'))]);
    }
}
