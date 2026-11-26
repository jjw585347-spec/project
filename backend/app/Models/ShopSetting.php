<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// مدل تنظیمات کلی آرایشگاه (جدول shop_settings)
class ShopSetting extends Model
{
    protected $fillable = [
        'shop_name',
        'cancel_deadline_hours',
    ];

    protected $casts = [
        'cancel_deadline_hours' => 'integer',
    ];

    /**
     * دریافت یا ایجاد رکورد یکتای تنظیمات آرایشگاه بر اساس مقادیر اولیه فایل تنظیمات (config/app.php)
     */
    public static function current(): self
    {
        $setting = self::query()->orderBy('id', 'asc')->first();
        if ($setting) {
            return $setting;
        }

        $defaultShopName = trim((string) config('app.shop_name', ''));
        $defaultDeadline = (int) config('app.cancel_deadline_hours');

        return self::create([
            'shop_name' => $defaultShopName !== '' ? $defaultShopName : (string) config('app.name'),
            'cancel_deadline_hours' => $defaultDeadline > 0 ? $defaultDeadline : 1,
        ]);
    }
}
