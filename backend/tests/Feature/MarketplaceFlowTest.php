<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplaceFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $type, string $email): User
    {
        $user = User::create([
            'first_name' => ucfirst($type),
            'last_name' => 'Test',
            'email' => $email,
            'phone' => '06' . random_int(10000000, 99999999),
            'password' => Hash::make('secret123'),
            'user_type' => $type,
        ]);

        UserProfile::create(['user_id' => $user->id]);

        return $user;
    }

    private function createService(User $creator)
    {
        Storage::fake('public');

        return $this->actingAs($creator, 'sanctum')->post('/api/services', [
            'title' => 'Skincare routine demo',
            'description' => 'Short vertical video',
            'category' => 'beauty',
            'hashtags' => '#ugc',
            'uploadedFiles' => UploadedFile::fake()->create('demo.mp4', 200, 'video/mp4'),
            'platforms' => [
                ['name' => 'instagram', 'videoType' => 'story', 'time' => '30', 'price' => 500],
            ],
        ], ['Accept' => 'application/json']);
    }

    public function test_register_creates_a_user_with_a_profile(): void
    {
        $this->postJson('/api/register', [
            'first_name' => 'Salma',
            'last_name' => 'Idrissi',
            'email' => 'salma@example.com',
            'phone' => '0612345678',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'user_type' => 'creator',
        ])->assertCreated();

        $user = User::where('email', 'salma@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('creator', $user->user_type);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id]);
    }

    public function test_register_rejects_an_unknown_user_type(): void
    {
        $this->postJson('/api/register', [
            'first_name' => 'Eve',
            'last_name' => 'Test',
            'email' => 'eve@example.com',
            'phone' => '0600000000',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'user_type' => 'support',
        ])->assertStatus(422);
    }

    public function test_login_returns_a_token_and_rejects_bad_passwords(): void
    {
        $this->makeUser('creator', 'creator@example.com');

        $this->postJson('/api/login', ['email' => 'creator@example.com', 'password' => 'secret123'])
            ->assertOk();

        $this->postJson('/api/login', ['email' => 'creator@example.com', 'password' => 'wrong'])
            ->assertStatus(401);
    }

    public function test_a_new_service_stays_hidden_until_support_publishes_it(): void
    {
        $creator = $this->makeUser('creator', 'creator@example.com');
        $support = $this->makeUser('support', 'support@example.com');

        $this->createService($creator)->assertCreated();
        $service = Service::first();

        $this->getJson('/api/services/update-status/published')->assertOk()->assertJsonCount(0);

        $this->actingAs($creator, 'sanctum')
            ->putJson('/api/services/update-status', ['id' => $service->id, 'status' => 'published'])
            ->assertForbidden();

        $this->actingAs($support, 'sanctum')
            ->putJson('/api/services/update-status', ['id' => $service->id, 'status' => 'published'])
            ->assertOk();

        $this->getJson('/api/services/update-status/published')->assertOk()->assertJsonCount(1);
    }

    public function test_a_business_can_order_and_the_creator_can_accept(): void
    {
        $creator = $this->makeUser('creator', 'creator@example.com');
        $business = $this->makeUser('business', 'business@example.com');

        $this->createService($creator)->assertCreated();
        $service = Service::first();

        $this->actingAs($business, 'sanctum')->postJson('/api/orders', [
            'service_id' => $service->id,
            'first_name' => 'Youssef',
            'last_name' => 'Alami',
            'email' => 'business@example.com',
            'message' => 'Two stories for a product launch',
            'instagram' => 2,
            'price' => 1000,
        ])->assertCreated();

        $this->assertDatabaseHas('orders', ['service_id' => $service->id, 'status' => 'pending']);

        $orderId = \App\Models\Order::first()->id;

        $this->actingAs($creator, 'sanctum')
            ->postJson("/api/orders/{$orderId}/status", ['status' => 'accepted'])
            ->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'accepted']);
    }

    public function test_guests_cannot_create_services_or_orders(): void
    {
        $this->postJson('/api/services', [])->assertUnauthorized();
        $this->postJson('/api/orders', [])->assertUnauthorized();
    }

    public function test_liking_a_service_twice_removes_the_like(): void
    {
        $creator = $this->makeUser('creator', 'creator@example.com');
        $business = $this->makeUser('business', 'business@example.com');

        $this->createService($creator)->assertCreated();
        $service = Service::first();

        $this->actingAs($business, 'sanctum')
            ->postJson('/api/likes/toggle', ['serviceId' => $service->id])
            ->assertOk()->assertJson(['likes' => 1, 'isLiked' => true]);

        $this->actingAs($business, 'sanctum')
            ->postJson('/api/likes/toggle', ['serviceId' => $service->id])
            ->assertOk()->assertJson(['likes' => 0, 'isLiked' => false]);
    }
}
