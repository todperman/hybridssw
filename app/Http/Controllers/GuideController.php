<?php

namespace App\Http\Controllers;

use App\Support\GuideData;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * คู่มือการใช้งานสำหรับคนที่ล็อกอินแล้ว เนื้อหาเปลี่ยนตามบทบาทและดึงกติกาจริงจากการตั้งค่า
 * (ชั่วโมงสูงสุด ระยะจองล่วงหน้า เวลารอชำระ ช่องทางชำระ ราคาของสาขา) จะได้ไม่ต้องแก้คู่มือทุกครั้งที่ตั้งค่าเปลี่ยน
 */
class GuideController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('guide', GuideData::for($request->user()));
    }
}
