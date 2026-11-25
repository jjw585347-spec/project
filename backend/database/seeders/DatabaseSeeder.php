<?php

namespace Database\Seeders;

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\WorkingScheduleController;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ServiceSeeder::class,
        ]);

        // ایجاد یا همگام‌سازی حساب اولیه مدیر با رمز هش‌شده از تنظیمات (.env)
        AdminAuthController::ensureConfiguredAdmin();

        // ایجاد ساعت کاری پیش‌فرض (شنبه تا پنج‌شنبه ۱۰:۰۰ تا ۲۰:۰۰ و جمعه تعطیل)
        WorkingScheduleController::ensureDefaultWeeklyHours();
    }
}
