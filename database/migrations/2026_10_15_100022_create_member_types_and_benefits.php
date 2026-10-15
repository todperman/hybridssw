<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ประเภทสมาชิกที่ได้สิทธิ์ใช้ฟรี เช่น บุคลากร รพ. และแพทย์
 *
 * สมาชิกทั่วไปไม่มีประเภท (member_type_id = null) จ่ายตามตารางราคาปกติ
 * สมาชิกที่มีประเภทได้ส่วนของตัวเองฟรี (รวมค่า Trainer) ตามโควตาชั่วโมงต่อเดือนของประเภทนั้น
 * แอดมินเป็นคนกำหนดประเภทหลังตรวจสอบ พร้อมวันหมดอายุสิทธิ์และเหตุผล เก็บประวัติไว้ทุกครั้ง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80);
            $table->string('description')->nullable();
            // null = ไม่จำกัด
            $table->unsignedSmallInteger('free_hours_per_month')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('member_type_id')->nullable()->after('primary_trainer_id')->constrained()->nullOnDelete();
            $table->date('benefit_expires_at')->nullable()->after('member_type_id');
            $table->string('benefit_note')->nullable()->after('benefit_expires_at');
            // สิ่งที่ผู้สมัครอ้างตอนสมัคร เช่น "บุคลากร รพ. รหัส 12345" ให้แอดมินตรวจก่อนกำหนดประเภท
            $table->string('benefit_claim')->nullable()->after('benefit_note');
        });

        Schema::table('reservation_participants', function (Blueprint $table) {
            // ชั่วโมงของคนนี้ในการจองนี้ที่ได้ฟรีจากสิทธิ์ประเภทสมาชิก ใช้นับโควตารายเดือน
            $table->unsignedTinyInteger('free_hours')->default(0)->after('member_id');
        });

        $now = now();

        DB::table('member_types')->insert([
            ['code' => 'hospital_staff', 'name' => 'บุคลากร รพ.', 'description' => 'พนักงานโรงพยาบาลศรีสวรรค์', 'free_hours_per_month' => null, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'doctor', 'name' => 'แพทย์', 'description' => 'แพทย์โรงพยาบาลศรีสวรรค์', 'free_hours_per_month' => null, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('reservation_participants', fn (Blueprint $table) => $table->dropColumn('free_hours'));

        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_type_id');
            $table->dropColumn(['benefit_expires_at', 'benefit_note', 'benefit_claim']);
        });

        Schema::dropIfExists('member_types');
    }
};
