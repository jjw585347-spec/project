<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * افزودن وضعیت فعال/غیرفعال به جدول خدمات و جلوگیری از حذف خودکار نوبت‌ها هنگام حذف خدمت
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('duration');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->restrictOnDelete();
        });
    }

    /**
     * بازگردانی تغییرات
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->cascadeOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
