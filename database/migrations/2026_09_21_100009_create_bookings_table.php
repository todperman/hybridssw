<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();

            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booked_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // booked | waitlisted | cancelled | checked_in | no_show | completed
            $table->string('status', 20)->default('booked');

            // คิวสำรอง
            $table->unsignedSmallInteger('waitlist_position')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('confirm_deadline_at')->nullable();

            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->boolean('cancelled_late')->default(false);

            $table->foreignId('member_package_id')->nullable();
            $table->boolean('credit_consumed')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            // MySQL ไม่มี partial index จึงใช้คอลัมน์ช่วย:
            // active_member_key = member_id ตอนที่การจองยัง active, และเป็น NULL เมื่อยกเลิก
            // MySQL ยอมให้ NULL ซ้ำกันได้ในคีย์ unique ลูกทีมจึงจองรอบเดิมใหม่หลังยกเลิกได้
            // แต่จองซ้อนในรอบเดียวกันตอน active ไม่ได้
            $table->unsignedBigInteger('active_member_key')->nullable();
            $table->unique(['workout_session_id', 'active_member_key'], 'bookings_session_active_member_unique');

            $table->index(['workout_session_id', 'status']);
            $table->index(['member_id', 'status']);
            $table->index(['trainer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
