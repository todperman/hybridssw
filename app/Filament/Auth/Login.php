<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * หน้าเข้าสู่ระบบหลังบ้าน ใช้เลย์เอาต์สองฝั่งชุดเดียวกับหน้าบ้าน
 * ตัวฟอร์มยังเป็นของ Filament ทั้งหมด เปลี่ยนแค่กรอบที่ห่ออยู่
 */
class Login extends BaseLogin
{
    protected string $view = 'filament.auth.login';

    /** ใช้เลย์เอาต์เปล่าเพราะเราวาดโครงหน้าเองทั้งหมด */
    protected static string $layout = 'filament-panels::components.layout.base';

    public function getHeading(): string
    {
        return 'เข้าสู่ระบบผู้ดูแล';
    }

    public function getSubheading(): ?string
    {
        return 'สำหรับแอดมินและเจ้าหน้าที่หน้าเคาน์เตอร์';
    }
}
