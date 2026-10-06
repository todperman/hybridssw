<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * คนทั่วไปที่สมัครเองต้องรอแอดมินอนุมัติก่อนจองได้
 * เก็บว่าใครอนุมัติเมื่อไหร่ และบันทึกเหตุผลที่สมาชิกจะเห็นถ้าไม่ผ่าน
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable()->after('approved_by_user_id');
        });

        // สมาชิกที่มีอยู่แล้วถือว่าผ่านแล้วทั้งหมด ประวัติจะได้ไม่มีช่องว่าง
        DB::table('members')->whereNull('approved_at')->update(['approved_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['approved_at', 'review_note']);
        });
    }
};
