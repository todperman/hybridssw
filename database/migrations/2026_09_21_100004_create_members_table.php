<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            // เทรนเนอร์หลักที่ดูแลอยู่ (ลูกทีมอาจอยู่ได้หลายทีมผ่านตาราง team_members)
            $table->foreignId('primary_trainer_id')->nullable()->constrained('trainers')->nullOnDelete();

            $table->string('member_code', 20)->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->text('health_note')->nullable();

            // แบบคัดกรองสุขภาพก่อนเล่นครั้งแรก (PAR-Q)
            $table->json('parq_answers')->nullable();
            $table->timestamp('parq_signed_at')->nullable();
            $table->text('parq_signature')->nullable();

            // active | suspended | inactive
            $table->string('status', 20)->default('active');
            $table->unsignedSmallInteger('no_show_count')->default(0);
            $table->timestamp('no_show_reset_at')->nullable();
            $table->timestamp('suspended_until')->nullable();
            $table->string('suspension_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index('primary_trainer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
