<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('timezone', 64)->default('Asia/Bangkok');

            // ความจุเริ่มต้นต่อรอบ (ไม่รวมเทรนเนอร์)
            $table->unsignedTinyInteger('default_capacity')->default(5);
            $table->unsignedSmallInteger('slot_duration_minutes')->default(60);

            // นโยบายการจองระดับสาขา
            $table->unsignedSmallInteger('cancellation_cutoff_hours')->default(4);
            $table->unsignedSmallInteger('waitlist_confirm_minutes')->default(30);
            $table->unsignedTinyInteger('no_show_strike_limit')->default(3);
            $table->unsignedSmallInteger('no_show_suspension_days')->default(7);
            $table->unsignedSmallInteger('session_horizon_days')->default(60);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
