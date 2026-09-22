@php
    $grid = $this->grid();
    $insights = $this->insights();
    $days = \App\Filament\Pages\BookingHeatmap::DAYS;
    $totals = $insights['totals'];

    // ระดับความหนาแน่น 0-6 พร้อมสีพื้นและสีตัวอักษรที่อ่านออกบนพื้นนั้น
    // ตัวเลขถูกใส่ในช่องเสมอ ไม่สื่อความหมายด้วยสีอย่างเดียว
    $scale = [
        0 => ['#e4e7e8', '#7b8a90'],
        1 => ['#dbf4fb', '#0f5a70'],
        2 => ['#b3e9f7', '#0f5a70'],
        3 => ['#84daee', '#0c4558'],
        4 => ['#2abbd8', '#04303f'],
        5 => ['#218dae', '#ffffff'],
        6 => ['#005170', '#ffffff'],
    ];

    $levelOf = fn (float $r) => match (true) {
        $r >= 0.90 => 6,
        $r >= 0.75 => 5,
        $r >= 0.60 => 4,
        $r >= 0.45 => 3,
        $r >= 0.25 => 2,
        $r > 0     => 1,
        default    => 0,
    };

    $slotName = fn (array $c) => $days[$c['dow']].' '.str_pad($c['hr'], 2, '0', STR_PAD_LEFT).':00';
@endphp

