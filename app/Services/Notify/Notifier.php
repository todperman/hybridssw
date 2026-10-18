<?php

namespace App\Services\Notify;

use App\Models\Branch;
use App\Models\User;
use App\Notifications\GymNotice;
use App\Services\DemoData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * ส่งแจ้งเตือนให้ผู้ใช้ และส่งสำเนาเข้ากล่องอีเมลผู้ดูแล (ตั้งค่าได้ที่หลังบ้าน → อีเมลแจ้งเตือน)
 *
 * ส่งทันทีไม่เข้าคิว เพราะเซิร์ฟเวอร์ไม่มีตัวรันคิว และถ้าส่งเมลพังต้องไม่ทำให้งานหลักพัง
 * จึงจับ error ไว้แล้วบันทึก log แทน การจองหรือการสมัครสำเร็จสำคัญกว่าอีเมลหนึ่งฉบับ
 */
class Notifier
{
    /**
     * @param  iterable<User|null>  $users
     * @param  bool  $copyAdmin  ส่งสำเนาเข้ากล่องผู้ดูแลด้วยไหม (ปิดสำหรับเมลที่ผู้ดูแลได้รับฉบับของตัวเองอยู่แล้ว)
     * @param  array<string, mixed>  $context  ข้อมูลประกอบ log เวลาส่งไม่สำเร็จ
     */
    public function deliver(GymNotice $notice, iterable $users, bool $copyAdmin = true, array $context = []): void
    {
        $recipients = collect($users)->filter()->unique('id')->values();

        $recipients->each(function (User $user) use ($notice, $context) {
            try {
                $user->notify($notice);
            } catch (\Throwable $e) {
                Log::warning('ส่งแจ้งเตือนไม่สำเร็จ', $context + ['user' => $user->id, 'title' => $notice->title, 'error' => $e->getMessage()]);
            }
        });

        if ($copyAdmin) {
            $this->copyToAdmin($notice, $recipients, $context);
        }
    }

    /**
     * สำเนาเข้ากล่องผู้ดูแลหนึ่งฉบับต่อเหตุการณ์ (ไม่ใช่ต่อผู้รับ)
     * ข้ามเมื่อผู้ดูแลเป็นผู้รับโดยตรงอยู่แล้ว หรือเหตุการณ์เป็นของบัญชีตัวอย่าง
     *
     * @param  Collection<int, User>  $recipients
     * @param  array<string, mixed>  $context
     */
    public function copyToAdmin(GymNotice $notice, Collection $recipients, array $context = []): void
    {
        $email = self::adminEmail();

        if ($email === null || ! config('gym.notifications.mail', true)) {
            return;
        }

        if ($recipients->contains(fn (User $u) => strcasecmp((string) $u->email, $email) === 0)) {
            return;
        }

        if ($recipients->contains(fn (User $u) => DemoData::isDemoEmail($u->email))) {
            return;
        }

        try {
            Notification::route('mail', $email)
                ->notify($notice->asAdminCopy($recipients->map(fn (User $u) => $u->displayName())->all()));
        } catch (\Throwable $e) {
            Log::warning('ส่งสำเนาถึงผู้ดูแลไม่สำเร็จ', $context + ['title' => $notice->title, 'error' => $e->getMessage()]);
        }
    }

    /** อีเมลผู้ดูแลที่รับสำเนาทุกเหตุการณ์ null = ไม่ได้ตั้งหรือรูปแบบไม่ถูก */
    public static function adminEmail(): ?string
    {
        $email = trim((string) config('gym.notifications.admin_email', ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** แอดมินและเจ้าหน้าที่ของสาขา รวมคนที่ดูแลทุกสาขา */
    public function admins(Branch $branch): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => $u->role?->canAccessAdminPanel()
                && ($u->branch_id === null || $u->branch_id === $branch->id || $u->role->isGlobal()))
            ->values();
    }
}
