<?php

namespace Tests\Feature;

use App\Models\User;
use Mockery;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use \Tests\Feature\Concerns\FakesTeamDashboardMetrics;

    private function bootLocale(string $locale): void
    {
        putenv('ROUTING_LOCALE='.$locale);
        $this->refreshApplication();
        $this->fakeTeamDashboardMetrics();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        putenv('ROUTING_LOCALE');
        parent::tearDown();
    }

    private function userWithRole(string $role): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->name = 'Test user';
        $user->shouldReceive('hasRole')->andReturnUsing(fn ($required) => $required === $role);
        return $user;
    }

    public function test_arabic_login_overrides_previous_english_preference(): void
    {
        $this->bootLocale('ar');
        $this->withSession(['locale' => 'en'])->get('/ar/login')
            ->assertOk()->assertSee('dir="rtl"', false)->assertSee('مرحباً بعودتك')
            ->assertSee('action="'.url('/ar/login').'"', false)
            ->assertSee(url('/en/login'), false)->assertSessionHas('locale', 'ar');
    }

    public function test_english_login_overrides_previous_arabic_preference(): void
    {
        $this->bootLocale('en');
        $this->withSession(['locale' => 'ar'])->get('/en/login')
            ->assertOk()->assertSee('dir="ltr"', false)->assertSee('Welcome back')
            ->assertSee(url('/ar/login'), false)->assertSessionHas('locale', 'en');
    }

    public function test_arabic_registration_is_translated(): void
    {
        $this->bootLocale('ar');
        $this->get('/ar/register')->assertOk()->assertSee('أنشئ مساحة عملك')
            ->assertSee('action="'.url('/ar/register').'"', false);
    }

    public function test_arabic_login_validation_is_translated(): void
    {
        $this->bootLocale('ar');
        $this->from('/ar/login')->post('/ar/login', [])
            ->assertRedirect('/ar/login')
            ->assertSessionHasErrors(['email' => 'حقل البريد الإلكتروني مطلوب.', 'password' => 'حقل كلمة المرور مطلوب.']);
    }

    public function test_guest_dashboard_redirects_to_login_in_the_same_language(): void
    {
        $this->bootLocale('ar');
        $this->get('/ar/dashboard')->assertRedirect('/ar/login');
    }

    public function test_arabic_logout_keeps_the_selected_language(): void
    {
        $this->bootLocale('ar');
        $this->actingAs($this->userWithRole('admin'))->post('/ar/logout')->assertRedirect('/ar');
        $this->assertGuest();
    }

    public function test_arabic_dashboard_uses_backend_role_and_preserves_language(): void
    {
        $this->bootLocale('ar');
        foreach (['admin' => 'لوحة تحكم فريق سوشيال إيز', 'seller' => 'لوحة التحكم', 'customer_support' => 'دعم العملاء'] as $role => $title) {
            $this->actingAs($this->userWithRole($role))->withSession(['locale' => 'en'])
                ->get('/ar/dashboard')->assertOk()->assertSee($title)->assertSee('dir="rtl"', false)
                ->assertSee(url('/en/dashboard'), false);
            $this->get('/ar/login')->assertRedirect('/ar/dashboard');
        }
    }

    public function test_english_dashboard_uses_backend_role_and_preserves_language(): void
    {
        $this->bootLocale('en');
        foreach (['admin' => 'Social Eaz Team Dashboard', 'seller' => 'Dashboard', 'customer_support' => 'Customer support'] as $role => $title) {
            $this->actingAs($this->userWithRole($role))->withSession(['locale' => 'ar'])
                ->get('/en/dashboard')->assertOk()->assertSee($title)->assertSee('dir="ltr"', false)
                ->assertSee(url('/ar/dashboard'), false);
            $this->get('/en/login')->assertRedirect('/en/dashboard');
        }
    }
}
