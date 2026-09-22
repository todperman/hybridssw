@php
    $trainer = $this->trainer();
    $grid = $this->grid;
    $insights = $this->insights;
    $totals = $insights['totals'];
    $days = \App\Services\BookingHeatmapService::DAYS;

    // สีของช่องเป็นข้อมูล ไม่ใช่การตกแต่ง จึงกำหนดค่าเป็นสีตรงๆ
    // และใส่ตัวเลขในช่องเสมอ ไม่สื่อความหมายด้วยสีอย่างเดียว
    $scale = [
        0 => ['#e4e7e8', '#7b8a90'],
        1 => ['#dbf4fb', '#0f5a70'],
        2 => ['#b3e9f7', '#0f5a70'],
        3 => ['#84daee', '#0c4558'],
        4 => ['#2abbd8', '#04303f'],
        5 => ['#218dae', '#ffffff'],
        6 => ['#005170', '#ffffff'],
    ];

    $slotName = fn (array $c) => $days[$c['dow']].' '.str_pad($c['hr'], 2, '0', STR_PAD_LEFT).':00';
@endphp

<div class="pb-16">
    <x-page-hero :eyebrow="$trainer->branch->name" title="สถิติการจอง" pattern="ski" pattern-alt="sled"
                 subtitle="ดูว่าชั่วโมงไหนคนแน่น จะได้วางตารางและจองล่วงหน้าได้ถูกจังหวะ">
        <x-slot:stats>
            @if ($totals)
                <dl class="mt-5 grid max-w-md grid-cols-3 gap-3">
                    @foreach ([
                        ['ใช้ที่นั่ง', round($totals['rate'] * 100).'%'],
                        ['ที่นั่งที่ใช้ไป', number_format($totals['occupied'])],
                        ['ไม่มาตามนัด', round($totals['noShowRate'] * 100).'%'],
                    ] as [$label, $value])
                        <div class="rounded-2xl border border-white/10 bg-white/10 px-3 py-2.5 backdrop-blur-sm">
                            <dt class="text-[11px] leading-tight text-white/65">{{ $label }}</dt>
                            <dd class="mt-0.5 font-display text-2xl font-bold leading-none text-paper">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </x-slot:stats>
    </x-page-hero>

    <div class="mx-auto max-w-5xl space-y-5 px-4 pt-6 sm:px-6 lg:px-8">

        {{-- เลือกมุมมองและช่วงเวลา --}}
        <div class="card flex flex-wrap items-center gap-3 p-4">
            <div class="glass-seg">
                @foreach (\App\Livewire\Trainer\Insights::SCOPES as $key => $label)
                    {{-- ต้องมี wire:key ไม่งั้น Livewire จับคู่ปุ่มในลูปผิด
                         แล้วไฮไลต์ของปุ่มที่เลือกจะค้างอยู่ที่เดิมทั้งที่ข้อมูลเปลี่ยนไปแล้ว --}}
                    <button wire:key="scope-{{ $key }}" wire:click="setScope('{{ $key }}')"
                            class="glass-seg-btn px-4
                                {{ $scope === $key ? ($key === 'mine' ? 'glass-seg-btn-mine' : 'glass-seg-btn-on') : '' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <select wire:model.live="weeks" aria-label="ช่วงเวลา"
                    class="min-h-[38px] rounded-full border-line bg-white/70 py-0 text-[13px]">
                @foreach (\App\Livewire\Trainer\Insights::WEEK_OPTIONS as $value => $label)
                    <option value="{{ $value }}">ย้อนหลัง {{ $label }}</option>
                @endforeach
            </select>

            <p class="ml-auto text-[13px] text-muted">
                {{ $scope === 'mine'
                    ? 'นับเฉพาะลูกทีมที่คุณจอง'
                    : 'อัตราการใช้ที่นั่งรวมของสาขา ไม่ระบุว่าที่นั่งเป็นของใคร' }}
            </p>
        </div>

        {{-- ตารางความหนาแน่น --}}
        <div class="card p-5">
            @if (empty($grid['hours']))
                <div class="px-6 py-14 text-center">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-mist">
                        <x-station-icon name="kettlebell" class="h-7 w-7 text-brand-deep" />
                    </span>
                    <p class="mt-4 font-display text-lg font-bold text-ink">ยังไม่มีข้อมูลในช่วงนี้</p>
                    <p class="mt-1 text-sm text-muted">เมื่อมีการจองย้อนหลังแล้วจะเห็นรูปแบบที่นี่</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] border-separate border-spacing-1">
                        <thead>
                            <tr>
                                <th class="w-14"></th>
                                @foreach ($days as $label)
                                    <th class="pb-1 text-center text-[11px] font-semibold text-muted">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grid['hours'] as $hr)
                                <tr>
                                    <th class="pe-2 text-end text-[11px] font-semibold tabular-nums text-muted">
                                        {{ str_pad($hr, 2, '0', STR_PAD_LEFT) }}:00
                                    </th>

                                    @foreach (array_keys($days) as $dow)
                                        @php($cell = $grid['cells'][$hr][$dow] ?? null)

                                        <td class="p-0">
                                            @if (! $cell)
                                                <div class="flex h-9 items-center justify-center rounded-lg border border-dashed border-line text-[11px] text-muted/40"
                                                     title="ไม่เปิดรอบ">—</div>
                                            @else
                                                @php([$bg, $fg] = $scale[$this->levelOf($cell['rate'])])

                                                <div class="relative flex h-9 items-center justify-center rounded-lg text-[11px] font-bold tabular-nums"
                                                     style="background: {{ $bg }}; color: {{ $fg }};"
                                                     title="{{ $days[$dow] }} {{ str_pad($hr, 2, '0', STR_PAD_LEFT) }}:00&#10;ใช้ไป {{ $cell['occupied'] }} จาก {{ $cell['capacity'] }} ที่นั่ง&#10;เปิดมาแล้ว {{ $cell['sessions'] }} รอบ">
                                                    {{ round($cell['rate'] * 100) }}%

                                                    @if ($cell['waitlisted'] > 0)
                                                        <span class="absolute right-1 top-1 h-1.5 w-1.5 rounded-full bg-accent ring-1 ring-white/70"></span>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-[11px] text-muted">
                    <span class="flex items-center gap-1.5">
                        ว่าง
                        @foreach ($scale as [$bg, $fg])
                            <span class="h-3.5 w-6 rounded" style="background: {{ $bg }};"></span>
                        @endforeach
                        เต็ม
                    </span>

                    <span class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-accent"></span>มีคนล้นไปคิวสำรอง
                    </span>

                    <span class="flex items-center gap-1.5">
                        <span class="h-3.5 w-6 rounded border border-dashed border-line"></span>ไม่เปิดรอบ
                    </span>
                </div>
            @endif
        </div>

        {{-- ข้อสรุป --}}
        @if ($totals)
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ([
                    ['title' => 'ชั่วโมงที่แน่นที่สุด', 'hint' => $scope === 'mine' ? 'ทีมคุณเล่นช่วงนี้มากที่สุด' : 'ต้องจองล่วงหน้า ไม่งั้นเต็ม', 'items' => $insights['busiest'], 'mode' => 'rate'],
                    ['title' => 'ชั่วโมงที่ยังว่าง', 'hint' => 'จองง่าย เหมาะกับลูกทีมที่ยืดหยุ่นเวลาได้', 'items' => $insights['quietest'], 'mode' => 'rate'],
                    ['title' => 'ล้นไปคิวสำรอง', 'hint' => 'ชั่วโมงที่คนจองไม่ทัน', 'items' => $insights['unmet'], 'mode' => 'count'],
                ] as $card)
                    <div class="card p-5">
                        <h2 class="font-display text-[15px] font-bold text-ink">{{ $card['title'] }}</h2>
                        <p class="mt-0.5 text-[12px] text-muted">{{ $card['hint'] }}</p>

                        @if (empty($card['items']))
                            <p class="mt-4 text-sm text-muted/70">ยังไม่มีข้อมูลพอ</p>
                        @else
                            <ul class="mt-3 space-y-2">
                                @foreach ($card['items'] as $item)
                                    <li class="flex items-center justify-between gap-3 rounded-xl bg-mist px-3 py-2">
                                        <span class="text-[13px] text-ink">{{ $slotName($item) }}</span>
                                        <span class="text-[13px] font-bold tabular-nums text-brand-dark">
                                            {{ $card['mode'] === 'count' ? $item['waitlisted'].' คน' : round($item['rate'] * 100).'%' }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
