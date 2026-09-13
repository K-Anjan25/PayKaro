<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_the_sign_in_screen_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back');
    }

    public function test_the_sign_up_screen_renders(): void
    {
        $this->get('/signup')->assertOk()->assertSee('Create your account');
    }

    public function test_an_email_and_password_sign_a_member_in(): void
    {
        $user = $this->workspace();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_email_is_matched_case_insensitively_and_trimmed(): void
    {
        $user = $this->workspace();

        $this->post('/login', [
            'email' => '  '.strtoupper($user->email).' ',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $user = $this->workspaceFixture();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_unknown_email_is_rejected_with_the_same_message(): void
    {
        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_six_failed_attempts_lock_the_email_out(): void
    {
        // `workspaceFixture()`, not `workspace()`: the point is that a *guest*
        // gets locked out, and `workspace()` would have signed this test in.
        $user = $this->workspaceFixture();

        $attempt = fn () => $this->post('/login', [
            'email' => $user->email,
            'password' => 'nope',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $attempt();
        }

        $attempt()->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Too many',
            session('errors')->first('email'),
        );
    }

    public function test_a_successful_sign_in_clears_the_attempt_counter(): void
    {
        $user = $this->workspace();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->post('/logout')->assertRedirect(route('login'));

        // Five more failures are still allowed after a clean sign-in.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->assertStringNotContainsString(
            'Too many',
            session('errors')->first('email') ?? '',
        );
    }

    public function test_a_google_only_account_is_told_how_to_sign_in_instead_of_crashing(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->google()->for($business)->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'anything'])
            ->assertSessionHasErrors('email');

        $message = (string) session('errors')->first('email');

        // The copy names the account type and the way out. (It reads "signs in with
        // Google" — the account signs in, not the person — so the old assertion,
        // looking for "sign in with Google", could never match.)
        $this->assertStringContainsString('signs in with Google', $message);
        $this->assertStringContainsString('Google button', $message);

        $this->assertGuest();
    }

    public function test_sign_up_creates_a_business_and_its_owner(): void
    {
        $this->post('/signup', [
            'business_name' => 'Shree Precision Works',
            'name' => 'Sunita Rao',
            'email' => 'Sunita@Example.Test',
            'password' => 'a-good-password',
        ])->assertRedirect(route('buyers.create'));

        $this->assertAuthenticated();

        $user = User::query()->where('email', 'sunita@example.test')->sole();

        $this->assertSame(UserRole::Owner, $user->role);
        $this->assertSame('Shree Precision Works', $user->business->name);
        $this->assertNotSame('a-good-password', $user->password);
    }

    public function test_sign_up_names_the_business_after_the_person_when_left_blank(): void
    {
        $this->post('/signup', [
            'name' => 'Imran Shaikh',
            'email' => 'imran@example.test',
            'password' => 'a-good-password',
        ])->assertRedirect(route('buyers.create'));

        $this->assertSame(
            'Imran Shaikh',
            User::query()->where('email', 'imran@example.test')->sole()->business->name,
        );
    }

    public function test_a_duplicate_email_is_a_field_error_rather_than_a_server_error(): void
    {
        // Again a guest: the form under test is only reachable when signed out.
        $existing = $this->workspaceFixture();

        $this->from('/signup')->post('/signup', [
            'name' => 'Someone Else',
            'email' => $existing->email,
            'password' => 'a-good-password',
        ])->assertRedirect('/signup')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_short_password_is_rejected(): void
    {
        $this->post('/signup', [
            'name' => 'Short Password',
            'email' => 'short@example.test',
            'password' => 'tiny',
        ])->assertSessionHasErrors('password');
    }

    public function test_guests_cannot_reach_the_workspace(): void
    {
        foreach (['/dashboard', '/invoices', '/buyers', '/treds', '/reports', '/settings'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_a_signed_in_member_is_bounced_off_the_auth_pages(): void
    {
        $this->workspace();

        $this->get('/login')->assertRedirect(route('dashboard'));
        $this->get('/signup')->assertRedirect(route('dashboard'));
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_logging_out_ends_the_session(): void
    {
        $this->workspace();

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_google_sign_in_is_not_offered_until_credentials_exist(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get('/login')->assertOk()->assertDontSee('Continue with Google');
        $this->get('/auth/google')->assertNotFound();
    }

    public function test_the_callback_rejects_an_unconfigured_state(): void
    {
        $this->get('/auth/google/callback?code=whatever')
            ->assertNotFound();
    }
}
