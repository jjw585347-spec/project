<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ایجاد جدول مدیران (صاحب آرایشگاه) با رمز هش‌شده و توکن نشست هش‌شده
     */
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('password');
            $table->string('api_token', 64)->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * بازگردانی تغییرات
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
