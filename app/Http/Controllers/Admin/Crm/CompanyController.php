<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use App\Services\Crm\CrmContext;
use App\Services\Crm\Timeline;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    use Presents;

    public function __construct(private CrmContext $context, private Timeline $timeline) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $companies = Company::with('owner:id,name')->withCount(['contacts', 'deals as open_deals_count' => fn ($d) => $d->where('status', 'open')])
            ->withSum(['deals as open_value' => fn ($d) => $d->where('status', 'open')], 'value')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('domain', 'like', "%{$q}%")))
            ->orderBy('name')->paginate($request->integer('per_page', 25) === 100 ? 100 : 25);

        return response()->json([
            'data' => $companies->getCollection()->map(fn ($c) => $this->row($c)),
            'meta' => ['total' => $companies->total(), 'page' => $companies->currentPage(), 'last_page' => $companies->lastPage()],
        ]);
    }

    public function store(Request $request)
    {
        $company = Company::create($this->validated($request) + ['created_by' => $this->context->actorId()]);
        $this->timeline->event('company_created', ['company_id' => $company->id]);

        return response()->json(['data' => $this->row($company->load('owner'))], 201);
    }

    public function show(Company $company)
    {
        $company->load(['owner:id,name', 'contacts.owner:id,name', 'deals.stage', 'deals.owner:id,name', 'deals.contact']);

        return response()->json([
            'company' => $this->row($company) + ['phone' => $company->phone, 'email' => $company->email, 'notes' => $company->notes, 'owner_id' => $company->owner_id],
            'contacts' => $company->contacts->map(fn ($c) => $this->contactRow($c)),
            'deals' => $company->deals->map(fn ($d) => $this->dealRow($d)),
            'timeline' => $company->activities()->with(['author:id,name', 'owner:id,name'])->latest()->limit(50)->get()->map(fn ($a) => $this->activityRow($a)),
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $company->update($this->validated($request, true));

        return response()->json(['data' => $this->row($company->load('owner'))]);
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return response()->json(['ok' => true]);
    }

    private function row(Company $c): array
    {
        return [
            'id' => $c->id, 'name' => $c->name, 'domain' => $c->domain, 'industry' => $c->industry, 'city' => $c->city, 'country' => $c->country,
            'tags' => $c->tags ?? [], 'contacts_count' => $c->contacts_count, 'open_deals_count' => $c->open_deals_count,
            'open_value' => (float) ($c->open_value ?? 0), 'owner' => $c->owner ? ['id' => $c->owner->id, 'name' => $c->owner->name] : null,
            'url' => route('admin.crm.companies.show', $c),
        ];
    }

    private function validated(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:160'], 'domain' => ['nullable', 'string', 'max:160'],
            'industry' => ['nullable', 'string', 'max:80'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:160'],
            'city' => ['nullable', 'string', 'max:80'], 'country' => ['nullable', 'string', 'max:80'], 'notes' => ['nullable', 'string', 'max:5000'],
            'owner_id' => ['nullable', Rule::in($this->context->memberIds())],
            'tags' => ['nullable', 'array', 'max:20'], 'tags.*' => ['string', 'max:40'],
        ]);
    }
}
