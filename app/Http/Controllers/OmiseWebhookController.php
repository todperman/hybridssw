<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * รับ webhook จาก Omise
 *
 * ตอบ 200 เสมอแม้ประมวลผลไม่สำเร็จ ไม่งั้น Omise จะยิงซ้ำไปเรื่อย ๆ
 * ความผิดพลาดบันทึกลง log ให้ตามดู และการยิงซ้ำของ Omise ก็ไม่ทำให้บันทึกยอดซ้ำอยู่แล้ว
 */
class OmiseWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        try {
            $payments->handleOmiseEvent($request->all());
        } catch (\Throwable $e) {
            Log::error('ประมวลผล webhook ของ Omise ไม่สำเร็จ', [
                'event' => $request->input('key'),
                'charge' => $request->input('data.id'),
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
