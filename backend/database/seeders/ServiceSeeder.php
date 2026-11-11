<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

// ذخیره ۴ خدمت آرایشگاه طبق فایل PRD
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'اصلاح مو', 'price' => 250000, 'duration' => 30],
            ['name' => 'اصلاح مو و ریش', 'price' => 350000, 'duration' => 60],
            ['name' => 'رنگ مو', 'price' => 900000, 'duration' => 90],
            ['name' => 'شست و شو و حالت دادن به مو', 'price' => 150000, 'duration' => 30],
        ];

        foreach ($services as $service) {
            // اگر خدمت از قبل بود دوباره ساخته نمی‌شود
            Service::updateOrCreate(['name' => $service['name']], $service);
        }
    }
}
