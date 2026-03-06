<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_registration_page(): void
    {
        $this->get(route('register'))
            ->assertOk();
    }

    public function test_user_can_register_successfully(): void
    {
        Event::fake([Registered::class]);

        $password = 'ValidPass1!';

        $response = $this->post(route('register.post'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => $password,
            'password_confirmation' => $password,
            'personal_data_consent' => '1',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $user = User::query()->where('email', 'john@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotSame($password, $user->password);
        $this->assertNotNull($user->personal_data_consent_at);
        $this->assertAuthenticatedAs($user);

        Event::assertDispatched(
            Registered::class,
            fn (Registered $event): bool => $event->user->is($user)
        );
    }

    public function test_registration_fails_for_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $password = 'ValidPass1!';

        $response = $this->from(route('register'))->post(route('register.post'), [
            'name' => 'Another User',
            'email' => 'existing@example.com',
            'password' => $password,
            'password_confirmation' => $password,
            'personal_data_consent' => '1',
        ]);

        $response
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_fails_without_personal_data_consent(): void
    {
        $password = 'ValidPass1!';

        $response = $this->from(route('register'))->post(route('register.post'), [
            'name' => 'No Consent',
            'email' => 'noconsent@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['personal_data_consent']);

        $this->assertDatabaseMissing('users', [
            'email' => 'noconsent@example.com',
        ]);
    }
}
