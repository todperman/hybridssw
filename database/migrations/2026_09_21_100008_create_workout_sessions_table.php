<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ตั้งชื่อ workout_sessions เพื่อไม่ชนกับตาราง sessions ของ Laravel (SESSION_DRIVER=database)
        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_template_id')->nullable()->constrained()->nullOnDelete();

            $table->date('date');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // ความจุไม่รวมเทรนเนอร์
            $table->unsignedTinyInteger('capacity')->default(5);

            // booked_count เป็นตัวนับจริงที่อัปเดตภายใต้ row lock เดียวกับการจอง
            // ห้ามคำนวณความว่างจาก COUNT(*) นอกล็อก มิฉะนั้นจะจองเกินตอนคนกดพร้อมกัน
            $table->unsignedTinyInteger('booked_count')->default(0);
            $table->unsignedSmallInteger('waitlist_count')->default(0);

            // shared = เทรนเนอร์หลายคนแบ่งที่นั่งในรอบเดียวกัน
            // exclusive = เทรนเนอร์คนเดียวเหมาทั้งรอบ
            $table->string('mode', 20)->default('shared');
            $table->foreignId('claimed_by_trainer_id')->nullable()->constrained('trainers')->nullOnDelete();

            // open | closed | cancelled | completed
            $table->string('status', 20)->default('open');
            $table->string('close_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // กันสร้างรอบซ้ำเวลาเดียวกันในสาขาเดียวกัน
            $table->unique(['branch_id', 'starts_at']);
            $table->index(['branch_id', 'date', 'status']);
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sessions');
    }
};
