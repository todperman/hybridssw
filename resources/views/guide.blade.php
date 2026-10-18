<x-app-layout>
    @php($maxHours = max(1, (int) ($branch?->max_booking_hours ?? 1)))
    @php($window = (int) ($branch?->booking_window_days ?? 30))
    @php($maxPeople = max(1, (int) ($branch?->max_trainees ?? 5)))
    @php($chapters = array_filter([
        'overview' => ['ภาพรวม', 'sparkles', true],
        'start' => ['เริ่มต้น', 'log-in', true],
        'book' => [$showMember ? 'จองยิม' : 'จองให้ลูกเทรน', 'calendar-plus', true],
        'pay' => ['ชำระเงิน', 'wallet', true],
        'free' => ['สิทธิ์ใช้ฟรี', 'stethoscope', $showMember],
        'trainer' => ['เทรนเนอร์', 'dumbbell', $showTrainer],
        'faq' => ['คำถามพบบ่อย', 'circle-help', true],
    ], fn ($c) => $c[2]))

    <div class="pb-16 print:pb-0" x-data="{ chapter: (location.hash || '#overview').slice(1) }" x-init="$watch('chapter', c => history.replaceState(null, '', '#' + c))" @hashchange.window="chapter = location.hash.slice(1) || 'overview'">
        <x-page-hero eyebrow="คู่มือการใช้งาน" title="ใช้งานยังไง ดูตรงนี้" :subtitle="$branch?->name" pattern="wallball" pattern-alt="ski" class="print:hidden">
            <x-slot:action>
                <div class="flex flex-wrap gap-2">
                    @if ($branch)
                        <x-price-sheet :branch="$branch" />
                    @endif
                    <button type="button" onclick="window.print()"
                            class="inline-flex min-h-[44px] items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 text-sm font-medium text-paper backdrop-blur-sm transition hover:bg-white/20">
                        @svg('lucide-printer', 'h-4 w-4', ['stroke-width' => '2'])
                        พิมพ์ / บันทึก PDF
                    </button>
                </div>
            </x-slot:action>
            <x-slot:stats>
                <div class="mt-5 flex flex-wrap gap-2 text-[12.5px]">
                    <span class="chip border border-white/20 bg-white/10 text-white">จองได้ครั้งละ 1–{{ $maxHours }} ชม.</span>
                    <span class="chip border border-white/20 bg-white/10 text-white">ล่วงหน้าไม่เกิน {{ $window }} วัน</span>
                    <span class="chip border border-white/20 bg-white/10 text-white">สูงสุด {{ $maxPeople }} คนต่อรอบ</span>
                    <span class="chip border border-white/20 bg-white/10 text-white">รอชำระ {{ $holdMinutes }} นาที</span>
                </div>
            </x-slot:stats>
        </x-page-hero>

        {{-- แถบบท เลื่อนแนวนอนบนมือถือ ติดใต้เมนู --}}
        <div class="sticky-under-nav z-30 border-b border-line bg-paper/90 backdrop-blur-md print:hidden">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <nav class="route-scroll -mx-1 flex gap-1.5 overflow-x-auto py-2.5" aria-label="บทในคู่มือ">
                    @foreach ($chapters as $id => [$label, $icon])
                        <button type="button" @click="chapter = '{{ $id }}'; window.scrollTo({ top: 0, behavior: 'smooth' })"
                                :class="chapter === '{{ $id }}' ? 'bg-brand-dark text-white shadow-soft' : 'text-muted hover:bg-mist hover:text-ink'"
                                class="inline-flex min-h-[38px] shrink-0 items-center gap-1.5 rounded-full px-3.5 text-[13.5px] font-medium transition">
                            <x-dynamic-component :component="'lucide-'.$icon" class="h-4 w-4" stroke-width="2" />
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>
            </div>
        </div>

        <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">

            {{-- ================= ภาพรวม (อินโฟกราฟิก) ================= --}}
            <section x-show="chapter === 'overview'" x-cloak class="space-y-4 print:!block">
                @include('guide.overview')
            </section>

            {{-- ================= เริ่มต้น ================= --}}
            <section x-show="chapter === 'start'" x-cloak class="space-y-4 print:!block">
                <div class="card overflow-hidden">
                    <div class="card-head">
                        <h2 class="font-display text-[17px] font-bold text-grad">บัญชีและการเข้าใช้งาน</h2>
                        <p class="mt-0.5 text-xs text-muted">สมัคร เข้าสู่ระบบ โปรไฟล์ และการแจ้งเตือน</p>
                    </div>
                    <div class="p-5">
                        <ol class="divide-y divide-line">
                            <x-guide.step n="1" title="สมัครใช้งาน" icon="user-round-plus">
                                <p>มี 3 ทางตามว่าคุณเป็นใคร</p>
                                <ul class="list-disc space-y-1 ps-5">
                                    <li><strong class="text-ink">สมาชิกทั่วไป</strong> สมัครเองที่หน้า "สมัครสมาชิก" กรอกชื่อ เบอร์ อีเมล ผู้ติดต่อฉุกเฉิน แล้วตั้งรหัสผ่าน{{ $approvalNeeded ? ' จากนั้นรอเจ้าหน้าที่อนุมัติก่อนจึงจองได้ (ระบบแจ้งทางกระดิ่งและอีเมลเมื่ออนุมัติ)' : ' จองได้ทันที' }}</li>
                                    <li><strong class="text-ink">ลูกทีมของเทรนเนอร์</strong> เปิดลิงก์หรือสแกน QR ที่เทรนเนอร์ส่งให้ กรอกข้อมูลแล้วเข้าทีมอัตโนมัติ ใช้งานได้ทันทีไม่ต้องรออนุมัติ</li>
                                    <li><strong class="text-ink">เทรนเนอร์</strong> สมัครที่ "สมัครเป็นเทรนเนอร์" แนบใบรับรอง (ภายนอก) หรือกรอกรหัสพนักงาน (ภายใน) แล้วรอเจ้าหน้าที่ตรวจ</li>
                                </ul>
                            </x-guide.step>
                            <x-guide.step n="2" title="เข้าสู่ระบบ" icon="key-round">
                                <p>ใช้อีเมลและรหัสผ่านที่ตั้งไว้ตอนสมัคร ติ๊ก "จดจำฉัน" ถ้าเป็นเครื่องส่วนตัว</p>
                                <p>ลืมรหัสผ่าน: กด "ลืมรหัสผ่าน" ที่หน้าเข้าสู่ระบบ ระบบส่งลิงก์ตั้งรหัสใหม่ไปที่อีเมล ลิงก์ใช้ได้ 60 นาที ถ้าไม่เจอให้ดูในกล่องสแปม</p>
                                <p>หน้าแรกจะพาไปหน้าที่ตรงกับบทบาทของคุณเอง สมาชิกไปที่ "การจองของฉัน" เทรนเนอร์ไปที่ "งาน"</p>
                            </x-guide.step>
                            <x-guide.step n="3" title="โปรไฟล์" icon="user-round">
                                <p>เมนู <strong class="text-ink">บัญชี → โปรไฟล์</strong> (มือถืออยู่มุมขวาล่าง) แก้ชื่อ ชื่อเล่น เบอร์ ใส่รูปโปรไฟล์ (ไม่เกิน 4 MB) และเปลี่ยนรหัสผ่านได้</p>
                                <p>ชื่อเล่นจะใช้เรียกในหน้างานและปฏิทิน ถ้าเว้นไว้จะแสดงชื่อจริงแทน</p>
                            </x-guide.step>
                            <x-guide.step n="4" title="การแจ้งเตือน" icon="bell">
                                <p>ทุกเหตุการณ์สำคัญแจ้ง 2 ทาง: <strong class="text-ink">กระดิ่ง</strong> มุมขวาบน และ <strong class="text-ink">อีเมล</strong> ที่ใช้สมัคร เช่น รอชำระ ชำระสำเร็จ ยืนยันการจอง เลื่อน ยกเลิก คืนเงิน ผลอนุมัติ</p>
                                <p>ก่อนถึงเวลาที่จอง {{ $reminderLead }} นาที จะมีแถบเตือนขึ้นทุกหน้าในเว็บ</p>
                            </x-guide.step>
                        </ol>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="card p-5">
                        <h3 class="flex items-center gap-2 font-display text-[15px] font-bold text-ink">@svg('lucide-smartphone', 'h-4 w-4 text-brand-deep') ใช้บนมือถือ</h3>
                        <p class="mt-2 text-[14px] leading-relaxed text-muted">เมนูหลักอยู่แถบล่าง กดถึงด้วยนิ้วโป้ง ปุ่ม "บัญชี" เปิดโปรไฟล์ คู่มือ และออกจากระบบ เพิ่มเว็บไว้หน้าจอหลักได้จากเมนูแชร์ของเบราว์เซอร์เพื่อเปิดเหมือนแอป</p>
                    </div>
                    <div class="card p-5">
                        <h3 class="flex items-center gap-2 font-display text-[15px] font-bold text-ink">@svg('lucide-shield-check', 'h-4 w-4 text-brand-deep') ความเป็นส่วนตัว</h3>
                        <p class="mt-2 text-[14px] leading-relaxed text-muted">ปฏิทินแสดงแค่ "จองแล้ว" ไม่บอกว่าใครจอง เห็นชื่อเฉพาะคนในรอบเดียวกับคุณ ข้อมูลบัตรไม่ผ่านเซิร์ฟเวอร์ของเรา ระบบเก็บเพียงยี่ห้อและเลข 4 ตัวท้าย</p>
                    </div>
                </div>
            </section>

            {{-- ================= จองยิม ================= --}}
            <section x-show="chapter === 'book'" x-cloak class="space-y-4 print:!block">
                <div class="card overflow-hidden">
                    <div class="card-head">
                        <h2 class="font-display text-[17px] font-bold text-grad">{{ $showMember ? 'จองยิม' : 'จองยิมให้ลูกเทรน' }}</h2>
                        <p class="mt-0.5 text-xs text-muted">หนึ่งการจองได้ใช้ทั้งยิมเป็นส่วนตัว ไม่มีคนอื่นมาใช้ร่วมในชั่วโมงนั้น</p>
                    </div>
                    <div class="p-5">
                        <ol class="divide-y divide-line">
                            <x-guide.step n="1" title="ดูวันเวลาที่ว่างจากปฏิทิน" icon="calendar-days">
                                <p>เมนู <strong class="text-ink">ปฏิทิน</strong> เห็นทั้งเดือนว่าวันไหนว่างกี่ชั่วโมง แถบเขียว = ว่างมาก เหลือง = เหลือน้อย เทา = เต็ม แตะวันเพื่อดูรายชั่วโมง พร้อมจำนวน Trainer ที่ว่างในชั่วโมงนั้น</p>
                                <p>กด <strong class="text-ink">จอง</strong> ที่ช่องว่าง เลือกจำนวนชั่วโมง ระบบพาไปหน้าจองโดยเลือกวันเวลาให้แล้ว หรือเริ่มจากเมนู "{{ $showMember ? 'จองยิม' : 'จองให้ลูกเทรน' }}" ก็ได้</p>
                            </x-guide.step>
                            <x-guide.step n="2" title="วันและระยะเวลา → เวลาเริ่ม" icon="clock">
                                <p>เลือกวัน (ล่วงหน้าได้ไม่เกิน {{ $window }} วัน) และจำนวนชั่วโมงต่อเนื่อง 1–{{ $maxHours }} ชม. จากนั้นเลือกเวลาเริ่ม ระบบแสดงเฉพาะช่วงที่ยิมว่างจริงและอยู่ในเวลาเปิด</p>
                            </x-guide.step>
                            <x-guide.step n="3" title="ผู้เข้าร่วม" icon="users">
                                <p>เพิ่มคนที่จะเข้าใช้ด้วยกันได้สูงสุด {{ $maxPeople }} คน ค้นจากเบอร์โทรหรือชื่อ ทุกคนต้องเป็นสมาชิกในระบบ ราคาคิดตามจำนวนคนทั้งกลุ่ม (ดูปุ่ม "ดูราคา")</p>
                                @if ($showTrainer)
                                    <p>เทรนเนอร์เลือกจากรายชื่อลูกทีมหรือกลุ่มที่สร้างไว้ จองให้ทั้งกลุ่มได้ในคลิกเดียว</p>
                                @endif
                                <p>คนที่มีสิทธิ์ใช้ฟรี (บุคลากร รพ. / แพทย์) จะมีป้าย <span class="chip bg-emerald-100 text-emerald-800">ฟรี</span> และส่วนของคนนั้นถูกหักออกจากยอดอัตโนมัติ</p>
                            </x-guide.step>
                            <x-guide.step n="4" title="Trainer" icon="dumbbell">
                                <p>เลือก Trainer ที่ว่างในช่วงนั้น (ระบบกรองให้แล้วตามตารางเวลาว่างของแต่ละคน) ราคาแบบมี Trainer รวมค่า Trainer แล้ว</p>
                                <p>"เข้าใช้เอง ไม่มี Trainer" เลือกได้เมื่อ{{ $noTrainerRule === 'all' ? 'ผู้เข้าร่วมทุกคน' : 'มีผู้เข้าร่วมอย่างน้อยหนึ่งคนที่' }}ได้รับสิทธิ์เข้าใช้เองจากเจ้าหน้าที่</p>
                            </x-guide.step>
                            <x-guide.step n="5" title="โค้ดส่วนลดและผู้ชำระเงิน" icon="ticket-percent">
                                <p>มีโค้ดส่วนลดใส่ที่ช่อง "โค้ดส่วนลด" ระบบตรวจเงื่อนไขและแสดงยอดใหม่ทันที ส่วนลดหักหลังจากหักส่วนที่ฟรีแล้ว</p>
                                <p>เลือกว่าใครเป็นผู้ชำระ (ต้องเป็นคนในกลุ่ม) คนนั้นจะได้รับแจ้งเตือนให้ชำระ และเป็นคนที่ได้รับเงินคืนถ้ามีการยกเลิก</p>
                            </x-guide.step>
                            <x-guide.step n="6" title="ยืนยันการจองแล้วชำระภายใน {{ $holdMinutes }} นาที" icon="timer">
                                <p>กดยืนยัน ระบบ <strong class="text-ink">กันเวลาไว้ให้ {{ $holdMinutes }} นาที</strong> (หรือจนถึงเวลาเริ่ม แล้วแต่อะไรถึงก่อน) หน้าการจองจะแสดงนาฬิกานับถอยหลังและช่องทางชำระ</p>
                                <p>ชำระสำเร็จ → สถานะเปลี่ยนเป็น <span class="chip bg-emerald-100 text-emerald-800">ยืนยันการจอง</span> ทันที ไม่ชำระในเวลา → รายการหมดอายุและปล่อยช่วงเวลาให้คนอื่น ต้องจองใหม่</p>
                                <p>ถ้าทุกคนในกลุ่มมีสิทธิ์ฟรีหรือส่วนลดครอบคลุมทั้งยอด ไม่ต้องชำระ ระบบยืนยันให้ทันที</p>
                            </x-guide.step>
                        </ol>
                    </div>
                </div>

                <div class="card overflow-hidden">
                    <div class="card-head">
                        <h2 class="font-display text-[17px] font-bold text-grad">หลังจองแล้ว</h2>
                        <p class="mt-0.5 text-xs text-muted">ดูรายการ เลื่อน ขอยกเลิก</p>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2">
                        <div>
                            <h3 class="flex items-center gap-2 font-display text-[15px] font-bold text-ink">@svg('lucide-ticket', 'h-4 w-4 text-brand-deep') การจองของฉัน / งาน</h3>
                            <p class="mt-1.5 text-[14px] leading-relaxed text-muted">รายการที่กำลังจะถึง รอชำระ และประวัติย้อนหลัง แตะรายการเพื่อดูรายละเอียด เลขอ้างอิง ผู้เข้าร่วม Trainer ราคา และสถานะเงิน</p>
                        </div>
                        <div>
                            <h3 class="flex items-center gap-2 font-display text-[15px] font-bold text-ink">@svg('lucide-repeat', 'h-4 w-4 text-brand-deep') เลื่อนวันเวลา</h3>
                            <p class="mt-1.5 text-[14px] leading-relaxed text-muted"><strong class="text-ink">เลื่อนเองได้</strong>จนถึงเที่ยงคืนก่อนวันใช้งาน (จองวันที่ 10 เลื่อนเองได้ถึง 9 เวลา 23:59) ราคาเท่าเดิม ใช้ Trainer และผู้เข้าร่วมเดิม หลังจากนั้นเป็น <strong class="text-ink">ส่งคำขอพร้อมเหตุผล</strong>ให้เจ้าหน้าที่พิจารณา ผลแจ้งทางกระดิ่งและอีเมล</p>
                        </div>
                        <div>
                            <h3 class="flex items-center gap-2 font-display text-[15px] font-bold text-ink">@svg('lucide-circle-x', 'h-4 w-4 text-brand-deep') ยกเลิกการจอง</h3>
                            <p class="mt-1.5 text-[14px] leading-relaxed text-muted">การยกเลิกทำโดยเจ้าหน้าที่ ติดต่อสาขาพร้อมเลขอ้างอิง ถ้ายิมให้บริการไม่ได้ เจ้าหน้าที่จะยกเลิกและ<strong class="text-ink">คืนเงินเต็มจำนวน</strong>ให้ผู้ชำระอัตโนมัติ</p>
                        </div>
                        <div>
                            <h3 class="flex items-center gap-2 font-display text-[15px] font-bold text-ink">@svg('lucide-alarm-clock', 'h-4 w-4 text-brand-deep') ก่อนถึงเวลา</h3>
                            <p class="mt-1.5 text-[14px] leading-relaxed text-muted">ระบบเตือนล่วงหน้า {{ $reminderLead }} นาที มาถึงก่อนเวลาเล็กน้อย แจ้งเลขอ้างอิงที่เคาน์เตอร์ได้เลย</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ================= ชำระเงิน ================= --}}
            <section x-show="chapter === 'pay'" x-cloak class="space-y-4 print:!block">
                <div class="card overflow-hidden">
                    <div class="card-head">
                        <h2 class="font-display text-[17px] font-bold text-grad">ชำระเงิน</h2>
                        <p class="mt-0.5 text-xs text-muted">{{ $omise ? 'ชำระออนไลน์ ยืนยันอัตโนมัติภายในไม่กี่วินาที' : 'โอนเงินแล้วเจ้าหน้าที่บันทึกรับชำระ' }}</p>
                    </div>
                    <div class="p-5">
                        @if ($omise && $methods->isNotEmpty())
                            <p class="text-[14px] text-muted">เปิดหน้าการจองที่รอชำระ เลือกช่องทางด้านล่าง ยอดที่ต้องจ่ายแสดงชัดเจนพร้อมเวลาที่เหลือ</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                @foreach ($methods as $key => $m)
                                    <div class="flex items-start gap-3 rounded-2xl border border-line bg-white/70 p-3.5">
                                        <div class="flex shrink-0 -space-x-1.5">
                                            @foreach (array_slice($m['logos'], 0, 3) as $logo)
                                                <img src="{{ \App\Services\Payments\Omise\PaymentMethods::logoUrl($logo) }}" alt="" class="h-8 w-8 rounded-full border border-line bg-white object-contain p-1">
                                            @endforeach
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-medium text-ink">{{ $m['label'] }}</p>
                                            <p class="text-[13px] leading-relaxed text-muted">
                                                @switch($key)
                                                    @case('promptpay') กด "พร้อมเพย์" ระบบสร้าง QR ให้ เปิดแอปธนาคารสแกนและโอน ยอดจะถูกยืนยันเองภายในไม่กี่วินาที ไม่ต้องส่งสลิป @break
                                                    @case('card') กรอกเลขบัตร วันหมดอายุ และ CVV ในหน้าที่ปลอดภัยของผู้ให้บริการ อาจต้องยืนยัน OTP จากธนาคาร (3-D Secure) @break
                                                    @case('mobile_banking') เลือกธนาคาร ระบบเปิดแอปธนาคารให้กดยืนยัน (บนคอมพิวเตอร์จะได้ QR) ยืนยันเสร็จกลับมาหน้าเดิมเอง @break
                                                    @case('rabbit_linepay') มือถือเปิดแอป LINE ให้ยืนยัน คอมพิวเตอร์ได้ QR สแกนด้วย LINE ต้องยืนยันภายใน 20 นาที @break
                                                    @case('truemoney') ใส่เบอร์ที่ผูกกับ TrueMoney Wallet แล้วยืนยันด้วย OTP ในแอป @break
                                                    @default {{ $m['hint'] }}
                                                @endswitch
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <ol class="divide-y divide-line">
                                <x-guide.step n="1" title="โอนเงินตามยอดที่แสดง" icon="banknote">
                                    <p>{{ $branch?->payment_instructions ?: 'โอนตามบัญชีที่แจ้งไว้ในหน้าการจอง หรือชำระที่เคาน์เตอร์สาขา' }}</p>
                                </x-guide.step>
                                <x-guide.step n="2" title="แจ้งเจ้าหน้าที่พร้อมเลขอ้างอิง" icon="receipt">
                                    <p>เจ้าหน้าที่บันทึกรับชำระในระบบ สถานะจะเปลี่ยนเป็น "ยืนยันการจอง" และมีแจ้งเตือนถึงทุกคนในรอบ</p>
                                </x-guide.step>
                            </ol>
                        @endif

                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <x-guide.tip tone="info">ราคาคิดเป็น <strong>บาทต่อชั่วโมงทั้งกลุ่ม</strong> ตามจำนวนคนและว่ามี Trainer ไหม กดปุ่ม "ดูราคา" ด้านบนเพื่อดูตารางของสาขานี้</x-guide.tip>
                            <x-guide.tip tone="warn">ต้องชำระภายใน {{ $holdMinutes }} นาทีหลังจอง เลยเวลา รายการจะหมดอายุและคืนช่วงเวลาให้คนอื่น ถ้ายังต้องการให้จองใหม่</x-guide.tip>
                            <x-guide.tip tone="ok">จ่ายแล้วแต่หน้ายังขึ้นรอ: รอ 1–2 นาทีหรือกดรีเฟรช ถ้าเงินถูกตัดแต่การจองหมดอายุไปก่อน เจ้าหน้าที่จะตรวจและคืนเงินให้ทั้งจำนวน</x-guide.tip>
                        </div>
                    </div>
                </div>

                <div class="card overflow-hidden">
                    <div class="card-head">
                        <h2 class="font-display text-[17px] font-bold text-grad">การคืนเงิน</h2>
                    </div>
                    <div class="p-5 text-[14px] leading-relaxed text-muted">
                        <ul class="list-disc space-y-1.5 ps-5">
                            <li>คืนเต็มจำนวนเมื่อเจ้าหน้าที่ยกเลิกการจอง (เช่น ยิมให้บริการไม่ได้) ไปยังผู้ชำระเงินและช่องทางเดิม</li>
                            <li>บัตร Rabbit LINE Pay และ TrueMoney คืนอัตโนมัติ เงินกลับเข้าบัญชีตามรอบของธนาคาร โดยทั่วไป 5–15 วันทำการ</li>
                            <li>พร้อมเพย์และ Mobile Banking เจ้าหน้าที่โอนคืนให้ด้วยตัวเอง จะติดต่อขอเลขบัญชีจากผู้ชำระ</li>
                            <li>ทุกครั้งที่คืนเงินสำเร็จ มีแจ้งเตือนทางกระดิ่งและอีเมล และดูประวัติได้ในหน้าการจองนั้น</li>
                        </ul>
                    </div>
                </div>
            </section>

            {{-- ================= สิทธิ์ใช้ฟรี ================= --}}
            @if ($showMember)
            <section x-show="chapter === 'free'" x-cloak class="space-y-4 print:!block">
                <div class="card overflow-hidden">
                    <div class="card-head">
                        <h2 class="font-display text-[17px] font-bold text-grad">สิทธิ์ใช้ฟรีสำหรับบุคลากรโรงพยาบาลและแพทย์</h2>
                        <p class="mt-0.5 text-xs text-muted">ฟรีเฉพาะส่วนของตัวเอง รวมค่า Trainer ตามโควตาที่กำหนด</p>
                    </div>
                    <div class="p-5">
                        @if ($hasBenefit)
                            <x-guide.tip tone="ok">บัญชีของคุณมีสิทธิ์ <strong>{{ $user->member->benefitLabel() }}</strong> อยู่แล้ว ดูโควตาที่เหลือของเดือนนี้ได้ที่ป้าย "ฟรี" ในหน้าจอง</x-guide.tip>
                        @endif
                        <ol class="divide-y divide-line {{ $hasBenefit ? 'mt-4' : '' }}">
                            <x-guide.step n="1" title="แจ้งตอนสมัคร" icon="hospital">
                                <p>ติ๊ก "เป็นบุคลากร/แพทย์ของโรงพยาบาล" และกรอกรหัสพนักงานหรือเลขใบประกอบวิชาชีพ เจ้าหน้าที่จะตรวจสอบตอนอนุมัติ</p>
                                <p>สมัครไปแล้วไม่ได้แจ้ง? ติดต่อเจ้าหน้าที่ให้กำหนดประเภทสมาชิกให้ภายหลังได้</p>
                            </x-guide.step>
                            <x-guide.step n="2" title="เจ้าหน้าที่กำหนดประเภทสมาชิก" icon="badge-check">
                                <p>หลังอนุมัติ เจ้าหน้าที่ตั้ง "ประเภทสมาชิก" (เช่น บุคลากร รพ. หรือ แพทย์) พร้อมวันหมดอายุของสิทธิ์ ระบบบันทึกประวัติว่าใครตั้ง เมื่อไร เหตุผลอะไร</p>
                            </x-guide.step>
                            <x-guide.step n="3" title="ส่วนของคุณฟรีอัตโนมัติทุกครั้งที่จอง" icon="sparkles">
                                <p>ตอนเลือกผู้เข้าร่วม คนที่มีสิทธิ์จะมีป้าย <span class="chip bg-emerald-100 text-emerald-800">ฟรี</span> ยอดรวมหัก "ส่วนต่อคน" ของคนนั้นออก <strong class="text-ink">รวมค่า Trainer ด้วย</strong> เพื่อนร่วมรอบที่ไม่มีสิทธิ์จ่ายส่วนของตัวเองตามปกติ</p>
                                <p>ถ้าทุกคนในรอบมีสิทธิ์ ยอดเป็น 0 บาท ระบบยืนยันการจองให้ทันทีโดยไม่ต้องชำระ</p>
                            </x-guide.step>
                            <x-guide.step n="4" title="โควตารายเดือนและวันหมดอายุ" icon="hourglass">
                                <p>แต่ละประเภทมีจำนวนชั่วโมงฟรีต่อเดือน ใช้ครบแล้วชั่วโมงที่เหลือของเดือนคิดราคาปกติ โควตานับใหม่ทุกต้นเดือนและนับจากการจองที่ยังมีผล (รายการที่หมดอายุหรือถูกยกเลิกคืนโควตาให้)</p>
                                <p>สิทธิ์หมดอายุตามวันที่เจ้าหน้าที่ตั้งไว้ (เช่น วันสิ้นสุดสัญญา) ต่ออายุได้โดยติดต่อเจ้าหน้าที่</p>
                            </x-guide.step>
                            <x-guide.step n="5" title="เข้าใช้เองโดยไม่มี Trainer" icon="key-round">
                                <p>เป็นสิทธิ์แยกต่างหากที่เจ้าหน้าที่ให้เป็นรายคน เมื่อ{{ $noTrainerRule === 'all' ? 'ทุกคนในรอบ' : 'มีคนในรอบอย่างน้อยหนึ่งคน' }}มีสิทธิ์นี้ จะเลือก "เข้าใช้เอง ไม่มี Trainer" ได้ตั้งแต่ก่อนเลือกเวลา ราคาใช้ตารางแบบไม่มี Trainer</p>
                            </x-guide.step>
                        </ol>
                    </div>
                </div>
            </section>
            @endif

            {{-- ================= เทรนเนอร์ ================= --}}
            @if ($showTrainer)
            <section x-show="chapter === 'trainer'" x-cloak class="space-y-4 print:!block">
                <div class="card overflow-hidden">
                    <div class="card-head">
                        <h2 class="font-display text-[17px] font-bold text-grad">สำหรับเทรนเนอร์</h2>
                        <p class="mt-0.5 text-xs text-muted">ตั้งเวลาว่าง สร้างทีม จองให้ลูกเทรน และจัดการงาน</p>
                    </div>
                    <div class="p-5">
                        <ol class="divide-y divide-line">
                            <x-guide.step n="1" title="สถานะใบสมัคร" icon="badge-check">
                                <p><strong class="text-ink">เทรนเนอร์ภายใน</strong> ที่ยืนยันรหัสพนักงานแล้วเริ่มงานได้ทันที <strong class="text-ink">เทรนเนอร์ภายนอก</strong> รอเจ้าหน้าที่ตรวจใบรับรองก่อน ระหว่างรอดูสถานะและแก้โปรไฟล์ได้ที่ "สถานะใบสมัคร" ผลแจ้งทางกระดิ่งและอีเมล</p>
                                <p>ใบรับรองมีวันหมดอายุ ถ้าหมดอายุจะรับงานใหม่ไม่ได้จนกว่าจะอัปเดตเอกสารกับเจ้าหน้าที่</p>
                            </x-guide.step>
                            <x-guide.step n="2" title="ตั้งเวลาว่าง" icon="clock">
                                <p>เมนู <strong class="text-ink">เวลาว่าง</strong> มี 3 ส่วน: <strong class="text-ink">เวลาว่างประจำสัปดาห์</strong> (เช่น จันทร์–ศุกร์ 17:00–21:00) <strong class="text-ink">ว่างเพิ่มเฉพาะวัน</strong> สำหรับวันที่รับงานนอกเวลาประจำ และ <strong class="text-ink">วันลา / ไม่รับงาน</strong></p>
                                <p>ลูกเทรนและปฏิทินจะเห็นคุณเป็น "Trainer ว่าง" เฉพาะชั่วโมงที่อยู่ในเวลาว่างและยังไม่มีงาน ลาทับวันที่มีคนจองไว้แล้วไม่ได้ ต้องส่งคำขอยกเลิกการรับงานจากหน้างานนั้นแทน</p>
                            </x-guide.step>
                            <x-guide.step n="3" title="ชวนลูกทีมด้วยลิงก์หรือ QR" icon="qr-code">
                                <p>เมนู <strong class="text-ink">ทีม</strong> กด "คัดลอก" ลิงก์คำเชิญหรือให้ลูกทีมสแกน QR ลูกทีมกรอกข้อมูลเองแล้วเข้าทีมอัตโนมัติ ใช้งานได้ทันที ลิงก์ใช้ได้ไม่จำกัดครั้ง</p>
                                <p>เพิ่มลูกทีมที่เป็นสมาชิกอยู่แล้วเองได้จาก "เพิ่มลูกทีมเอง" ค้นด้วยเบอร์โทร เทรนเนอร์ภายนอกมีจำนวนลูกทีมสูงสุดตามที่สาขากำหนด</p>
                            </x-guide.step>
                            <x-guide.step n="4" title="สร้างกลุ่มลูกทีม" icon="users">
                                <p>จัดลูกทีมที่มาเทรนด้วยกันเป็นกลุ่ม ตั้งชื่อและสี ตอนจองเลือกทั้งกลุ่มได้ในคลิกเดียว ลบกลุ่มไม่กระทบลูกทีมและการจองที่ทำไปแล้ว</p>
                            </x-guide.step>
                            <x-guide.step n="5" title="จองยิมให้ลูกเทรน" icon="calendar-plus">
                                <p>เมนู <strong class="text-ink">จองให้ลูกเทรน</strong> หรือกด "จอง" จากช่องว่างในปฏิทิน ขั้นตอนเหมือนสมาชิก แต่คุณเป็น Trainer ประจำการจองเสมอ และต้องเลือกว่า<strong class="text-ink">ลูกเทรนคนไหนเป็นผู้ชำระ</strong> คนนั้นจะได้รับแจ้งเตือนให้ชำระภายใน {{ $holdMinutes }} นาที</p>
                                <p>ยอดคิดตามตารางราคา "มี Trainer" หักส่วนของลูกเทรนที่มีสิทธิ์ฟรีให้อัตโนมัติ</p>
                            </x-guide.step>
                            <x-guide.step n="6" title="งานและการเปลี่ยนแปลง" icon="clipboard-list">
                                <p>เมนู <strong class="text-ink">งาน</strong> รวมทุกการจองที่คุณเป็น Trainer แตะเพื่อดูผู้เข้าร่วมและสถานะเงิน</p>
                                <p>รับงานต่อไม่ได้: กด <strong class="text-ink">ขอยกเลิกการรับงาน</strong> พร้อมเหตุผล เจ้าหน้าที่จะหา Trainer แทนหรือติดต่อลูกเทรน ลูกเทรนไม่เสียการจอง เลื่อนวันเวลาทำได้เหมือนสมาชิก (เลื่อนเองก่อนเที่ยงคืนของวันก่อนใช้งาน)</p>
                            </x-guide.step>
                        </ol>
                    </div>
                </div>
            </section>
            @endif

            {{-- ================= คำถามพบบ่อย ================= --}}
            <section x-show="chapter === 'faq'" x-cloak class="space-y-4 print:!block">
                <div class="card overflow-hidden">
                    <div class="card-head">
                        <h2 class="font-display text-[17px] font-bold text-grad">คำถามพบบ่อย</h2>
                    </div>
                    <div class="px-5 pb-1 pt-1">
                        <x-guide.faq q="จองแล้วแต่ยังไม่ได้จ่าย ช่วงเวลานั้นเป็นของฉันแน่ไหม">
                            ใช่ ระบบกันเวลาไว้ให้ {{ $holdMinutes }} นาทีนับจากกดยืนยัน (หรือจนถึงเวลาเริ่มถ้าใกล้กว่านั้น) ระหว่างนี้คนอื่นจองทับไม่ได้ ถ้าหมดเวลาจะคืนช่วงเวลาให้คนอื่นและต้องจองใหม่
                        </x-guide.faq>
                        <x-guide.faq q="ทำไมปฏิทินไม่แสดงเวลาที่ฉันต้องการ">
                            อาจเป็นเพราะ (1) ยิมปิดในช่วงนั้นหรือเป็นวันหยุดพิเศษ (2) มีคนจองไปแล้ว (3) เลยระยะจองล่วงหน้า {{ $window }} วัน หรือ (4) เวลาเริ่มผ่านไปแล้ว ลองเลือกวันอื่นหรือดูแถบสีในปฏิทินเดือน
                        </x-guide.faq>
                        <x-guide.faq q="เลือก Trainer ไม่ได้ / ไม่มี Trainer ให้เลือก">
                            Trainer จะโผล่เฉพาะคนที่ตั้งเวลาว่างครอบคลุมช่วงที่เลือกและยังไม่มีงาน ลองเปลี่ยนเวลา หรือดูจำนวน "Trainer ว่าง" ในปฏิทินรายวันก่อนเลือก ถ้าเป็นลูกทีม ให้เทรนเนอร์ของคุณจองให้ก็ได้
                        </x-guide.faq>
                        <x-guide.faq q="เงินถูกตัดแล้วแต่สถานะยังรอชำระ">
                            ปกติยืนยันภายในไม่กี่วินาที หน้าการจองจะอัปเดตเอง ถ้าเกิน 2 นาทีให้กดรีเฟรช ถ้ายังไม่เปลี่ยน ติดต่อเจ้าหน้าที่พร้อมเลขอ้างอิงและเวลาที่ชำระ ระบบมีการตรวจสอบย้อนหลังกับผู้ให้บริการทุก 2 นาที เงินไม่หายแน่นอน
                        </x-guide.faq>
                        <x-guide.faq q="อยากเปลี่ยนวันเวลา ทำยังไง">
                            เปิดหน้าการจอง กด "เลือกวันเวลาใหม่" ถ้ายังไม่เลยเที่ยงคืนของวันก่อนใช้งานจะเลื่อนได้ทันที ราคาเท่าเดิม ถ้าเลยแล้วจะเป็นการส่งคำขอให้เจ้าหน้าที่พิจารณา ผลแจ้งทางกระดิ่งและอีเมล
                        </x-guide.faq>
                        <x-guide.faq q="เพื่อนที่มาด้วยไม่ได้เป็นสมาชิก เพิ่มได้ไหม">
                            ผู้เข้าร่วมทุกคนต้องมีบัญชีในระบบเพื่อความปลอดภัยและการนับจำนวนคน ให้เพื่อนสมัครที่หน้า "สมัครสมาชิก" ก่อน (หรือผ่านลิงก์ของเทรนเนอร์ถ้าอยู่ทีมเดียวกัน) แล้วค่อยเพิ่มด้วยเบอร์โทร
                        </x-guide.faq>
                        <x-guide.faq q="โค้ดส่วนลดใช้ไม่ได้">
                            โค้ดแต่ละตัวมีเงื่อนไข เช่น ใช้ได้เฉพาะสาขา เฉพาะแบบมี/ไม่มี Trainer ยอดขั้นต่ำ จำนวนครั้ง หรือหมดอายุ ระบบจะบอกเหตุผลใต้ช่องกรอก ส่วนลดหักจากยอดที่เหลือหลังหักส่วนที่ฟรีแล้ว และยอดสุดท้ายต่ำกว่า 20 บาทไม่ได้
                        </x-guide.faq>
                        <x-guide.faq q="ฉันเป็นบุคลากร รพ. แต่ไม่เห็นป้าย ฟรี">
                            สิทธิ์ต้องถูกกำหนดโดยเจ้าหน้าที่หลังตรวจสอบ ถ้าสมัครและแจ้งไว้แล้วแต่ยังไม่ได้ ติดต่อเจ้าหน้าที่พร้อมรหัสพนักงาน หรือโควตาเดือนนี้อาจใช้ครบแล้ว / สิทธิ์หมดอายุ ดูรายละเอียดในบท "สิทธิ์ใช้ฟรี"
                        </x-guide.faq>
                        <x-guide.faq q="ไม่ได้รับอีเมลแจ้งเตือน">
                            ดูในกล่องสแปมหรือโฆษณา และตรวจว่าอีเมลในโปรไฟล์สะกดถูก การแจ้งเตือนทุกฉบับยังดูได้ที่กระดิ่งมุมขวาบนเสมอ
                        </x-guide.faq>
                        <x-guide.faq q="ลืมรหัสผ่าน / เปลี่ยนรหัสผ่าน">
                            ลืม: หน้าเข้าสู่ระบบ → "ลืมรหัสผ่าน" ใส่อีเมล รับลิงก์ตั้งรหัสใหม่ (ใช้ได้ 60 นาที) เปลี่ยน: บัญชี → โปรไฟล์ → รหัสผ่าน ใส่รหัสเดิมและรหัสใหม่
                        </x-guide.faq>
                    </div>
                </div>

                @if ($branch)
                    <div class="card p-5">
                        <h3 class="flex items-center gap-2 font-display text-[15px] font-bold text-ink">@svg('lucide-life-buoy', 'h-4 w-4 text-brand-deep') ติดต่อเจ้าหน้าที่</h3>
                        <div class="mt-3 grid gap-3 text-[14px] text-muted sm:grid-cols-3">
                            <div class="flex items-start gap-2">@svg('lucide-building-2', 'mt-0.5 h-4 w-4 shrink-0 text-brand-deep') <span>{{ $branch->name }}</span></div>
                            @if ($branch->phone)
                                <a href="tel:{{ preg_replace('/\D+/', '', $branch->phone) }}" class="flex items-start gap-2 text-brand-deep">@svg('lucide-phone', 'mt-0.5 h-4 w-4 shrink-0') <span>{{ $branch->phone }}</span></a>
                            @endif
                            @if ($branch->address)
                                <div class="flex items-start gap-2">@svg('lucide-map-pin', 'mt-0.5 h-4 w-4 shrink-0 text-brand-deep') <span>{{ $branch->address }}</span></div>
                            @endif
                        </div>
                        <p class="mt-3 text-[13px] text-muted/80">แจ้งเลขอ้างอิงการจอง (ขึ้นต้นด้วย HS เช่น HS261018AB3KD) ทุกครั้งที่ติดต่อ จะช่วยให้เจ้าหน้าที่ตรวจสอบได้เร็วขึ้น</p>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
