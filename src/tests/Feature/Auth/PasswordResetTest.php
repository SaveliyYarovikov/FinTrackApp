<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_STATUS_MESSAGE = 'If an account with this email exists, we will send a password reset link.';

    public function test_guest_can_open_password_reset_request_form(): void
    {
        $this->get(route('password.request'))
            ->assertOk();
    }

    public function test_existing_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', self::GENERIC_STATUS_MESSAGE);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_reset_request_for_unknown_email_is_neutral(): void
    {
        Notification::fake();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'missing@example.com',
        ]);

        $response
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', self::GENERIC_STATUS_MESSAGE)
            ->assertSessionDoesntHaveErrors();

        Notification::assertNothingSent();
    }

    public function test_guest_can_open_password_reset_form_with_token(): void
    {
        $token = 'reset-token-123';
        $email = 'user@example.com';

        $this->get(route('password.reset', ['token' => $token, 'email' => $email]))
            ->assertOk()
            ->assertSee('name="token" value="'.$token.'"', false)
            ->assertSee('value="'.$email.'"', false);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        Event::fake([PasswordReset::class]);
        Notification::fake();

        $oldPassword = 'OldPass1!';
        $newPassword = 'NewPass1!';

        $user = User::factory()->create([
            'password' => Hash::make($oldPassword),
        ]);

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertSessionHas('status', self::GENERIC_STATUS_MESSAGE);

        $token = null;

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            }
        );

        $this->assertNotNull($token);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertRedirect(route('login'));

        $user->refresh();

        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse(Hash::check($oldPassword, $user->password));

        Event::assertDispatched(
            PasswordReset::class,
            fn (PasswordReset $event): bool => $event->user->is($user)
        );
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $oldPassword = 'OldPass1!';
        $newPassword = 'NewPass1!';

        $user = User::factory()->create([
            'password' => Hash::make($oldPassword),
        ]);

        $response = $this->from(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

        $response
            ->assertRedirect(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->assertSessionHasErrors(['email']);

        $user->refresh();

        $this->assertTrue(Hash::check($oldPassword, $user->password));
        $this->assertFalse(Hash::check($newPassword, $user->password));
    }
}
