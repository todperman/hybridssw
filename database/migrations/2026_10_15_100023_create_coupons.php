<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * คูปองส่วนลด แอดมินสร้างในหลังบ้าน ลูกค้ากรอกโค้ดตอนจอง
 *
 * จำนวนครั้งที่ใช้นับจากการจองที่ยังถือเวลาอยู่ (รอชำระหรือยืนยัน) ที่อ้างคูปองนี้
 * การจองที่หมดอายุหรือถูกยกเลิกจึงคืนสิทธิ์ให้เองโดยไม่ต้องมีตารางนับแยก
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('description')->nullable();
            // percent | fixed
            $table->string('type', 10)->default('percent');
            $table->decimal('value', 10, 2);
            // เพดานส่วนลดสำหรับแบบเปอร์เซ็นต์ null = ไม่จำกัด
            $table->decimal('max_discount', 10, 2)->nullable();
            // ยอดขั้นต่ำก่อนหักส่วนลด
            $table->decimal('min_amount', 10, 2)->default(0);
            // both | with_trainer | without_trainer
            $table->string('applies_to', 20)->default('both');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedSmallInteger('max_uses_per_member')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('pricing')->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0)->after('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount_amount');
        });

        Schema::dropIfExists('coupons');
    }
};
