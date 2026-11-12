<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ساخت جدول مشتری‌ها
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // نام مشتری (غیرتکراری)
            $table->string('phone');          // شماره موبایل مشتری
            $table->timestamps();
        });
    }

    /**
     * حذف جدول مشتری‌ها
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
