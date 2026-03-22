<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserInterestList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
    }

    public function test_delete_account_page_renders(): void
    {
        $response = $this->get('/delete-account');
        $response->assertOk();
    }

    public function test_delete_account_request_deactivates_user_when_credentials_are_valid(): void
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'is_active' => true,
        ]);

        $response = $this->post('/delete-account', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/delete-account');
        $this->assertDatabaseHas('users', [
            'user_id' => $user->user_id,
            'is_active' => 0,
        ]);
    }

    public function test_delete_account_request_rejects_wrong_password(): void
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test2@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'is_active' => true,
        ]);

        $response = $this->from('/delete-account')->post('/delete-account', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/delete-account');
        $this->assertDatabaseHas('users', [
            'user_id' => $user->user_id,
            'is_active' => 1,
        ]);
    }

    public function test_user_interest_list_register_is_idempotent(): void
    {
        $response1 = $this->postJson('/api/user-interest-list/register', [
            'email' => 'TEST@EXAMPLE.COM',
        ]);
        $response1->assertOk();
        $this->assertDatabaseHas('user_interest_lists', [
            'email' => 'test@example.com',
        ]);

        $countAfterFirst = UserInterestList::query()->count();

        $response2 = $this->postJson('/api/user-interest-list/register', [
            'email' => 'test@example.com',
        ]);
        $response2->assertOk();

        $this->assertSame($countAfterFirst, UserInterestList::query()->count());
    }
}
