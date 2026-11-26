<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * افزودن ستون نام کاربری (username) به جدول مدیران (admins) در کنار شماره موبایل (phone) و رمز عبور (password)
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('username', 100)->nullable()->unique()->after('id');
        });

        $defaultUsername = trim((string) config('admin.username', ''));
        $defaultPhone = trim((string) config('admin.phone', ''));

        $admins = DB::table('admins')->get();
        foreach ($admins as $admin) {
            $currentPhoneValue = trim((string) $admin->phone);
            $isPhoneDigits = (bool) preg_match('/^09\d{9}$/', $currentPhoneValue);

            if (!$isPhoneDigits && $currentPhoneValue !== '') {
                // اگر قبلاً نام کاربری مثل aref داخل ستون phone ذخیره شده بود، به ستون username منتقل شود
                $resolvedUsername = $currentPhoneValue;
                $resolvedPhone = preg_match('/^09\d{9}$/', $defaultPhone) ? $defaultPhone : '09120000000';
            } else {
                $resolvedUsername = ($defaultUsername !== '' && !preg_match('/^09\d{9}$/', $defaultUsername))
                    ? $defaultUsername
                    : 'aref';
                $resolvedPhone = $isPhoneDigits ? $currentPhoneValue : '09120000000';
            }

            DB::table('admins')
                ->where('id', $admin->id)
                ->update([
                    'username' => $resolvedUsername,
                    'phone' => $resolvedPhone,
                ]);
        }
    }

    /**
     * بازگردانی تغییرات
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
