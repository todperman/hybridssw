<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ระบบจอง Private Gym: หนึ่งการจองใช้ทั้งยิมต่อช่วงเวลา กลุ่มละ 1–6 คน
 *
 * หัวใจของความถูกต้องอยู่ที่ slot_locks หนึ่งแถวต่อทรัพยากรต่อชั่วโมง
 * (ยิม / Trainer / ผู้เข้าร่วมแต่ละคน) พร้อม unique index
 * จองซ้อนจึงเป็นไปไม่ได้ที่ระดับฐานข้อมูล แม้สองคำขอจะเข้ามาพร้อมกันเป๊ะ
 * ไม่ต้องพึ่งการเช็คในโค้ดอย่างเดียว ซึ่ง MySQL ไม่มี exclusion constraint ให้ใช้
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('hourly_rate', 10, 2)->default(0)->after('max_group_size');
            $table->unsignedTinyInteger('max_trainees')->default(6)->after('hourly_rate');
            $table->unsignedSmallInteger('booking_window_days')->default(45)->after('max_trainees');
            $table->unsignedTinyInteger('max_booking_hours')->default(4)->after('booking_window_days');
            $table->text('payment_instructions')->nullable()->after('max_booking_hours');
        });

        Schema::table('members', function (Blueprint $table) {
            // สิทธิ์พิเศษที่แอดมินให้เท่านั้น ไม่มีช่องทางให้สมาชิกยื่นขอ
            $table->boolean('can_book_without_trainer')->default(false)->after('review_note');
        });

        Schema::table('trainers', function (Blueprint $table) {
            $table->boolean('accepts_bookings')->default(true)->after('status');
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            // ว่างได้เฉพาะกลุ่มที่ทุกคนได้สิทธิ์เข้าใช้โดยไม่มี Trainer
            $table->foreignId('trainer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payer_member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_via', 10);

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedTinyInteger('hours');

            // pending_payment | confirmed | expired | cancelled
            $table->string('status', 20);
            $table->dateTime('hold_expires_at')->nullable();

            $table->decimal('hourly_rate', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('THB');

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();

            // แสดงแยกจากสถานะการจองตามข้อกำหนด: pending | succeeded | failed
            $table->string('refund_status', 20)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'starts_at']);
            $table->index(['status', 'hold_expires_at']);
            $table->index(['trainer_id', 'starts_at']);
        });

        Schema::create('reservation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['reservation_id', 'member_id']);
            $table->index('member_id');
        });

        Schema::create('slot_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();

            // gym (resource_id = branch_id) | trainer | member
            $table->string('resource', 10);
            $table->unsignedBigInteger('resource_id');
            $table->dateTime('slot_start');

            $table->unique(['resource', 'resource_id', 'slot_start'], 'slot_locks_unique_resource_slot');
            $table->index('reservation_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payer_member_id')->constrained('members')->cascadeOnDelete();

            // manual | omise
            $table->string('provider', 20);
            $table->string('provider_charge_id')->nullable();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('THB');

            // pending | succeeded | failed
            $table->string('status', 20);
            $table->string('failure_message')->nullable();
            $table->timestamp('paid_at')->nullable();

            // ชำระสำเร็จหลังรายการหมดอายุและเวลาเดิมถูกจองไปแล้ว ต้องให้แอดมินตรวจและคืนเงิน
            $table->boolean('needs_review')->default(false);

            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            // กันบันทึกยอดซ้ำจาก webhook ที่ส่งมาหลายครั้ง
            $table->unique(['provider', 'provider_charge_id']);
            $table->index(['reservation_id', 'status']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('reason');

            // pending | succeeded | failed
            $table->string('status', 20);
            $table->string('provider_refund_id')->nullable();
            $table->string('failure_message')->nullable();

            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['status']);
        });

        Schema::create('reservation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();

            // reschedule | trainer_withdrawal
            $table->string('type', 30);
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();

            $table->dateTime('new_starts_at')->nullable();
            $table->dateTime('new_ends_at')->nullable();
            $table->foreignId('replacement_trainer_id')->nullable()->constrained('trainers')->nullOnDelete();

            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });

        Schema::create('trainer_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();

            // ประจำสัปดาห์ (day_of_week) หรือเฉพาะวัน (date) อย่างใดอย่างหนึ่ง
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->date('date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['trainer_id', 'day_of_week']);
            $table->index(['trainer_id', 'date']);
        });

        Schema::create('trainer_time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            // ว่างทั้งคู่ = ไม่รับงานทั้งวัน
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['trainer_id', 'date']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->morphs('subject');
            $table->text('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('action');
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('trainer_time_offs');
        Schema::dropIfExists('trainer_availabilities');
        Schema::dropIfExists('reservation_requests');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('slot_locks');
        Schema::dropIfExists('reservation_participants');
        Schema::dropIfExists('reservations');

        Schema::table('trainers', fn (Blueprint $table) => $table->dropColumn('accepts_bookings'));
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('can_book_without_trainer'));
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn([
            'hourly_rate', 'max_trainees', 'booking_window_days', 'max_booking_hours', 'payment_instructions',
        ]));
    }
};
