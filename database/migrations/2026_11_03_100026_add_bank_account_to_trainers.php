<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** บัญชีรับเงินส่วนแบ่งของเทรนเนอร์ กรอกเองในหน้ารายได้ แอดมินเห็นตอนจ่าย */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->string('bank_code', 10)->nullable()->after('review_note');
            $table->string('bank_account_number', 20)->nullable()->after('bank_code');
            $table->string('bank_account_name', 120)->nullable()->after('bank_account_number');
            $table->string('promptpay_id', 20)->nullable()->after('bank_account_name');
            $table->dateTime('bank_updated_at')->nullable()->after('promptpay_id');
        });
    }

    public function down(): void
    {
        Schema::table('trainers', fn (Blueprint $table) => $table->dropColumn(['bank_code', 'bank_account_number', 'bank_account_name', 'promptpay_id', 'bank_updated_at']));
    }
};
