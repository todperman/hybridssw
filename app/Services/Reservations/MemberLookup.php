<?php

namespace App\Services\Reservations;

use App\Models\Branch;
use App\Models\Member;
use App\Rules\ThaiPhone;

/**
 * หาเพื่อนร่วมกลุ่มด้วยรหัสสมาชิกหรือเบอร์โทรที่ตรงกันเป๊ะเท่านั้น
 * ไม่มีการค้นแบบบางส่วนหรือเปิดรายชื่อสมาชิกทั้งหมดให้เรียกดู ตามข้อกำหนดข้อ 5
 */
class MemberLookup
{
    public function find(Branch $branch, string $query): ?Member
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $digits = ThaiPhone::digits($query);

        $member = Member::query()
            ->where('branch_id', $branch->id)
            ->where(function ($q) use ($query, $digits) {
                $q->where('member_code', strtoupper($query));

                if (strlen($digits) >= 9) {
                    $q->orWhereHas('user', fn ($u) => $u->where('phone', $digits));
                }
            })
            ->with('user')
            ->first();

        // ไม่บอกว่าเจอแต่เพิ่มไม่ได้ เพื่อไม่ให้ใช้เดาว่าเบอร์ไหนเป็นสมาชิก
        return $member?->canJoinReservations() ? $member : null;
    }
}
