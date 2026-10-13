<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ช่องทางย่อยของการชำระ (promptpay / card / transfer) แยกจาก provider
 * ต้องรู้เพื่อคืนเงินให้ถูกวิธี: บัตรคืนผ่าน Omise ได้ ส่วน PromptPay Omise คืนให้ไม่ได้ ต้องโอนคืนเอง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method', 20)->nullable()->after('provider');
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['provider', 'status']);
            $table->dropColumn('method');
        });
    }
};
