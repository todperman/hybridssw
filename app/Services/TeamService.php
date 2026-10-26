<?php

namespace App\Services;

use App\Models\Member;
use App\Models\TeamMember;
use App\Models\Trainer;
use App\Notifications\AccountNotice;
use App\Services\Notify\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * เอาสมาชิกที่มีบัญชีอยู่แล้ว (สมัครเองทั่วไป) เข้าทีมของเทรนเนอร์
 * ใช้ 3 ทาง: เทรนเนอร์ค้นด้วยเบอร์/รหัสในหน้า "ทีม", สมาชิกเปิดลิงก์คำเชิญขณะล็อกอินอยู่, แอดมินตั้ง "เทรนเนอร์ประจำ"
 */
class TeamService
{
    public const VIA_INVITE_LINK = 'invite_link';
    public const VIA_MANUAL = 'manual';
    public const VIA_ADMIN = 'admin';

    public function __construct(protected Notifier $notify) {}

    /** ข้อความภาษาคนว่าทำไมเข้าทีมไม่ได้ null = เข้าได้ */
    public function blocker(Trainer $trainer, Member $member): ?string
    {
        if (! $trainer->isApproved()) {
            return 'บัญชีเทรนเนอร์ยังไม่ได้รับอนุมัติ';
        }

        if ($member->branch_id !== $trainer->branch_id) {
            return 'สมาชิกคนนี้อยู่คนละสาขากับเทรนเนอร์';
        }

        if ($member->isSuspended()) {
            return 'สมาชิกคนนี้ถูกระงับสิทธิ์อยู่';
        }

        if ($this->isInTeam($trainer, $member)) {
            return 'อยู่ในทีมนี้อยู่แล้ว';
        }

        $limit = $trainer->maxTeamSize();

        if ($limit !== null && $trainer->teamMembers()->count() >= $limit) {
            return 'ทีมเต็มแล้ว ('.$limit.' คน)';
        }

        return null;
    }

    public function isInTeam(Trainer $trainer, Member $member): bool
    {
        return $trainer->teamMembers()->whereKey($member->id)->exists();
    }

    /**
     * เข้าทีม: สร้างหรือเปิดแถว team_members ใหม่ ตั้งเทรนเนอร์ประจำถ้ายังไม่มี
     * สมาชิกที่ยังรออนุมัติและเข้ามาทางลิงก์ของเทรนเนอร์ ถือว่าเทรนเนอร์รับรองแล้ว อนุมัติให้เลย (เหมือนสมัครใหม่ทางลิงก์)
     *
     * @throws \RuntimeException เมื่อเข้าไม่ได้ ข้อความใช้แสดงผู้ใช้ได้ตรง ๆ
     */
    public function join(Trainer $trainer, Member $member, string $via, bool $notifyMember = true): TeamMember
    {
        if ($reason = $this->blocker($trainer, $member)) {
            throw new \RuntimeException($reason);
        }

        $row = DB::transaction(function () use ($trainer, $member, $via) {
            $row = TeamMember::updateOrCreate(
                ['trainer_id' => $trainer->id, 'member_id' => $member->id],
                ['status' => 'active', 'joined_at' => now(), 'left_at' => null, 'joined_via' => $via],
            );

            if ($member->primary_trainer_id === null) {
                $member->forceFill(['primary_trainer_id' => $trainer->id])->save();
            }

            if ($via === self::VIA_INVITE_LINK && $member->awaitsApproval()) {
                $member->approve(null);
            }

            return $row;
        });

        $trainerName = $trainer->user?->displayName() ?? 'เทรนเนอร์';

        if ($notifyMember && $via !== self::VIA_INVITE_LINK) {
            $this->notify->deliver(
                new AccountNotice(
                    'คุณถูกเพิ่มเข้าทีมของ '.$trainerName,
                    ($via === self::VIA_ADMIN ? 'เจ้าหน้าที่' : $trainerName).' เพิ่มคุณเข้าทีมแล้ว ต่อจากนี้เทรนเนอร์จองยิมให้คุณได้ และคุณยังจองเองได้เหมือนเดิม',
                    route('member.reservations'),
                ),
                [$member->user],
                context: ['member' => $member->id, 'trainer' => $trainer->id],
            );
        }

        if ($via === self::VIA_INVITE_LINK && $trainer->user) {
            $this->notify->deliver(
                new AccountNotice(
                    'ลูกทีมใหม่เข้าทีมของคุณ',
                    $member->user->displayName().' (สมาชิกเดิม) เข้าทีมผ่านลิงก์คำเชิญแล้ว',
                    route('trainer.team'),
                ),
                [$trainer->user],
                context: ['member' => $member->id, 'trainer' => $trainer->id],
            );
        }

        return $row;
    }
}
