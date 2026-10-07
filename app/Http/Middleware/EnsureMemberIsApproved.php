<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * สมาชิกที่สมัครเองต้องรอแอดมินอนุมัติก่อนจะใช้หน้าจองได้
 *
 * ยังไม่อนุมัติจึงพาไปหน้าสถานะการสมัครหน้าเดียว แบบเดียวกับเทรนเนอร์ที่รออนุมัติ
 * BookingService ก็กันไว้อีกชั้น หน้านี้มีไว้ให้คนสมัครรู้ว่ากำลังรออะไร
 */
class EnsureMemberIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = $request->user()?->member;

        abort_if($member === null, 403, 'หน้านี้สำหรับสมาชิกเท่านั้น');

        // ยกเว้นหน้าสถานะเอง ไม่งั้นจะ redirect วนไม่จบ
        if ($member->awaitsApproval() && ! $request->routeIs('member.pending')) {
            return redirect()->route('member.pending');
        }

        if (! $member->awaitsApproval() && $request->routeIs('member.pending')) {
            return redirect()->route('member.book');
        }

        return $next($request);
    }
}
