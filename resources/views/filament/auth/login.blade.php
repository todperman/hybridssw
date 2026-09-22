{{-- หน้าเข้าสู่ระบบหลังบ้าน โครงสองฝั่งชุดเดียวกับ layouts/auth ของหน้าบ้าน
     ฝั่งซ้ายเป็นแผงแบรนด์พื้นเข้ม ฝั่งขวาเป็นฟอร์มของ Filament ตามเดิม --}}
{{-- ครอบอีกชั้นเพราะ Livewire ต้องมี root เดียว
     และกล่องโมดัลต้องอยู่ "นอก" grid ไม่งั้นมันจะกลายเป็นลูกตัวที่สาม
     แล้วสร้างแถวที่สองขึ้นมาแย่งความสูง ทำให้แผงแบรนด์ไม่เต็มจอ --}}
<div>
<div class="ssw-auth">
    <aside class="ssw-auth__brand">
        {{-- ชั้นตกแต่งชุดเดียวกับแผงแบรนด์หน้าบ้าน
             เขียนเป็นคลาส CSS ของตัวเอง ไม่ใช้ยูทิลิตี้ของ Tailwind
             เพราะหลังบ้านคอมไพล์ CSS แยกจากหน้าบ้าน ยูทิลิตี้ที่ใช้ที่นี่ที่เดียวจะไม่ถูกสร้าง --}}
        <div class="ssw-deco" aria-hidden="true">
            <div class="ssw-deco__grid"></div>
            <div class="ssw-deco__orb ssw-deco__orb--1"></div>
            <div class="ssw-deco__orb ssw-deco__orb--2"></div>

            <x-station-icon name="ski" class="ssw-deco__icon ssw-deco__icon--1" stroke="1" />
            <x-station-icon name="sled" class="ssw-deco__icon ssw-deco__icon--2" stroke="1" />
            <x-station-icon name="wallball" class="ssw-deco__icon ssw-deco__icon--3" stroke="1" />

            <span class="ssw-deco__wordmark">Hybrid<br>Workout</span>
        </div>

        <div class="ssw-auth__brandinner">
            <a href="{{ url('/') }}" class="ssw-auth__logo">
                <img src="{{ asset('img/logo@180.png') }}" alt="Srisawan Hybrid Workout" width="180" height="183">
                <span>Hybrid<br>Workout</span>
            </a>

            <div>
                <p class="ssw-auth__eyebrow">ระบบหลังบ้าน</p>
                <h1 class="ssw-auth__title">
                    คุมทุกรอบ<br>
                    <span>จากที่เดียว</span>
                </h1>
                <p class="ssw-auth__lead">
                    ตั้งเวลาเปิดปิดรอบ อนุมัติเทรนเนอร์ ดูอัตราการใช้ที่นั่ง
                    และแก้การจองแทนหน้าเคาน์เตอร์ได้จากหน้าเดียว
                </p>

                <ul class="ssw-auth__points">
                    <li>ตารางรอบต่อสาขา เปิดปิดตามช่วงเวลาที่กำหนด</li>
                    <li>อนุมัติเทรนเนอร์และคุมเพดานลูกทีมต่อกลุ่ม</li>
                    <li>ฮีตแมปการจอง บอกว่าควรเปิดรอบเพิ่มตรงไหน</li>
                </ul>
            </div>

        </div>

        {{-- คลื่นคั่นแบบเดียวกับหน้าบ้าน โผล่เฉพาะตอนวางซ้อนกันบนจอแคบ --}}
        <svg class="ssw-auth__wave" viewBox="0 0 1440 48" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0,30 C180,4 360,4 540,26 C720,48 900,48 1080,28 C1200,15 1320,12 1440,22 L1440,48 L0,48 Z" fill="currentColor"/>
        </svg>
    </aside>

    <main class="ssw-auth__panel">
        <div class="ssw-auth__form">
            <header class="ssw-auth__formhead">
                <h2>{{ $this->getHeading() }}</h2>
                <p>{{ $this->getSubheading() }}</p>
            </header>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE) }}

            {{ $this->content }}

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER) }}

            {{-- เทรนเนอร์ที่หลงมาหน้านี้จะล็อกอินไม่ผ่าน เพราะไม่มีสิทธิ์เข้าหลังบ้าน
                 แต่ข้อความที่ได้คือ "ข้อมูลไม่ตรงกับบันทึก" ซึ่ง Filament จงใจใช้ข้อความเดียวกัน
                 ทั้งกรณีรหัสผิดและกรณีไม่มีสิทธิ์ เพื่อไม่ให้เดาได้ว่าอีเมลไหนมีอยู่จริง
                 จึงต้องบอกทางไว้ตรงนี้ ไม่งั้นคนจะนึกว่ารหัสผ่านตัวเองพัง --}}
            <p class="ssw-auth__hint">
                หน้านี้สำหรับแอดมินและเจ้าหน้าที่เท่านั้น
                เทรนเนอร์และลูกทีมเข้าใช้งานที่
                <a href="{{ route('login') }}">หน้าเข้าสู่ระบบของเว็บ</a>
            </p>

            <p class="ssw-auth__back">
                <a href="{{ url('/') }}">&larr; กลับหน้าแรก</a>
            </p>
        </div>
    </main>

</div>

<x-filament-actions::modals />
</div>
