<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * เปลี่ยนจากราคาต่อชั่วโมงต่อการจอง เป็นตารางราคาตามจำนวนคน แยกแบบมี/ไม่มี Trainer
 *
 * ทุกสาขาได้ตารางราคาตั้งต้นจาก config/gym.php (ราคาที่เจ้าของระบบกำหนดเมื่อ 2026-10-15)
 * การจองเดิมเก็บยอดที่ตกลงไว้แล้วใน amount จึงไม่ถูกคิดใหม่
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->json('pricing')->nullable()->after('max_booking_hours');
        });

        Schema::table('reservations', function (Blueprint $table) {
            // รายละเอียดการคิดราคาตอนจอง (จำนวนคน แบบ ราคาต่อคน) ไว้ย้อนดูเมื่อแก้ตารางราคาภายหลัง
            $table->json('pricing')->nullable()->after('amount');
        });

        DB::table('branches')->update(['pricing' => json_encode(config('gym.pricing'))]);

        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('hourly_rate'));
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('hourly_rate', 10, 2)->default(0)->after('max_booking_hours');
        });

        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('pricing'));
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn('pricing'));
    }
};
