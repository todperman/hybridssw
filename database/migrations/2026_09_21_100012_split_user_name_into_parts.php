<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 120)->nullable()->after('branch_id');
            $table->string('last_name', 120)->nullable()->after('first_name');
        });

        // แยกชื่อเดิมที่ช่องว่างแรก ส่วนที่เหลือถือเป็นนามสกุล
        DB::statement("
            UPDATE users
            SET first_name = SUBSTRING_INDEX(name, ' ', 1),
                last_name = TRIM(SUBSTRING(name, CHAR_LENGTH(SUBSTRING_INDEX(name, ' ', 1)) + 1))
        ");

        DB::statement("UPDATE users SET last_name = '' WHERE last_name IS NULL");

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 120)->nullable(false)->change();
            $table->string('last_name', 120)->nullable(false)->default('')->change();
            $table->dropColumn('name');
        });

        // name กลายเป็นคอลัมน์ที่ฐานข้อมูลคำนวณให้เอง
        // ทำให้โค้ดที่อ่าน $user->name ทั้งหมดยังทำงานเหมือนเดิม
        // และยังค้นหา/เรียงลำดับด้วย name ได้ตรงๆ โดยไม่ต้องซิงก์เอง
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->storedAs("TRIM(CONCAT_WS(' ', first_name, last_name))")->after('last_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['first_name', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['first_name', 'last_name']);
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('last_name')->default('');
        });

        DB::statement("UPDATE users SET name = TRIM(CONCAT_WS(' ', first_name, last_name))");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
