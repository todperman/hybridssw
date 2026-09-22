<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ชื่อเล่นใช้เรียกกันหน้างาน เก็บแยกจากชื่อจริงเพราะ users.name
            // เป็นคอลัมน์ generated จาก first_name + last_name เขียนทับไม่ได้
            $table->string('nickname', 60)->nullable()->after('last_name');
        });

        Schema::table('team_members', function (Blueprint $table) {
            // โน้ตอยู่ที่ pivot ไม่ใช่ที่ members เพราะลูกทีมคนเดียวอยู่ได้หลายทีม
            // โน้ตของเทรนเนอร์แต่ละคนจึงต้องแยกกันและไม่รั่วข้ามทีม
            $table->text('trainer_note')->nullable()->after('joined_via');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('nickname'));
        Schema::table('team_members', fn (Blueprint $table) => $table->dropColumn('trainer_note'));
    }
};
