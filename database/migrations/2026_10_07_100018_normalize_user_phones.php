<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * เบอร์ที่แอดมินกรอกจากหลังบ้านเคยเก็บพร้อมขีด ทำให้ค้นหาเพื่อนด้วยเบอร์โทรไม่เจอ
 * ตั้งแต่นี้ User เก็บเป็นตัวเลขล้วนเอง ไฟล์นี้แปลงของเก่าให้เป็นแบบเดียวกัน
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('phone')->orderBy('id')->each(function ($user) {
            $digits = preg_replace('/\D+/', '', $user->phone);

            if ($digits !== $user->phone) {
                DB::table('users')->where('id', $user->id)->update(['phone' => $digits === '' ? null : $digits]);
            }
        });
    }

    public function down(): void
    {
        // แปลงกลับไม่ได้ และไม่จำเป็น เบอร์ตัวเลขล้วนใช้ได้ทุกหน้า
    }
};
