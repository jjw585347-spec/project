<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بروزرسانی جدول مشتری‌ها (حذف یکتایی نام، افزودن رمز ۴ رقمی و یکتایی شماره موبایل)
     * و اتصال جدول نوبت‌ها به حساب مشتری (customer_id)
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // حذف محدودیت یکتا بودن نام تا دو نفر هم‌نام بتوانند ثبت‌نام کنند
            $table->dropUnique(['name']);
            // افزودن ستون رمز عبور ۴ رقمی
            $table->string('password')->nullable()->after('phone');
        });

        // مقداردهی پیش‌فرض رمز عبور برای رکوردهای قبلی (در صورت وجود) و حذف شماره‌های تکراری احتمالی
        $customers = DB::table('customers')->orderBy('id')->get();
        $seenPhones = [];
        foreach ($customers as $customer) {
            if (isset($seenPhones[$customer->phone])) {
                DB::table('customers')->where('id', $customer->id)->delete();
                continue;
            }
            $seenPhones[$customer->phone] = true;

            if (empty($customer->password)) {
                DB::table('customers')
                    ->where('id', $customer->id)
                    ->update(['password' => Hash::make('1234')]);
            }
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('phone');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('id')
                ->constrained('customers')
                ->nullOnDelete();
        });
    }

    /**
     * بازگردانی تغییرات
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('password');
            $table->unique('name');
        });
    }
};
