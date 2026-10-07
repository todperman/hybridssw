<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Omise เรียก webhook จากเซิร์ฟเวอร์ของเขาเอง ไม่มี CSRF token
        // ปลอดภัยเพราะตัวรับไม่เชื่อ payload เลย ดึงผลจาก Omise ด้วย secret key มาตรวจทุกครั้ง
        $middleware->validateCsrfTokens(except: ['webhooks/omise']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * หน้าที่เปิดค้างไว้นานจนเซสชันหมดอายุ แล้วกดส่งฟอร์ม จะเจอหน้า 419 PAGE EXPIRED เปล่าๆ
         * ซึ่งผู้ใช้ไม่รู้ว่าต้องทำอะไรต่อ โยนกลับไปหน้าเดิมพร้อมบอกให้ลองใหม่แทน
         */
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return redirect()
                ->back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('status', 'หน้านี้เปิดค้างไว้นานจนหมดอายุ กรุณากดส่งอีกครั้ง');
        });
    })->create();
