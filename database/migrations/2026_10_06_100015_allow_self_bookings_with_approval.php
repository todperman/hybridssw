<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * รองรับการจองที่สมาชิกจองให้ตัวเอง และการอนุมัติการจองโดยแอดมิน
 *
 * เดิมทุกการจองต้องมีเทรนเนอร์ เพราะมีแต่เทรนเนอร์ที่จองได้
 * พอเปิดให้คนทั่วไปสมัครและจองเอง การจองแบบนั้นไม่มีเทรนเนอร์ trainer_id จึงต้องเว้นว่างได้
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('trainer_id')->nullable()->change();

            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn('approved_at');
        });

        // การจองที่ไม่มีเทรนเนอร์ใส่กลับเป็น NOT NULL ไม่ได้ ต้องลบออกก่อน
        // ย้อนกลับจึงทำให้ข้อมูลการจองของสมาชิกที่จองเองหายไป ตั้งใจให้เป็นแบบนั้น
        DB::table('bookings')->whereNull('trainer_id')->delete();

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('trainer_id')->nullable(false)->change();
        });
    }
};
