<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * حالت درست: GET /api/services لیست خدمات را به ترتیب شناسه برمی‌گرداند
     */
    public function test_get_services_returns_ordered_services_list(): void
    {
        Service::query()->delete();

        $first = Service::create([
            'name' => 'اصلاح مو',
            'price' => 250000,
            'duration' => 30,
        ]);

        $second = Service::create([
            'name' => 'اصلاح مو و ریش',
            'price' => 350000,
            'duration' => 60,
        ]);

        $response = $this->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $first->id)
            ->assertJsonPath('0.name', 'اصلاح مو')
            ->assertJsonPath('0.price', 250000)
            ->assertJsonPath('0.duration', 30)
            ->assertJsonPath('1.id', $second->id)
            ->assertJsonPath('1.name', 'اصلاح مو و ریش');
    }

    /**
     * حالت خطا / مرزی: ارسال متد نامعتبر POST به /api/services با خطای 405 رد می‌شود
     * و در صورت خالی بودن جدول خدمات، آرایه خالی با کد 200 برمی‌گردد
     */
    public function test_services_endpoint_rejects_invalid_method_and_handles_empty_state(): void
    {
        Service::query()->delete();

        $emptyResponse = $this->getJson('/api/services');
        $emptyResponse->assertStatus(200)->assertExactJson([]);

        $methodNotAllowedResponse = $this->postJson('/api/services', [
            'name' => 'خدمت تستی',
        ]);
        $methodNotAllowedResponse->assertStatus(405);
    }
}
