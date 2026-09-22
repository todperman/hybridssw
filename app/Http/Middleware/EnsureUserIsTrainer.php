<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsTrainer
{
    public function handle(Request $request, Closure $next): Response
    {
        $trainer = $request->user()?->trainer;

        abort_if($trainer === null, 403, 'หน้านี้สำหรับเทรนเนอร์เท่านั้น');

        /*
         * ยังไม่อนุมัติ = ใช้หน้าอื่นไม่ได้เลย จองก็ไม่ได้ ชวนลูกทีมก็ไม่ได้
         * จึงพาไปหน้ารอผลอนุมัติหน้าเดียว แทนที่จะปล่อยเข้าไปเจอแถบเตือนแล้วงงว่าต้องทำอะไรต่อ
         *
         * ยกเว้นหน้ารอผลเอง ไม่งั้นจะ redirect วนไม่จบ
         */
        if (! $trainer->isApproved() && ! $request->routeIs('trainer.pending')) {
            return redirect()->route('trainer.pending');
        }

        // อนุมัติแล้วไม่ต้องมาค้างที่หน้ารอผล
        if ($trainer->isApproved() && $request->routeIs('trainer.pending')) {
            return redirect()->route('trainer.schedule');
        }

        return $next($request);
    }
}
