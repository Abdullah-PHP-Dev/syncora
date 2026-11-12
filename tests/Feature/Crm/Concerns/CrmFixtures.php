<?php

namespace Tests\Feature\Crm\Concerns;

use App\Models\User;
use App\Services\Crm\CrmSettings;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Feature\AiCopilot\Concerns\CreatesAiCopilotTables;

/** Two subscribed tenants (Rawda Living, Hala Coffee) and the CRM schema. */
trait CrmFixtures
{
    use CreatesAiCopilotTables;

    private User $rawda;

    private User $hala;

    protected function setUpCrm(): void
    {
        $this->withoutVite();
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
        $this->createAiCopilotTables();
        foreach (['2026_10_18_140000_create_seller_team_members', '2026_11_11_100000_add_role_to_seller_team_members', '2026_11_11_120000_add_name_to_seller_team_members',
            '2026_06_19_150647_create_ad_campaigns_table', '2026_07_31_220836_create_email_lists_table', '2026_07_31_220837_create_email_subscribers_table',
            '2026_07_31_220838_create_email_list_subscriber_table', '2026_09_18_000001_create_email_subaccounts_table', '2026_09_18_000007_add_sendgrid_columns_to_email_lists_table',
            '2026_09_18_000008_add_sendgrid_columns_to_email_subscribers_table', '2026_09_18_223326_add_source_to_email_subscribers_table',
            '2026_11_12_120000_create_crm_tables', '2026_11_12_120100_connect_crm_to_existing_modules', '2026_11_12_130000_add_crm_automations_settings_and_capture'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        CrmSettings::flush();
        $this->rawda = User::factory()->create(['name' => 'Rawda Living']);
        $this->hala = User::factory()->create(['name' => 'Hala Coffee']);
    }

    private function url(string $name, array|int $params = []): string
    {
        return route('admin.crm.api.'.$name, $params);
    }
}
