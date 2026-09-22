<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            $table->string('code', 20)->unique();

            // internal = เทรนเนอร์ในสังกัด, external = เทรนเนอร์ภายนอก
            $table->string('type', 20)->default('external');
            // pending | approved | rejected | suspended
            $table->string('status', 20)->default('pending');

            $table->text('bio')->nullable();
            $table->json('specialties')->nullable();

            // เอกสารสำหรับเทรนเนอร์ภายนอก
            $table->string('certification_name')->nullable();
            $table->string('certification_file_path')->nullable();
            $table->date('certification_expires_at')->nullable();
            $table->date('contract_starts_at')->nullable();
            $table->date('contract_ends_at')->nullable();

            // โควตา (null = ใช้ค่าเริ่มต้นตามประเภทเทรนเนอร์)
            $table->unsignedTinyInteger('max_seats_per_session')->nullable();
            $table->unsignedSmallInteger('advance_booking_days')->nullable();
            $table->unsignedSmallInteger('max_team_size')->nullable();

            // โทเคนสำหรับลิงก์/QR ชวนลูกทีมเข้าทีม
            $table->string('invite_token', 64)->unique()->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainers');
    }
};
