<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_email_used_by_another_user_is_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => $other->email])
            ->assertSessionHasErrors('email');
    }

    public function test_a_user_can_keep_their_current_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', ['name' => 'Updated', 'email' => $user->email])
            ->assertSessionHasNoErrors();
    }

    public function test_an_uppercase_email_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => 'USER@EXAMPLE.TEST'])
            ->assertSessionHasErrors('email');
    }

    public function test_a_name_longer_than_255_characters_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', ['name' => str_repeat('a', 256), 'email' => $user->email])
            ->assertSessionHasErrors('name');
    }
}
