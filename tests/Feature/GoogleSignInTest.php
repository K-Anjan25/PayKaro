<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use RuntimeException;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

/**
 * Google Sign-In through Socialite.
 *
 * The provider is mocked at Socialite's boundary, so these tests cover what
 * PayKaro owns — one-click provisioning, linking an existing email, refusing to
 * duplicate a workspace — without dialling Google.
 */
final class GoogleSignInTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'https://app.test/auth/google/callback',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Swap Socialite's google driver for a mock whose behaviour the test names.
     *
     * @param  Closure(GoogleProvider&Mockery::MockInterface):void  $configure
     */
    private function fakeSocialite(Closure $configure): void
    {
        $provider = Mockery::mock(GoogleProvider::class);

        $provider->shouldReceive('redirectUrl')->andReturnSelf();

        $configure($provider);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function googleUser(string $id, ?string $email, string $name): GoogleUser
    {
        $googleUser = new GoogleUser;

        $googleUser->id = $id;
        $googleUser->email = $email;
        $googleUser->name = $name;
        $googleUser->avatar = 'https://example.test/avatar.png';

        return $googleUser;
    }

    public function test_the_button_is_offered_once_credentials_exist(): void
    {
        // The label is design copy — the wireframe signs in with "Sign in with
        // Google", the component's default says "Continue with Google" — so the
        // assertion is about the entry point the button offers, not its wording.
        $this->get('/login')->assertOk()->assertSee(route('auth.google'));
    }

    public function test_the_redirect_route_hands_off_to_google(): void
    {
        $this->fakeSocialite(
            fn ($provider) => $provider->shouldReceive('redirect')->andReturn(
                new RedirectResponse('https://accounts.google.com/o/oauth2/auth?state=test'),
            )
        );

        $this->get('/auth/google')->assertRedirectContains('accounts.google.com/o/oauth2/auth');
    }

    public function test_a_first_google_sign_in_creates_the_business_and_its_owner(): void
    {
        $this->fakeSocialite(fn ($provider) => $provider->shouldReceive('user')->andReturn(
            $this->googleUser('g-1000', 'farid@example.test', 'Farid Khan'),
        ));

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'farid@example.test')->sole();

        $this->assertSame('g-1000', $user->google_id);
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, Business::query()->count());
        $this->assertSame($user->business_id, Business::query()->sole()->id);
        $this->assertNull($user->password, 'a Google-only account never gets a password');
    }

    public function test_repeating_the_callback_does_not_create_a_second_workspace(): void
    {
        $this->fakeSocialite(fn ($provider) => $provider->shouldReceive('user')->andReturn(
            $this->googleUser('g-1000', 'farid@example.test', 'Farid Khan'),
        ));

        $this->get('/auth/google/callback');
        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $this->assertSame(1, Business::query()->count());
        $this->assertSame(1, User::query()->where('email', 'farid@example.test')->count());
    }

    public function test_an_existing_password_account_is_linked_rather_than_duplicated(): void
    {
        $user = $this->workspace();
        $this->fakeSocialite(fn ($provider) => $provider->shouldReceive('user')->andReturn(
            $this->googleUser('g-777', $user->email, 'Some Other Name'),
        ));

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertSame('g-777', $user->google_id);
        $this->assertSame($user->name, User::query()->where('email', $user->email)->sole()->name);
        $this->assertNotNull($user->password, 'the password must still work as a fallback');
        $this->assertSame(1, Business::query()->count());
    }

    public function test_a_failed_exchange_returns_to_sign_in_without_leaking_details(): void
    {
        $this->fakeSocialite(
            fn ($provider) => $provider->shouldReceive('user')->andThrow(new RuntimeException('invalid state'))
        );

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_google_assertion_without_an_email_is_refused(): void
    {
        $this->fakeSocialite(fn ($provider) => $provider->shouldReceive('user')->andReturn(
            $this->googleUser('g-500', null, 'No Email'),
        ));

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Google did not share an email address we can sign you in with.');

        $this->assertSame(0, User::query()->where('google_id', 'g-500')->count());
        $this->assertSame(0, Business::query()->count());
    }
}
