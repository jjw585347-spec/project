<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * حالت درست: POST /api/customers/register ثبت‌نام مشتری جدید
     */
    public function test_register_customer_succeeds_with_valid_data(): void
    {
        $response = $this->postJson('/api/customers/register', [
            'name' => 'علی رضایی',
            'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
            'password' => '۱۲۳۴',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'حساب شما با موفقیت ایجاد شد.')
            ->assertJsonPath('customer.name', 'علی رضایی')
            ->assertJsonPath('customer.phone', '09123456789');

        $this->assertDatabaseHas('customers', [
            'name' => 'علی رضایی',
            'phone' => '09123456789',
        ]);
    }

    /**
     * حالت خطا: POST /api/customers/register رد کردن حساب تکراری یا اطلاعات نامعتبر
     */
    public function test_register_customer_fails_on_duplicate_or_invalid_phone(): void
    {
        Customer::create([
            'name' => 'علی رضایی',
            'phone' => '09123456789',
            'password' => Hash::make('1234'),
        ]);

        // تکرار همان نام، شماره و رمز
        $duplicateResponse = $this->postJson('/api/customers/register', [
            'name' => 'علی رضایی',
            'phone' => '09123456789',
            'password' => '1234',
        ]);

        $duplicateResponse->assertStatus(422)
            ->assertJsonPath(
                'message',
                'این حساب با همین نام، شماره موبایل و رمز عبور قبلاً ثبت شده است. لطفاً رمز عبور ۴ رقمی متفاوتی انتخاب کنید یا از بخش ورود وارد شوید.'
            );

        // شماره موبایل نامعتبر
        $invalidPhoneResponse = $this->postJson('/api/customers/register', [
            'name' => 'علی رضایی',
            'phone' => '08123456789',
            'password' => '1234',
        ]);

        $invalidPhoneResponse->assertStatus(422);
    }

    /**
     * حالت درست: POST /api/customers/login ورود موفق مشتری قبلی
     */
    public function test_login_customer_succeeds_with_valid_credentials(): void
    {
        $customer = Customer::create([
            'name' => 'محمد حسینی',
            'phone' => '09121112233',
            'password' => Hash::make('4321'),
        ]);

        $response = $this->postJson('/api/customers/login', [
            'name' => 'محمد حسینی',
            'phone' => '09121112233',
            'password' => '4321',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'ورود با موفقیت انجام شد.')
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('customer.name', 'محمد حسینی');
    }

    /**
     * حالت خطا: POST /api/customers/login رد کردن ورود با رمز اشتباه یا کاربر ناموجود
     */
    public function test_login_customer_fails_with_wrong_password(): void
    {
        Customer::create([
            'name' => 'محمد حسینی',
            'phone' => '09121112233',
            'password' => Hash::make('4321'),
        ]);

        $response = $this->postJson('/api/customers/login', [
            'name' => 'محمد حسینی',
            'phone' => '09121112233',
            'password' => '9999',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'رمز عبور ۴ رقمی واردشده اشتباه است.');
    }

    /**
     * حالت درست: GET /api/customers/{id} دریافت اطلاعات مشتری با شناسه معتبر
     */
    public function test_show_customer_returns_customer_by_id(): void
    {
        $customer = Customer::create([
            'name' => 'سعید کریمی',
            'phone' => '09129998877',
            'password' => Hash::make('1111'),
        ]);

        $response = $this->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('customer.name', 'سعید کریمی')
            ->assertJsonPath('customer.phone', '09129998877');
    }

    /**
     * حالت خطا: GET /api/customers/{id} بازگشت 404 برای شناسه ناموجود
     */
    public function test_show_customer_returns_404_when_not_found(): void
    {
        $response = $this->getJson('/api/customers/99999999');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'حساب مشتری یافت نشد.');
    }
}
