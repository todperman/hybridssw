<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ถอดระบบรอบแชร์ที่นั่งเดิมออก หลังเปลี่ยนเป็นจองทั้งยิมรายชั่วโมง (ระบบจอง Private Gym)
 *
 * ทิ้งรอบ การจองแบบที่นั่ง คิวสำรอง แพ็กเกจเครดิต และสถิติไม่มาตามนัด
 * ข้อมูลในตารางเหล่านี้เป็นข้อมูลทดลองช่วงก่อนเปิดใช้จริง เจ้าของระบบตกลงให้ลบได้
 *
 * down() สร้างโครงตารางและคอลัมน์คืน แต่ไม่ได้ข้อมูลเดิมคืน
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    protected array $columns = [
        'branches' => ['default_capacity', 'slot_duration_minutes', 'cancellation_cutoff_hours', 'waitlist_confirm_minutes', 'no_show_strike_limit', 'no_show_suspension_days', 'session_horizon_days'],
        'members' => ['no_show_count', 'no_show_reset_at'],
        'trainers' => ['max_seats_per_session', 'advance_booking_days'],
        'schedule_templates' => ['slot_duration_minutes', 'capacity'],
        'schedule_exceptions' => ['capacity'],
    ];

    public function up(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('workout_sessions');
        Schema::dropIfExists('member_packages');
        Schema::dropIfExists('packages');

        foreach ($this->columns as $table => $columns) {
            $existing = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));

            if ($existing !== []) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($existing));
            }
        }
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedTinyInteger('default_capacity')->default(5);
            $table->unsignedSmallInteger('slot_duration_minutes')->default(60);
            $table->unsignedSmallInteger('cancellation_cutoff_hours')->default(4);
            $table->unsignedSmallInteger('waitlist_confirm_minutes')->default(30);
            $table->unsignedTinyInteger('no_show_strike_limit')->default(3);
            $table->unsignedSmallInteger('no_show_suspension_days')->default(7);
            $table->unsignedSmallInteger('session_horizon_days')->default(60);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->unsignedSmallInteger('no_show_count')->default(0);
            $table->timestamp('no_show_reset_at')->nullable();
        });

        Schema::table('trainers', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_seats_per_session')->nullable();
            $table->unsignedSmallInteger('advance_booking_days')->nullable();
        });

        Schema::table('schedule_templates', function (Blueprint $table) {
            $table->unsignedSmallInteger('slot_duration_minutes')->default(60);
            $table->unsignedTinyInteger('capacity')->default(5);
        });

        Schema::table('schedule_exceptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('capacity')->nullable();
        });

        // ใช้ไฟล์สร้างตารางเดิมตามลำดับ ได้โครงเหมือนก่อนถอดทุกอย่าง
        foreach ([
            '2026_09_21_100008_create_workout_sessions_table.php',
            '2026_09_21_100009_create_bookings_table.php',
            '2026_09_21_100010_create_packages_table.php',
            '2026_10_06_100015_allow_self_bookings_with_approval.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }
};
