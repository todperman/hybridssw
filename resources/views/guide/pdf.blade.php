{{-- คู่มือฉบับ PDF สำหรับ mPDF: ใช้ตารางและ CSS พื้นฐานเท่านั้น (ไม่มี flex/grid) ฟอนต์ TH Sarabun
     เนื้อหาคู่ขนานกับหน้าเว็บ ตัวเลขทุกตัวมาจาก GuideData เหมือนกัน --}}
@php($steps = $showMember ? [
    ['สมัคร', $approvalNeeded ? 'กรอกข้อมูล รอเจ้าหน้าที่อนุมัติ แจ้งผลทางกระดิ่งและอีเมล' : 'กรอกข้อมูล ใช้งานได้ทันที'],
    ['ดูปฏิทิน', 'เห็นวัน/ชั่วโมงที่ว่าง และจำนวน Trainer ที่ว่างในชั่วโมงนั้น'],
    ['เลือกคนและ Trainer', 'สูงสุด '.$maxPeople.' คน ราคาคิดทั้งกลุ่ม คนที่มีสิทธิ์ฟรีถูกหักออกอัตโนมัติ'],
    ['ชำระใน '.$holdMinutes.' นาที', 'ระบบกันเวลาไว้ให้ระหว่างจ่าย ไม่มีใครจองทับได้'],
    ['ยืนยันการจอง', 'สถานะเปลี่ยนทันทีที่ชำระสำเร็จ แจ้งทุกคนในรอบ'],
    ['เข้าใช้ยิม', 'เตือนก่อน '.$reminderLead.' นาที แจ้งเลขอ้างอิงที่เคาน์เตอร์'],
] : [
    ['สมัครและอนุมัติ', 'ภายในเริ่มทันที ภายนอกรอตรวจใบรับรอง'],
    ['ตั้งเวลาว่าง', 'ประจำสัปดาห์ + เฉพาะวัน + วันลา'],
    ['ชวนลูกทีม', 'ลิงก์หรือ QR ลูกทีมสมัครเองเข้าทีมทันที'],
    ['จองให้ลูกเทรน', 'เลือกลูกทีม/กลุ่ม แล้วเลือกผู้ชำระ'],
    ['ลูกเทรนชำระใน '.$holdMinutes.' นาที', 'ระบบแจ้งลูกเทรนให้จ่ายเอง'],
    ['ดูงานของคุณ', 'ขอยกเลิกรับงานได้พร้อมเหตุผล'],
])
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<style>
    body { font-family: sarabun; font-size: 15pt; color: #1c2f3a; line-height: 1.35; }
    h1 { font-size: 30pt; color: #00334a; margin: 0 0 2pt; }
    h2 { font-size: 20pt; color: #007595; margin: 18pt 0 6pt; padding-bottom: 3pt; border-bottom: 1.5pt solid #2bbbd7; page-break-after: avoid; }
    h3 { page-break-after: avoid; }
    table.grid, table.steps, .box, .keep { page-break-inside: avoid; }
    h3 { font-size: 16.5pt; color: #00334a; margin: 10pt 0 3pt; }
    p { margin: 0 0 5pt; }
    ul, ol { margin: 0 0 6pt 0; padding-left: 18pt; }
    li { margin-bottom: 2pt; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }
    .muted { color: #5b676c; }
    .small { font-size: 12.5pt; }
    .chip { display: inline-block; border: 0.6pt solid #b9d9e3; background: #eaf5f8; color: #00516f; border-radius: 10pt; padding: 1pt 8pt; font-size: 12.5pt; margin-right: 4pt; }
    .box { border: 0.6pt solid #d6dcde; background: #f7f9fa; border-radius: 6pt; padding: 7pt 10pt; margin: 6pt 0; }
    .box-green { border-color: #a7e3c4; background: #effaf4; }
    .box-amber { border-color: #f3d58a; background: #fff8e6; }
    .steps td { padding: 5pt 6pt; border-bottom: 0.5pt solid #e3e9ec; }
    .steps td.num { padding: 3pt 0; vertical-align: middle; }
    .num { background: #00516f; color: #fff; border-radius: 4pt; text-align: center; font-weight: bold; font-size: 14pt; padding: 1pt 0; }
    .grid td { padding: 4pt 6pt; border: 0.5pt solid #d6dcde; }
    .grid th { padding: 4pt 6pt; border: 0.5pt solid #d6dcde; background: #e8eef1; color: #00334a; text-align: center; font-size: 14pt; }
    .right { text-align: right; }
    .center { text-align: center; }
    .cover { background: #00334a; color: #fff; padding: 18pt 20pt; border-radius: 8pt; }
    .cover h1 { color: #fff; }
    .cover .muted { color: #cfe3ea; }
    .fact { display: inline-block; background: #ffffff; color: #00334a; border-radius: 10pt; padding: 1pt 9pt; margin: 3pt 4pt 0 0; font-size: 13pt; font-weight: bold; }
    .q { font-weight: bold; color: #00334a; margin-top: 7pt; }
    .a { margin-left: 0; }
</style>
</head>
<body>

<div class="cover">
    <table><tr>
        <td width="68%">
            <div class="muted small">คู่มือการใช้งาน</div>
            <h1>{{ config('app.name') }}</h1>
            <div class="muted">{{ $branch?->name }} · สำหรับ{{ $showMember && $showTrainer ? 'สมาชิกและเทรนเนอร์' : ($showTrainer ? 'เทรนเนอร์' : 'สมาชิก') }} · ออกเมื่อ {{ now()->locale('th')->isoFormat('D MMMM YYYY') }}</div>
            <div style="margin-top: 8pt;">
                <span class="fact">จองได้ครั้งละ 1–{{ $maxHours }} ชม.</span>
                <span class="fact">ล่วงหน้าไม่เกิน {{ $window }} วัน</span>
                <span class="fact">สูงสุด {{ $maxPeople }} คนต่อรอบ</span>
                <span class="fact">รอชำระ {{ $holdMinutes }} นาที</span>
            </div>
        </td>
        <td width="32%" class="right"><img src="{{ public_path('img/brand-light.png') }}" style="width: 150pt;"></td>
    </tr></table>
</div>

<div class="keep">
<h2>1. {{ $showMember ? 'เส้นทางการจองใน 6 ขั้น' : 'เทรนเนอร์เริ่มงานใน 6 ขั้น' }}</h2>
<table class="steps">
    @foreach ($steps as $i => [$title, $desc])
        <tr>
            <td width="30pt" class="num">{{ $i + 1 }}</td>
            <td width="30%" style="padding-left: 8pt;"><strong>{{ $title }}</strong></td>
            <td class="muted">{{ $desc }}</td>
        </tr>
    @endforeach
</table>

</div>

<h2>2. บัญชีและการเข้าใช้งาน</h2>
<h3>สมัครใช้งาน</h3>
<ul>
    <li><strong>สมาชิกทั่วไป</strong> สมัครเองที่หน้า "สมัครสมาชิก" กรอกชื่อ เพศ เบอร์ อีเมล ผู้ติดต่อฉุกเฉิน ตั้งรหัสผ่าน{{ $approvalNeeded ? ' แล้วรอเจ้าหน้าที่อนุมัติก่อนจึงจองได้' : ' ใช้งานได้ทันที' }}</li>
    <li><strong>ลูกทีมของเทรนเนอร์</strong> เปิดลิงก์หรือสแกน QR ที่เทรนเนอร์ส่งให้ กรอกข้อมูลแล้วเข้าทีมอัตโนมัติ ใช้งานได้ทันที</li>
    <li><strong>เทรนเนอร์</strong> สมัครที่ "สมัครเป็นเทรนเนอร์" ระบุเพศ แนบใบรับรอง (ภายนอก) หรือกรอกรหัสพนักงาน (ภายใน) แล้วรอเจ้าหน้าที่ตรวจ</li>
</ul>
<h3>เข้าสู่ระบบ · โปรไฟล์ · การแจ้งเตือน</h3>
<ul>
    <li>ใช้อีเมลและรหัสผ่านที่ตั้งไว้ ลืมรหัสผ่านกด "ลืมรหัสผ่าน" ระบบส่งลิงก์ตั้งใหม่ทางอีเมล (ใช้ได้ 60 นาที)</li>
    <li>เมนู บัญชี > โปรไฟล์ แก้ชื่อ ชื่อเล่น เบอร์ รูปโปรไฟล์ และรหัสผ่าน</li>
    <li>ทุกเหตุการณ์แจ้ง 2 ทาง: กระดิ่งมุมขวาบน และอีเมลที่ใช้สมัคร ก่อนถึงเวลาจอง {{ $reminderLead }} นาทีมีแถบเตือนทุกหน้า</li>
</ul>

@if ($showMember)
<h2>3. จองยิม</h2>
<table class="steps">
    @foreach ([
        ['ดูวันเวลาที่ว่างจากปฏิทิน', 'เมนู "ปฏิทิน" เห็นทั้งเดือน เขียว = ว่างมาก เหลือง = เหลือน้อย เทา = เต็ม แตะวันเพื่อดูรายชั่วโมงและจำนวน Trainer ที่ว่าง กด "จอง" ที่ช่องว่างได้เลย'],
        ['วันและระยะเวลา > เวลาเริ่ม', 'ล่วงหน้าไม่เกิน '.$window.' วัน จองต่อเนื่อง 1–'.$maxHours.' ชม. ระบบแสดงเฉพาะช่วงที่ว่างจริง'],
        ['ผู้เข้าร่วม', 'เพิ่มได้สูงสุด '.$maxPeople.' คน ค้นจากเบอร์โทร ทุกคนต้องเป็นสมาชิก ราคาคิดทั้งกลุ่ม คนที่มีสิทธิ์ฟรีมีป้าย "ฟรี"'],
        ['Trainer', 'เลือกจากคนที่ว่างในช่วงนั้น "เข้าใช้เอง ไม่มี Trainer" เลือกได้เมื่อ'.($noTrainerRule === 'all' ? 'ทุกคน' : 'มีอย่างน้อยหนึ่งคน').'ในกลุ่มได้รับสิทธิ์จากเจ้าหน้าที่'],
        ['โค้ดส่วนลดและผู้ชำระเงิน', 'ใส่โค้ดถ้ามี ส่วนลดหักหลังหักส่วนที่ฟรี เลือกผู้ชำระ (ต้องอยู่ในกลุ่ม) คนนั้นได้รับแจ้งให้จ่ายและได้รับเงินคืนถ้ายกเลิก'],
        ['ยืนยันแล้วชำระใน '.$holdMinutes.' นาที', 'ระบบกันเวลาไว้ (หรือถึงเวลาเริ่ม) ชำระสำเร็จ = ยืนยันทันที ไม่จ่าย = หมดอายุ ต้องจองใหม่ ทุกคนฟรี = ยืนยันทันทีโดยไม่ต้องจ่าย'],
    ] as $i => [$title, $desc])
        <tr>
            <td width="30pt" class="num">{{ $i + 1 }}</td>
            <td width="30%" style="padding-left: 8pt;"><strong>{{ $title }}</strong></td>
            <td class="muted">{{ $desc }}</td>
        </tr>
    @endforeach
</table>
<h3>หลังจองแล้ว</h3>
<ul>
    <li><strong>เลื่อนวันเวลา</strong> เลื่อนเองได้จนถึงเที่ยงคืนก่อนวันใช้งาน (จองวันที่ 10 เลื่อนเองได้ถึง 9 เวลา 23:59) ราคาเท่าเดิม หลังจากนั้นส่งคำขอพร้อมเหตุผลให้เจ้าหน้าที่</li>
    <li><strong>ยกเลิก</strong> ทำโดยเจ้าหน้าที่ ติดต่อสาขาพร้อมเลขอ้างอิง ถ้ายิมให้บริการไม่ได้ เจ้าหน้าที่ยกเลิกและคืนเงินเต็มจำนวนอัตโนมัติ</li>
    <li><strong>การจองของฉัน</strong> รายการที่กำลังจะถึง รอชำระ และประวัติ แตะเพื่อดูผู้เข้าร่วม Trainer ราคา และสถานะเงิน</li>
</ul>
@endif

<div class="keep">
<h2>{{ $showMember ? '4' : '3' }}. ชำระเงิน</h2>
@if ($omise && $methods->isNotEmpty())
<table class="grid">
    <tr><th width="28%">ช่องทาง</th><th>วิธีจ่าย</th><th width="24%">ยืนยัน</th></tr>
    @foreach ($methods as $key => $m)
        <tr>
            <td><strong>{{ $m['label'] }}</strong><br><span class="small muted">{{ $m['hint'] }}</span></td>
            <td class="small">
                @switch($key)
                    @case('promptpay') กด "พร้อมเพย์" ระบบสร้าง QR ให้ เปิดแอปธนาคารสแกนและโอน ไม่ต้องส่งสลิป @break
                    @case('card') กรอกเลขบัตร วันหมดอายุ และ CVV ในหน้าที่ปลอดภัยของผู้ให้บริการ อาจต้องยืนยัน OTP ของธนาคาร @break
                    @case('mobile_banking') เลือกธนาคาร ระบบเปิดแอปธนาคารให้กดยืนยัน (คอมพิวเตอร์ได้ QR) @break
                    @case('rabbit_linepay') มือถือเปิดแอป LINE ให้ยืนยัน คอมพิวเตอร์ได้ QR ต้องยืนยันภายใน 20 นาที @break
                    @case('truemoney') ใส่เบอร์ที่ผูกกับ TrueMoney Wallet แล้วยืนยัน OTP ในแอป @break
                @endswitch
            </td>
            <td class="small center">{{ match ($key) { 'promptpay' => 'ภายในไม่กี่วินาที', 'card' => 'หลังผ่าน OTP', 'mobile_banking' => 'หลังยืนยันในแอป', 'rabbit_linepay' => 'หลังยืนยันใน LINE', 'truemoney' => 'หลัง OTP', default => 'ออนไลน์' } }}</td>
        </tr>
    @endforeach
</table>
@else
<ol>
    <li>โอนเงินตามยอดที่แสดง {{ $branch?->payment_instructions ?: 'ตามบัญชีที่แจ้งในหน้าการจอง หรือชำระที่เคาน์เตอร์' }}</li>
    <li>แจ้งเจ้าหน้าที่พร้อมเลขอ้างอิง เจ้าหน้าที่บันทึกรับชำระ สถานะเปลี่ยนเป็น "ยืนยันการจอง"</li>
</ol>
@endif
</div>
<div class="box box-amber small">ต้องชำระภายใน {{ $holdMinutes }} นาทีหลังจอง เลยเวลา รายการจะหมดอายุและคืนช่วงเวลาให้คนอื่น · จ่ายแล้วแต่ยังขึ้นรอ: รอ 1–2 นาทีหรือรีเฟรช ถ้าเงินถูกตัดแต่การจองหมดอายุไปก่อน เจ้าหน้าที่จะตรวจและคืนเงินเต็มจำนวน</div>
<h3>การคืนเงิน</h3>
<ul class="small">
    <li>คืนเต็มจำนวนเมื่อเจ้าหน้าที่ยกเลิกการจอง ไปยังผู้ชำระและช่องทางเดิม</li>
    <li>บัตร Rabbit LINE Pay และ TrueMoney คืนอัตโนมัติ เงินกลับตามรอบธนาคาร 5–15 วันทำการ · พร้อมเพย์และ Mobile Banking เจ้าหน้าที่โอนคืนและติดต่อขอเลขบัญชี</li>
</ul>

@if ($priceRows !== [])
<div class="keep">
<h2>{{ $showMember ? '5' : '4' }}. ตารางราคา (บาทต่อชั่วโมง ทั้งกลุ่ม)</h2>
<table class="grid">
    <tr><th>จำนวนคน</th><th>มี Trainer</th><th>เฉลี่ยต่อคน</th><th>ไม่มี Trainer</th><th>เฉลี่ยต่อคน</th></tr>
    @foreach ($priceRows as $row)
        <tr>
            <td class="center"><strong>{{ $row['people'] }} คน</strong></td>
            <td class="right">฿{{ number_format($row['with']) }}</td>
            <td class="right muted">฿{{ number_format($row['with'] / $row['people']) }}</td>
            <td class="right">฿{{ number_format($row['without']) }}</td>
            <td class="right muted">฿{{ number_format($row['without'] / $row['people']) }}</td>
        </tr>
    @endforeach
</table>
<p class="small muted">จองหลายชั่วโมงคูณตามจำนวนชั่วโมง · "ไม่มี Trainer" เฉพาะกลุ่มที่ได้รับสิทธิ์เข้าใช้เอง · โค้ดส่วนลดหักจากยอดที่เหลือ ยอดสุดท้ายไม่ต่ำกว่า 20 บาท</p>
</div>
@endif

@if ($showMember)
<h2>6. สิทธิ์ใช้ฟรีสำหรับบุคลากรโรงพยาบาลและแพทย์</h2>
<ol>
    <li><strong>แจ้งตอนสมัคร</strong> ติ๊ก "เป็นบุคลากร/แพทย์ของโรงพยาบาล" และกรอกรหัสพนักงานหรือเลขใบประกอบวิชาชีพ</li>
    <li><strong>เจ้าหน้าที่กำหนดประเภทสมาชิก</strong> หลังอนุมัติ พร้อมวันหมดอายุของสิทธิ์ ระบบบันทึกประวัติ</li>
    <li><strong>ส่วนของคุณฟรีอัตโนมัติ</strong> ทุกครั้งที่จอง ยอดรวมหัก "ส่วนต่อคน" ของคุณออก รวมค่า Trainer ด้วย เพื่อนร่วมรอบจ่ายส่วนของตัวเอง ทุกคนมีสิทธิ์ = 0 บาท ยืนยันทันที</li>
    <li><strong>โควตารายเดือน</strong> ใช้ครบแล้วชั่วโมงที่เหลือคิดราคาปกติ นับใหม่ทุกต้นเดือน รายการที่หมดอายุหรือยกเลิกคืนโควตา สิทธิ์หมดอายุตามวันที่เจ้าหน้าที่ตั้ง</li>
    <li><strong>เข้าใช้เองโดยไม่มี Trainer</strong> เป็นสิทธิ์แยกที่เจ้าหน้าที่ให้รายคน ใช้ตารางราคาแบบไม่มี Trainer</li>
</ol>
@if ($example)
<div class="box box-green">ตัวอย่าง {{ $example['people'] }} คน มี Trainer 1 ชม. = ฿{{ number_format($example['total']) }} · มี 1 คนที่มีสิทธิ์ หักส่วนของคนนั้น ฿{{ number_format($example['share']) }} · <strong>จ่ายจริง ฿{{ number_format($example['total'] - $example['share']) }}</strong>
@if ($memberTypes->isNotEmpty())<br><span class="small">ประเภทที่เปิดอยู่: @foreach ($memberTypes as $t)<span class="chip">{{ $t->name }} · {{ $t->quotaLabel() }}</span>@endforeach</span>@endif
</div>
@endif
@endif

@if ($showTrainer)
<h2>{{ $showMember ? '7' : '5' }}. สำหรับเทรนเนอร์</h2>
<ol>
    <li><strong>สถานะใบสมัคร</strong> ภายในที่ยืนยันรหัสพนักงานแล้วเริ่มงานทันที ภายนอกรอตรวจใบรับรอง ใบรับรองหมดอายุจะรับงานใหม่ไม่ได้จนกว่าจะอัปเดต</li>
    <li><strong>ตั้งเวลาว่าง</strong> เมนู "เวลาว่าง": ประจำสัปดาห์ · ว่างเพิ่มเฉพาะวัน · วันลา ลูกเทรนเห็นคุณเป็น "Trainer ว่าง" เฉพาะชั่วโมงที่อยู่ในเวลาว่างและยังไม่มีงาน ลาทับงานที่มีคนจองไม่ได้ ต้องส่งคำขอยกเลิกการรับงาน</li>
    <li><strong>ชวนลูกทีม</strong> เมนู "ทีม" คัดลอกลิงก์หรือให้สแกน QR ลูกทีมสมัครเองเข้าทีมทันที เพิ่มสมาชิกเดิมได้จาก "เพิ่มลูกทีมเอง" เทรนเนอร์ภายนอกมีจำนวนลูกทีมสูงสุดตามสาขากำหนด</li>
    <li><strong>กลุ่มลูกทีม</strong> จัดคนที่มาเทรนด้วยกันเป็นกลุ่ม ตอนจองเลือกทั้งกลุ่มได้ในคลิกเดียว</li>
    <li><strong>จองให้ลูกเทรน</strong> ขั้นตอนเหมือนสมาชิก คุณเป็น Trainer ประจำการจองเสมอ เลือกลูกเทรนที่เป็นผู้ชำระ คนนั้นได้รับแจ้งให้จ่ายภายใน {{ $holdMinutes }} นาที ยอดใช้ตาราง "มี Trainer" หักส่วนลูกเทรนที่มีสิทธิ์ฟรี</li>
    <li><strong>งาน</strong> รวมทุกการจองที่คุณเป็น Trainer รับงานต่อไม่ได้กด "ขอยกเลิกการรับงาน" พร้อมเหตุผล เจ้าหน้าที่หา Trainer แทน ลูกเทรนไม่เสียการจอง</li>
</ol>
@endif

<h2>{{ ($showMember ? 7 : 5) + ($showTrainer ? 1 : 0) }}. คำถามพบบ่อย</h2>
@foreach ([
    ['จองแล้วแต่ยังไม่ได้จ่าย ช่วงเวลานั้นเป็นของฉันแน่ไหม', 'ใช่ ระบบกันไว้ '.$holdMinutes.' นาที (หรือจนถึงเวลาเริ่ม) คนอื่นจองทับไม่ได้ หมดเวลาจะคืนช่วงเวลาให้คนอื่น'],
    ['ทำไมปฏิทินไม่แสดงเวลาที่ต้องการ', 'ยิมปิด/วันหยุดพิเศษ มีคนจองแล้ว เลยระยะ '.$window.' วัน หรือเวลาเริ่มผ่านไปแล้ว ลองเลือกวันอื่น'],
    ['เลือก Trainer ไม่ได้', 'Trainer โผล่เฉพาะคนที่ตั้งเวลาว่างครอบคลุมช่วงนั้นและยังไม่มีงาน ลองเปลี่ยนเวลา หรือให้เทรนเนอร์ของคุณจองให้'],
    ['เงินถูกตัดแล้วแต่สถานะยังรอชำระ', 'ปกติยืนยันในไม่กี่วินาที เกิน 2 นาทีให้รีเฟรช ถ้ายังไม่เปลี่ยนติดต่อเจ้าหน้าที่พร้อมเลขอ้างอิง ระบบตรวจย้อนหลังกับผู้ให้บริการทุก 2 นาที เงินไม่หาย'],
    ['อยากเปลี่ยนวันเวลา', 'เปิดหน้าการจอง กด "เลือกวันเวลาใหม่" ก่อนเที่ยงคืนของวันก่อนใช้งานเลื่อนได้ทันที หลังจากนั้นเป็นคำขอให้เจ้าหน้าที่'],
    ['เพื่อนไม่ได้เป็นสมาชิก เพิ่มได้ไหม', 'ทุกคนต้องมีบัญชี ให้เพื่อนสมัครที่ "สมัครสมาชิก" ก่อน แล้วเพิ่มด้วยเบอร์โทร'],
    ['โค้ดส่วนลดใช้ไม่ได้', 'โค้ดมีเงื่อนไข (สาขา แบบมี/ไม่มี Trainer ยอดขั้นต่ำ จำนวนครั้ง วันหมดอายุ) ระบบบอกเหตุผลใต้ช่องกรอก'],
    ['ไม่ได้รับอีเมล', 'ดูในสแปม ตรวจอีเมลในโปรไฟล์ การแจ้งเตือนทุกฉบับดูได้ที่กระดิ่งเสมอ'],
] as [$q, $a])
    <p class="q">{{ $q }}</p>
    <p class="a muted">{{ $a }}</p>
@endforeach

@if ($branch)
<h2>ติดต่อเจ้าหน้าที่</h2>
<p><strong>{{ $branch->name }}</strong>@if ($branch->phone) · โทร {{ \App\Rules\ThaiPhone::format($branch->phone) }}@endif</p>
@if ($branch->address)<p class="muted">{{ $branch->address }}</p>@endif
<p class="small muted">แจ้งเลขอ้างอิงการจอง (ขึ้นต้นด้วย HS) ทุกครั้งที่ติดต่อ · เว็บไซต์ {{ config('app.url') }}</p>
@endif

</body>
</html>
