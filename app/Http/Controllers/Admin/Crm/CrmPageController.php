<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Services\Crm\CrmContext;
use App\Services\Crm\PipelineService;

/** Blade shells: each CRM page mounts one Vue page with its URLs, texts and lookups. */
class CrmPageController extends Controller
{
    public function __construct(private CrmContext $context, private PipelineService $pipelines) {}

    public function overview()
    {
        return $this->page('overview');
    }

    public function contacts()
    {
        return $this->page('contacts');
    }

    public function contact(Contact $contact)
    {
        return $this->page('contact', ['contactId' => $contact->id], $contact->full_name);
    }

    public function companies()
    {
        return $this->page('companies');
    }

    public function company(Company $company)
    {
        return $this->page('company', ['companyId' => $company->id], $company->name);
    }

    public function leads()
    {
        return $this->page('leads');
    }

    public function deals()
    {
        return $this->page('deals');
    }

    public function activities()
    {
        return $this->page('activities');
    }

    public function settings()
    {
        return $this->page('settings');
    }

    public function automations()
    {
        return $this->page('automations');
    }

    public function reports()
    {
        return $this->page('reports');
    }

    private function page(string $page, array $props = [], ?string $title = null)
    {
        $member = request()->attributes->get('workspace_member');

        return view('admin.crm.page', [
            'page' => $page,
            'title' => $title ?? __('crm.nav.'.$page),
            'props' => $props + [
                'canWrite' => ! $member || $member->canUse('crm', true),
                'members' => $this->context->members(),
                'actorId' => $this->context->actorId(),
                'pipelines' => $this->pipelines->all(),
                'options' => self::options(),
                'currency' => request()->attributes->get('workspace')?->currency ?: 'SAR',
                'fields' => \App\Models\Crm\Field::where('entity', 'contact')->orderBy('sort_order')->get(['key', 'label', 'type', 'options']),
            ],
        ]);
    }

    public static function options(): array
    {
        return [
            'lifecycles' => Contact::LIFECYCLES,
            'leadStatuses' => \App\Models\Crm\Lead::STATUSES,
            'disqualifyReasons' => \App\Models\Crm\Lead::DISQUALIFY_REASONS,
            'activityTypes' => ['task', 'call', 'meeting', 'note', 'email'],
            'sources' => ['instagram', 'whatsapp', 'facebook', 'tiktok', 'snapchat', 'x', 'linkedin', 'google_ads', 'website', 'email', 'referral', 'phone', 'walk_in', 'other'],
        ];
    }
}
