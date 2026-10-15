<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ค่าที่ตั้งในหลังบ้าน (ช่องทางชำระ คีย์ Omise) ทับค่าใน .env
        // ตอนยังไม่ได้ migrate (ติดตั้งครั้งแรก) ตารางยังไม่มี ข้ามไปใช้ .env
        try {
            \App\Support\PaymentSettings::apply();
        } catch (\Throwable) {
            // ใช้ค่าจาก .env ตามเดิม
        }
    }
}
