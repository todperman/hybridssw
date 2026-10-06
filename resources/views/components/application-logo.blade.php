@props(['dark' => false, 'size' => 'md'])

{{-- โลโก้ HS / HYBRID SSW มีชื่อแบรนด์อยู่ในตัวภาพแล้ว ไม่ต้องพิมพ์ชื่อซ้ำข้าง ๆ
     สัญลักษณ์เป็นสีดำ บนพื้นเข้มจึงต้องใช้ไฟล์ brand-light ที่เปลี่ยนส่วนดำเป็นขาว
     ทั้งสองไฟล์สร้างจาก h_ssw.png ที่รากโปรเจกต์ --}}
<span {{ $attributes->merge(['class' => 'inline-flex min-h-[44px] items-center']) }}>
    <img src="{{ asset($dark ? 'img/brand-light.png' : 'img/brand.png') }}" alt="Hybrid SSW"
         width="465" height="168" fetchpriority="high"
         @class(['w-auto shrink-0', 'h-10' => $size === 'md', 'h-14 lg:h-16' => $size === 'lg'])>
</span>
