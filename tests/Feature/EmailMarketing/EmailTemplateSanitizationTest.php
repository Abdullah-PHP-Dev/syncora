<?php

namespace Tests\Feature\EmailMarketing;

use App\Models\EmailMarketing\EmailTemplate;
use App\Models\User;
use Tests\Feature\EmailMarketing\Concerns\CreatesEmailMarketingTables;
use Tests\TestCase;

/**
 * The body a seller submits is generated client-side and the hidden form
 * field carrying it is an ordinary request field - nothing stops a
 * tampered/hand-crafted POST from bypassing the generator. These tests
 * pin App\Support\Email\EmailHtmlSanitizer as the real save-time boundary:
 * a <script> tag must never survive into the database, while the MSO
 * conditional comments and <style> block the email HTML engine legitimately
 * emits must survive byte-for-byte (see that class's docblock for why
 * both things matter equally).
 */
class EmailTemplateSanitizationTest extends TestCase
{
    use CreatesEmailMarketingTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createEmailMarketingTables();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            \App\Http\Middleware\EnsureSeller::class,
            \App\Http\Middleware\EnsureActiveSubscription::class,
        ]);
    }

    public function test_a_script_tag_is_stripped_from_the_saved_body(): void
    {
        $user = User::factory()->create();

        $body = '<!doctype html><html><head><style>body{color:red;}</style></head>'
            . '<body><table role="presentation"><tr><td>Hello <script>alert(1)</script>{{first_name}}</td></tr></table></body></html>';

        $response = $this->actingAs($user)->post('/email/templates', [
            'name' => 'Script Test', 'subject' => 'S', 'body' => $body, 'status' => 'draft',
        ]);

        $response->assertRedirect();
        $template = EmailTemplate::where('name', 'Script Test')->firstOrFail();
        $this->assertStringNotContainsString('<script>', $template->body);
        $this->assertStringNotContainsString('alert(1)', $template->body);
        $this->assertStringContainsString('{{first_name}}', $template->body);
    }

    public function test_mso_comments_and_the_style_block_survive_the_save(): void
    {
        $user = User::factory()->create();

        $body = '<!doctype html><html><head><style>@media(max-width:600px){.x{width:100%!important}}</style></head>'
            . '<body><table role="presentation" width="600"><tr><td>hi</td></tr></table>'
            . '<!--[if mso]><v:roundrect fillcolor="#000"><w:anchorlock/><center>Btn</center></v:roundrect><![endif]-->'
            . '<!--[if !mso]><!--><table><tr><td style="border-radius:8px;display:inline-block;">Btn</td></tr></table><!--<![endif]-->'
            . '</body></html>';

        $response = $this->actingAs($user)->post('/email/templates', [
            'name' => 'MSO Test', 'subject' => 'S', 'body' => $body, 'status' => 'draft',
        ]);

        $response->assertRedirect();
        $template = EmailTemplate::where('name', 'MSO Test')->firstOrFail();
        $this->assertStringContainsString('<!--[if mso]><v:roundrect fillcolor="#000"><w:anchorlock/><center>Btn</center></v:roundrect><![endif]-->', $template->body);
        $this->assertStringContainsString('@media(max-width:600px){.x{width:100%!important}}', $template->body);
        $this->assertStringContainsString('border-radius:8px', $template->body);
        $this->assertStringContainsString('display:inline-block', $template->body);
    }

    public function test_an_onclick_handler_is_stripped(): void
    {
        $user = User::factory()->create();

        $body = '<body><a href="https://x.com" onclick="evil()">Click</a></body>';

        $this->actingAs($user)->post('/email/templates', [
            'name' => 'Onclick Test', 'subject' => 'S', 'body' => $body, 'status' => 'draft',
        ]);

        $template = EmailTemplate::where('name', 'Onclick Test')->firstOrFail();
        $this->assertStringNotContainsString('onclick', $template->body);
        $this->assertStringContainsString('href="https://x.com"', $template->body);
    }
}
