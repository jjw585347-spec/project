<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ساخت جدول خدمات آرایشگاه
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // نام خدمت
            $table->unsignedInteger('price');       // قیمت به تومان
            $table->unsignedSmallInteger('duration'); // مدت زمان واقعی به دقیقه
            $table->timestamps();
        });
    }

    /**
     * حذف جدول خدمات
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
