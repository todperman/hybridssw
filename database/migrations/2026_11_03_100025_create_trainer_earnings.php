<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ส่วนแบ่งเทรนเนอร์: หนึ่งแถวต่อหนึ่งการจองที่ยืนยันแล้ว สแนปช็อตฐานและยอด ณ เวลายืนยัน
 * ตารางส่วนแบ่ง (Trainer/Facility ต่อจำนวนคน) เก็บรายสาขาในคอลัมน์ payout ของ branches
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->json('payout')->nullable()->after('pricing');
        });

        Schema::create('trainer_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->unsignedTinyInteger('people');
            $table->unsignedTinyInteger('hours');
            $table->decimal('per_hour', 10, 2);          // ค่า Trainer ต่อชั่วโมงตามตาราง ณ ตอนยืนยัน
            $table->decimal('amount', 10, 2);            // per_hour × hours
            $table->decimal('facility_amount', 10, 2);   // ส่วนของยิมตามตาราง (ไว้ดูสัดส่วน)
            $table->decimal('customer_amount', 10, 2);   // ยอดที่ลูกค้าจ่ายจริง (หลังฟรี/ส่วนลด)
            $table->string('status', 20)->index();       // accrued | payable | paid | void
            $table->dateTime('earned_at')->index();      // = ends_at ของการจอง นับเป็นรายได้เมื่อถึงเวลานี้
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payout_note')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['trainer_id', 'status', 'earned_at']);
            $table->index(['branch_id', 'earned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_earnings');
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('payout'));
    }
};
