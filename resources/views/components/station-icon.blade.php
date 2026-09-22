@props([
    'name' => 'kettlebell',
    'stroke' => '1.6',
])

{{--
    ชุดอุปกรณ์ซ้อมแบบไฮบริด วาดขึ้นเองทั้งหมดเป็นเส้นเดี่ยว (monoline)
    ใช้ currentColor เพื่อให้เปลี่ยนสีตามบริบทที่วางได้เลย และคมทุกความละเอียด

    ตั้งใจไม่ใช้ภาพถ่ายหรือโลโก้จากภายนอก เพราะภาพสต็อกและเครื่องหมายการค้า
    มีข้อจำกัดด้านสิทธิ์ที่ไม่ควรเอามาใช้บนเว็บที่ให้บริการลูกค้าจริง
--}}
<svg {{ $attributes->merge(['class' => 'h-6 w-6']) }}
     viewBox="0 0 48 48" fill="none" stroke="currentColor"
     stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">

    @switch($name)
        {{-- ---------- ไอคอนประจำสถานี ----------
             ชุดนี้วาดจาก "การเคลื่อนไหว" ไม่ใช่รูปอุปกรณ์เพียงอย่างเดียว
             เพราะบางสถานีใช้อุปกรณ์ตัวเดียวกันแต่คนละท่า เช่น ดันเลื่อนกับลากเลื่อน
             ถ้าวาดแต่ตัวเลื่อนจะได้ไอคอนซ้ำกันจนแยกไม่ออก --}}

        @case('ski-erg')
            {{-- เสาเครื่อง + สายสองเส้น + ลูกศรชี้ลง สื่อจังหวะดึงลง --}}
            <path d="M24 9v9"/>
            <path d="M14 18h20"/>
            <path d="M18 18v9M30 18v9"/>
            <path d="M14.5 27h7M26.5 27h7"/>
            <path d="M24 30v8"/>
            <path d="M20.5 34.5 24 38l3.5-3.5"/>
            @break

        @case('sled-push')
            {{-- ตัวเลื่อนพร้อมของบนแท่น และลูกศรไปข้างหน้า --}}
            <path d="M8 36h20l5-5"/>
            <path d="M13 36v-9M23 36v-9"/>
            <path d="M11 27h14v-6H11z"/>
            <path d="M35 20l5 5-5 5"/>
            @break

        @case('sled-pull')
            {{-- ตัวเลื่อนหันกลับ มีเชือกโค้งและลูกศรเข้าหาตัว --}}
            <path d="M40 36H20l-5-5"/>
            <path d="M35 36v-9M25 36v-9"/>
            <path d="M23 27h14v-6H23z"/>
            <path d="M18 24c-4 0-6 3-10 3"/>
            <path d="M13 22l-5 5 5 5"/>
            @break

        @case('burpee')
            {{-- นอนลงพื้นทางซ้าย แล้วกระโดดข้ามไปลงทางขวา
                 เส้นประคือวิถีกระโดด ลูกศรชี้ลงตรงจุดลงพื้น --}}
            <path d="M8 39h32"/>
            <path d="M10 35h8"/>
            <circle cx="9" cy="32" r="2"/>
            <path d="M13 31Q24 12 35 31" stroke-dasharray="3 3"/>
            <path d="M31.5 27.5 35 31l3.5-3.5"/>
            <path d="M31 35h7"/>
            @break

        @case('row')
            {{-- ล้อ โซ่ ด้ามจับ และรางเบาะ --}}
            <path d="M8 36h32"/>
            <circle cx="14" cy="25" r="6"/>
            <path d="M20 25h8"/>
            <path d="M28 21v8"/>
            <path d="M30 36v-4h8v4"/>
            @break

        @case('farmers-carry')
            {{-- น้ำหนักสองข้างห้อยจากหูจับโค้ง คั่นกลางด้วยรอยก้าวเดิน --}}
            <path d="M9 21a4 4 0 0 1 8 0"/>
            <path d="M31 21a4 4 0 0 1 8 0"/>
            <path d="M8 21h10v13H8zM30 21h10v13H30z"/>
            <path d="M21 30h1M24.5 33h1M21 36h1"/>
            @break

        @case('sandbag-lunge')
            {{-- ถุงทรายมีหูรัดวางบนบ่า ลูกศรชี้ลงคือการย่อตัว
                 รอยเท้าสองรอยแยกหน้า-หลังบอกว่าเป็นท่าก้าวย่อ ไม่ใช่สควอต
                 เคยวาดเป็นขาสองข้างแล้วอ่านเป็นโต๊ะ จึงเปลี่ยนมาใช้ภาษาเดียวกับไอคอนตัวอื่น --}}
            <path d="M14 11h20a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H14a3 3 0 0 1-3-3v-6a3 3 0 0 1 3-3z"/>
            <path d="M17 11V8.5M31 11V8.5"/>
            <path d="M24 25v6"/>
            <path d="M20.5 27.5 24 31l3.5-3.5"/>
            <path d="M13 36h6M29 36h6"/>
            <path d="M9 40h30"/>
            @break

        @case('wall-ball')
            {{-- กำแพงพร้อมเป้า ลูกบอล และเส้นโค้งของการขว้าง --}}
            <path d="M39 8v32"/>
            <circle cx="33" cy="14" r="4"/>
            <circle cx="14" cy="32" r="6"/>
            <path d="M18 27c5-7 8-9 11-10" stroke-dasharray="3 3"/>
            <path d="M8 40h24"/>
            @break

        @case('kettlebell')
            {{-- หูจับต้องแคบกว่าตัว ไม่งั้นจะเห็นเป็นก้อนกลมก้อนเดียวตอนย่อเล็ก --}}
            <path d="M19.5 21v-2.5a4.5 4.5 0 0 1 9 0V21"/>
            <path d="M16 21h16c3.2 3.9 5 8.9 5 13.8 0 1.2-1 2.2-2.2 2.2H13.2c-1.2 0-2.2-1-2.2-2.2 0-4.9 1.8-9.9 5-13.8z"/>
            @break

        @case('dumbbell')
            <path d="M17.5 24h13"/>
            <path d="M14.5 18v12M34 18v12" stroke-width="3.4"/>
            <path d="M10.5 20.5v7M37.5 20.5v7" stroke-width="3.4"/>
            @break

        @case('rower')
            <path d="M9 36h30"/>
            <circle cx="14" cy="26" r="5.5"/>
            <path d="M19.5 26h8"/>
            <path d="M27.5 22.5v7"/>
            <path d="M31 36v-4.5h6V36"/>
            @break

        @case('ski')
            <path d="M15 37h18"/>
            <path d="M24 37V12"/>
            <path d="M17 12h14"/>
            <path d="M19.5 12.5v10M28.5 12.5v10"/>
            <path d="M17 22.5h5M26 22.5h5"/>
            @break

        @case('sled')
            <path d="M10 36h24c1.7 0 3-1.3 3-3"/>
            <path d="M15 36V23M29 36V23"/>
            <path d="M15 27h14"/>
            <rect x="19" y="13" width="6" height="10" rx="1.5"/>
            @break

        @case('wallball')
            <path d="M11 9v30"/>
            <path d="M11 16h9"/>
            <circle cx="29" cy="29" r="8.5"/>
            <path d="M20 19c3-3.5 6.5-4.5 9.5-3" stroke-dasharray="2.5 3"/>
            @break

        @case('sandbag')
            <rect x="12" y="20" width="24" height="16" rx="4.5"/>
            <path d="M18.5 20v-2.5a5.5 5.5 0 0 1 11 0V20"/>
            <path d="M12.5 27.5h23"/>
            @break

        @case('box')
            <path d="M14 36V20l10-4.5L34 20v16z"/>
            <path d="M14 20l10 4.5L34 20"/>
            <path d="M24 24.5V36"/>
            @break

        @case('stopwatch')
            <circle cx="24" cy="27" r="11"/>
            <path d="M24 21.5V27l3.5 2.5"/>
            <path d="M20 11h8"/>
            <path d="M24 11v3.5"/>
            @break

        @default
            <circle cx="24" cy="24" r="12"/>
    @endswitch
</svg>
