<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * حذف محدودیت یکتا بودن شماره موبایل تا دو نفر با نام و شماره یکسان
     * ولی رمز عبور ۴ رقمی متفاوت بتوانند ثبت‌نام کنند
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });
    }

    /**
     * بازگردانی تغییرات
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unique('phone');
        });
    }
};