<x-filament-panels::page>
    {{--
        สไตล์เขียนไว้ในหน้าเอง เพราะ Filament ใช้ CSS ที่คอมไพล์มาแล้ว
        และไม่ได้สแกนคลาส Tailwind จากวิวของโปรเจกต์
        ถ้าเขียนด้วยคลาส Tailwind ตรงนี้จะไม่มีสไตล์ออกมาเลย
    --}}
    <style>
        .hm { --hm-line: #d6dcde; --hm-muted: #5b676c; --hm-ink: #1c2f3a; }
        .hm-controls { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 1rem; }
        .hm-field label { display: block; font-size: .8125rem; font-weight: 500; color: var(--hm-muted); margin-bottom: .25rem; }
        .hm-field select {
            border: 1px solid var(--hm-line); border-radius: .625rem; padding: .5rem .75rem;
            font-size: .875rem; background: #fff; color: var(--hm-ink); min-width: 12rem;
        }
        .hm-totals { margin-inline-start: auto; display: flex; flex-wrap: wrap; gap: .5rem; }
        .hm-pill { border-radius: .5rem; padding: .375rem .75rem; font-size: .8125rem; font-weight: 600; }

        .hm-card { background: #fff; border: 1px solid var(--hm-line); border-radius: .875rem; padding: 1.25rem; }
        .hm-scroll { overflow-x: auto; }
        .hm-table { width: 100%; min-width: 620px; border-collapse: separate; border-spacing: 4px; }
        .hm-table th { font-size: .75rem; font-weight: 600; color: var(--hm-muted); }
        .hm-table thead th { padding-bottom: .25rem; text-align: center; }
        .hm-table tbody th { text-align: end; padding-inline-end: .5rem; white-space: nowrap; font-variant-numeric: tabular-nums; }

        .hm-cell {
            position: relative; height: 40px; border-radius: .5rem;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 700; font-variant-numeric: tabular-nums;
        }
        .hm-empty {
            height: 40px; border-radius: .5rem; border: 1px dashed var(--hm-line);
            display: flex; align-items: center; justify-content: center;
            color: #b8c2c6; font-size: .6875rem;
        }
        /* จุดมุมบนบอกว่าชั่วโมงนี้มีคนล้นไปคิวสำรอง */
        .hm-dot {
            position: absolute; top: 4px; inset-inline-end: 4px;
            height: 7px; width: 7px; border-radius: 9999px;
            background: #ffd758; box-shadow: 0 0 0 1.5px rgba(255,255,255,.75);
        }

        .hm-legend { margin-top: 1.25rem; display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1.25rem; font-size: .75rem; color: var(--hm-muted); }
        .hm-legend-row { display: flex; align-items: center; gap: .375rem; }
        .hm-swatch { height: 16px; width: 26px; border-radius: .25rem; }

        .hm-insights { display: grid; gap: 1rem; grid-template-columns: 1fr; }
        @media (min-width: 1024px) { .hm-insights { grid-template-columns: repeat(3, 1fr); } }
        .hm-insights h3 { font-size: .875rem; font-weight: 700; color: var(--hm-ink); }
        .hm-insights p.hint { margin-top: .125rem; font-size: .75rem; color: var(--hm-muted); }
        .hm-list { margin-top: .75rem; display: flex; flex-direction: column; gap: .5rem; }
        .hm-item {
            display: flex; align-items: center; justify-content: space-between; gap: .75rem;
            background: #f3f6f8; border-radius: .5rem; padding: .5rem .75rem; font-size: .875rem;
        }
        .hm-item b { font-variant-numeric: tabular-nums; }
        .hm-none { margin-top: 1rem; font-size: .875rem; color: #9aa7ac; }
    </style>

    <div class="hm" style="display: flex; flex-direction: column; gap: 1.5rem;">

        <div class="hm-controls">
            <div class="hm-field">
                <label for="branchId">สาขา</label>
                <select id="branchId" wire:model.live="branchId">
                    @foreach ($this->branches() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="hm-field">
                <label for="weeks">ช่วงเวลา</label>
                <select id="weeks" wire:model.live="weeks">
                    @foreach (\App\Filament\Pages\BookingHeatmap::WEEK_OPTIONS as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($totals)
                <div class="hm-totals">
                    <span class="hm-pill" style="background:#dbf4fb; color:#0f5a70;">
                        ใช้ที่นั่งรวม {{ round($totals['rate'] * 100) }}%
                    </span>
                    <span class="hm-pill" style="background:#eef1f2; color:#4a585e;">
                        {{ number_format($totals['occupied']) }} / {{ number_format($totals['capacity']) }} ที่นั่ง
                    </span>
                    <span class="hm-pill" style="background:#fce59a; color:#7a5600;">
                        ไม่มาตามนัด {{ round($totals['noShowRate'] * 100) }}%
                    </span>
                </div>
            @endif
        </div>

        <div class="hm-card">
            @if (empty($grid['hours']))
                <p style="padding: 3rem 0; text-align: center; font-size: .875rem; color: #9aa7ac;">
                    ยังไม่มีข้อมูลในช่วงเวลานี้
                </p>
            @else
                <div class="hm-scroll">
                    <table class="hm-table">
                        <thead>
                            <tr>
                                <th style="width: 3.5rem;"></th>
                                @foreach ($days as $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grid['hours'] as $hr)
                                <tr>
                                    <th>{{ str_pad($hr, 2, '0', STR_PAD_LEFT) }}:00</th>

                                    @foreach (array_keys($days) as $dow)
                                        @php($cell = $grid['cells'][$hr][$dow] ?? null)

                                        <td style="padding: 0;">
                                            @if (! $cell)
                                                <div class="hm-empty" title="ไม่เปิดรอบ">—</div>
                                            @else
                                                @php([$bg, $fg] = $scale[$levelOf($cell['rate'])])

                                                <div class="hm-cell" style="background: {{ $bg }}; color: {{ $fg }};"
                                                     title="{{ $days[$dow] }} {{ str_pad($hr, 2, '0', STR_PAD_LEFT) }}:00&#10;ใช้ไป {{ $cell['occupied'] }} จาก {{ $cell['capacity'] }} ที่นั่ง ({{ round($cell['rate'] * 100) }}%)&#10;เปิดมาแล้ว {{ $cell['sessions'] }} รอบ&#10;ไม่มาตามนัด {{ $cell['noShows'] }} คน&#10;ล้นไปคิวสำรอง {{ $cell['waitlisted'] }} คน">
                                                    {{ round($cell['rate'] * 100) }}%
                                                    @if ($cell['waitlisted'] > 0)
                                                        <span class="hm-dot"></span>
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

                <div class="hm-legend">
                    <span class="hm-legend-row">
                        <span>ว่าง</span>
                        @foreach ($scale as $lv => [$bg, $fg])
                            <span class="hm-swatch" style="background: {{ $bg }};"></span>
                        @endforeach
                        <span>เต็ม</span>
                    </span>

                    <span class="hm-legend-row">
                        <span class="hm-swatch" style="width:14px; height:14px; border-radius:9999px; background:#ffd758;"></span>
                        มีคนล้นไปคิวสำรอง
                    </span>

                    <span class="hm-legend-row">
                        <span class="hm-swatch" style="background: transparent; border: 1px dashed var(--hm-line);"></span>
                        ไม่เปิดรอบ
                    </span>
                </div>
            @endif
        </div>

        @if ($totals)
            <div class="hm-insights">
                @foreach ([
                    ['title' => 'ควรเปิดรอบเพิ่ม', 'hint' => 'มีคนรอคิวสำรองจนจบรอบแล้วไม่ได้ที่นั่ง คือความต้องการที่รับไม่ทัน', 'items' => $insights['unmet'], 'mode' => 'count', 'unit' => 'คน', 'empty' => 'ไม่มีคิวสำรองตกค้าง รับไหวทุกชั่วโมง', 'color' => '#966c00'],
                    ['title' => 'ชั่วโมงที่แน่นที่สุด', 'hint' => 'ใช้จัดกำลังเทรนเนอร์และอุปกรณ์ให้พอ', 'items' => $insights['busiest'], 'mode' => 'rate', 'unit' => null, 'empty' => 'ยังไม่มีข้อมูลพอ', 'color' => '#00516f'],
                    ['title' => 'ว่างเรื้อรัง', 'hint' => 'เปิดมาแล้วอย่างน้อย 3 รอบ แต่ใช้ที่นั่งไม่ถึงหนึ่งในสาม', 'items' => $insights['quietest'], 'mode' => 'rate', 'unit' => null, 'empty' => 'ไม่มีชั่วโมงที่ว่างผิดปกติ', 'color' => '#5b676c'],
                ] as $card)
                    <div class="hm-card">
                        <h3>{{ $card['title'] }}</h3>
                        <p class="hint">{{ $card['hint'] }}</p>

                        @if (empty($card['items']))
                            <p class="hm-none">{{ $card['empty'] }}</p>
                        @else
                            <div class="hm-list">
                                @foreach ($card['items'] as $item)
                                    <div class="hm-item">
                                        <span>{{ $slotName($item) }}</span>
                                        <b style="color: {{ $card['color'] }};">
                                            @if ($card['mode'] === 'count')
                                                {{ $item['waitlisted'] }} {{ $card['unit'] }}
                                            @else
                                                {{ round($item['rate'] * 100) }}%
                                            @endif
                                        </b>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
