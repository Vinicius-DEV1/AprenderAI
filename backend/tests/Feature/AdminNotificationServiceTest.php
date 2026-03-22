<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\AdminNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_it_creates_notifications_for_all_admins()
    {
        $admin1 = User::factory()->create(['role' => 'admin']);
        $admin2 = User::factory()->create(['role' => 'admin']);
        $user   = User::factory()->create(['role' => 'user']); // Should not receive

        $service = new AdminNotificationService();
        $service->notify(
            AdminNotificationService::SEVERITY_GRAVE,
            'Test Grave Error',
            'This is a test body',
            'test_key_1',
            60
        );

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin1->id,
            'title'   => 'Test Grave Error',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin2->id,
            'title'   => 'Test Grave Error',
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $user->id,
            'title'   => 'Test Grave Error',
        ]);
        
        $this->assertEquals(2, UserNotification::count());
    }

    public function test_it_deduplicates_identical_notifications()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $service = new AdminNotificationService();
        
        // First call should work
        $service->notify(
            AdminNotificationService::SEVERITY_MEDIO,
            'Deduplication Test',
            'Body',
            'dedup_key',
            60
        );

        // Second call with same key should be ignored
        $service->notify(
            AdminNotificationService::SEVERITY_MEDIO,
            'Deduplication Test',
            'Body',
            'dedup_key',
            60
        );

        $this->assertEquals(1, UserNotification::where('user_id', $admin->id)->count());
    }

    public function test_it_maps_severities_to_visual_types()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = new AdminNotificationService();

        $service->notify(AdminNotificationService::SEVERITY_GRAVE, 'Grave', 'Body', 'key1', 60);
        $service->notify(AdminNotificationService::SEVERITY_MEDIO, 'Medio', 'Body', 'key2', 60);
        $service->notify(AdminNotificationService::SEVERITY_LEVE,  'Leve', 'Body', 'key3', 60);

        $grave = UserNotification::where('title', 'Grave')->first();
        $this->assertEquals('warning', $grave->type);
        $this->assertStringContainsString('grave', $grave->meta);

        $medio = UserNotification::where('title', 'Medio')->first();
        $this->assertEquals('info', $medio->type);
        $this->assertStringContainsString('médio', $medio->meta);

        $leve = UserNotification::where('title', 'Leve')->first();
        $this->assertEquals('tip', $leve->type);
        $this->assertStringContainsString('leve', $leve->meta);
    }
}
