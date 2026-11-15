<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'mobile' => '+966500000000',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'mobile' => '+966500000000',
        ]);
        $response->assertRedirect(route('admin.connections.index'));
    }

    public function test_registration_requires_a_valid_mobile_number(): void
    {
        foreach ([null, 'invalid', '123', '+1234567890123456'] as $mobile) {
            $this->post('/register', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'mobile' => $mobile,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertSessionHasErrors('mobile');
        }

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
