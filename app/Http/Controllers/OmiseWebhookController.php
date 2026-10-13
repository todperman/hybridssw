<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * รับ webhook จาก Omise
 *
 * ชั้นที่ 1: ตรวจลายเซ็น HMAC-SHA256 ของ Omise ด้วย OMISE_WEBHOOK_SECRET (ถ้าตั้งไว้) ลายเซ็นผิดตอบ 401
 * ชั้นที่ 2: ไม่เชื่อสถานะใน payload เลย ใช้แค่รหัสรายการแล้วดึงของจริงจาก Omise ด้วย secret key
 *
 * ประมวลผลไม่สำเร็จยังตอบ 200 เพราะ Omise ไม่รับประกันการส่งซ้ำอยู่แล้ว
 * งาน payments:sync-omise จะกระทบยอดรายการที่ค้างให้เองทุกไม่กี่นาที
 */
class OmiseWebhookController extends Controller
{
    /** ยอมให้เวลาในลายเซ็นคลาดได้ไม่เกินนี้ กันการนำ webhook เก่ามายิงซ้ำ */
    public const TOLERANCE_SECONDS = 300;

    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        if (! $this->verified($request)) {
            Log::warning('webhook ของ Omise ลายเซ็นไม่ถูกต้อง', ['ip' => $request->ip()]);

            return response()->json(['ok' => false], 401);
        }

        try {
            $payments->handleOmiseEvent($request->json()->all());
        } catch (\Throwable $e) {
            Log::error('ประมวลผล webhook ของ Omise ไม่สำเร็จ', [
                'event' => $request->input('key'),
                'object' => $request->input('data.id'),
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    protected function verified(Request $request): bool
    {
        $secret = config('services.omise.webhook_secret');

        // ยังไม่ได้ตั้ง secret: ยังปลอดภัยเพราะชั้นที่ 2 แต่ gym:doctor จะเตือนให้ตั้ง
        if (blank($secret)) {
            return true;
        }

        $timestamp = (string) $request->header('Omise-Signature-Timestamp');
        $header = (string) $request->header('Omise-Signature');

        if ($timestamp === '' || $header === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $key = base64_decode($secret, true);

        if ($key === false) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $key);

        // ช่วงเปลี่ยน secret Omise ส่งมาสองลายเซ็นคั่นด้วยจุลภาค ตรงอันใดอันหนึ่งก็ผ่าน
        foreach (explode(',', $header) as $signature) {
            if (hash_equals($expected, trim($signature))) {
                return true;
            }
        }

        return false;
    }
}
