<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** เทรนเนอร์ระบุเพศตอนสมัคร (สมาชิกมีคอลัมน์นี้อยู่แล้ว) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
