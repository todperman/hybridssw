<?php

namespace App\Http\Controllers;

use App\Support\GuideData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * คู่มือการใช้งานฉบับ PDF ฟอนต์ TH Sarabun สร้างสดจากข้อมูลจริงทุกครั้งที่กด
 * ใช้ mPDF เพราะวางสระ/วรรณยุกต์ไทยได้ถูกต้องและฝังฟอนต์เองได้ ไม่ต้องพึ่งเบราว์เซอร์บนเซิร์ฟเวอร์
 */
class GuidePdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // ฟอนต์ไทยแบบ OTL กินหน่วยความจำตอนสร้างครั้งแรก (ครั้งต่อไป mPDF ใช้ cache ใน storage/app/mpdf)
        // ค่าเริ่มต้นของโฮสต์มัก 128M ซึ่งไม่พอ ขอเพิ่มเฉพาะคำขอนี้
        if ((int) ini_get('memory_limit') !== -1) {
            @ini_set('memory_limit', '512M');
        }

        $data = GuideData::for($request->user());
        $html = view('guide.pdf', $data)->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 18,
            'margin_bottom' => 18,
            'margin_footer' => 8,
            'tempDir' => storage_path('app/mpdf'),
            'fontDir' => array_merge((new ConfigVariables)->getDefaults()['fontDir'], [resource_path('fonts')]),
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'] + [
                'sarabun' => [
                    'R' => 'THSarabun-Regular.ttf',
                    'B' => 'THSarabun-Bold.ttf',
                    'BI' => 'THSarabun-BoldItalic.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => 'sarabun',
            'default_font_size' => 15,
            'autoLangToFont' => false,
            'autoScriptToLang' => false,
        ]);

        $mpdf->SetTitle('คู่มือการใช้งาน '.config('app.name'));
        $mpdf->SetAuthor(config('app.name'));
        $mpdf->SetHTMLFooter('<table width="100%" style="font-size: 11pt; color: #5b676c; border-top: 0.5pt solid #d6dcde;"><tr>'
            .'<td width="50%">'.e(config('app.name')).' · คู่มือการใช้งาน · ออกเมื่อ '.now()->format('d/m/Y').'</td>'
            .'<td width="50%" style="text-align: right;">หน้า {PAGENO} / {nbpg}</td></tr></table>');
        $mpdf->WriteHTML($html);

        $filename = 'คู่มือการใช้งาน-HybridSSW.pdf';

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline')
                .'; filename="hybridssw-guide.pdf"; filename*=UTF-8\'\''.rawurlencode($filename),
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
