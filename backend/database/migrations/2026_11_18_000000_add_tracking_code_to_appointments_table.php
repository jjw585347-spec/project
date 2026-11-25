<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * افزودن ستون کد پیگیری ۶ رقمی (tracking_code) به جدول نوبت‌ها طبق قانون R9
     */
    public function up(): void
    {
        if (!Schema::hasColumn('appointments', 'tracking_code')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->string('tracking_code', 6)->nullable()->unique()->after('status');
            });
        }

        // مقداردهی کد پیگیری ۶ رقمی یکتا برای رکوردهای قبلی
        $existingAppointments = DB::table('appointments')
            ->whereNull('tracking_code')
            ->get(['id']);

        $usedCodes = DB::table('appointments')
            ->whereNotNull('tracking_code')
            ->pluck('tracking_code')
            ->all();
        $usedSet = array_fill_keys($usedCodes, true);

        foreach ($existingAppointments as $appt) {
            do {
                $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            } while (isset($usedSet[$code]));

            $usedSet[$code] = true;

            DB::table('appointments')
                ->where('id', $appt->id)
                ->update(['tracking_code' => $code]);
        }
    }

    /**
     * حذف ستون کد پیگیری از جدول نوبت‌ها
     */
    public function down(): void
    {
        if (Schema::hasColumn('appointments', 'tracking_code')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropUnique(['tracking_code']);
                $table->dropColumn('tracking_code');
            });
        }
    }
};
