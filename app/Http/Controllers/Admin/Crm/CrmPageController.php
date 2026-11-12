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

    public function lead(\App\Models\Crm\Lead $lead)
    {
        return $this->page('lead', ['leadId' => $lead->id], $lead->contact?->full_name ?? __('crm.nav.leads'));
    }

    public function deal(\App\Models\Crm\Deal $deal)
    {
        return $this->page('deal', ['dealId' => $deal->id], $deal->name);
    }

    public function insights()
    {
        return $this->page('insights');
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
                'options' => self::options($this->context->workspaceId()),
                'bands' => \App\Services\Crm\LeadScorer::config($this->context->workspaceId())['bands'],
                'currency' => request()->attributes->get('workspace')?->currency ?: 'SAR',
                'fields' => \App\Models\Crm\Field::where('entity', 'contact')->orderBy('sort_order')->get(['key', 'label', 'type', 'options']),
            ],
        ]);
    }

    public static function options(?int $workspaceId = null): array
    {
        $settings = $workspaceId ? \App\Services\Crm\CrmSettings::for($workspaceId) : \App\Services\Crm\CrmSettings::DEFAULTS;

        return [
            'extraStatuses' => $settings['extra_statuses'], 'lostReasons' => $settings['lost_reasons'], 'winReasons' => $settings['win_reasons'],
            'lifecycles' => Contact::LIFECYCLES,
            'leadStatuses' => \App\Models\Crm\Lead::STATUSES,
            'disqualifyReasons' => $settings['disqualify_reasons'],
            'activityTypes' => ['task', 'call', 'meeting', 'note', 'email'],
            'sources' => $settings['sources'],
            'requireLostReason' => (bool) ($settings['require_lost_reason'] ?? false),
        ];
    }
}
