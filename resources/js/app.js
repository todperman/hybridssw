import './bootstrap';

/**
 * ปฏิทินเลือกวันของแอป
 *
 * เขียนเองแทน <input type="date"> เพราะป๊อปอัปของ input เป็นของเบราว์เซอร์
 * แต่งด้วย CSS ไม่ได้เลย และหน้าตาต่างกันไปทุกระบบปฏิบัติการ
 *
 * ผูกค่ากับ property ของ Livewire ผ่าน $wire โดยตรง จึงใช้กับหน้าไหนก็ได้
 * แค่ส่งชื่อ property เข้ามา
 */
/**
 * วัดความสูงจริงของแถบเมนูแล้วส่งเข้า --nav-h ให้แถบที่ตรึงด้านล่างใช้อ้างอิง
 *
 * ย้ายมาไว้ที่นี่แทนการเขียนยาว ๆ ใน x-init เพราะ Alpine จะแปลง x-init
 * เป็น "นิพจน์" เมื่อไม่ได้ขึ้นต้นด้วย let/const ตรง ๆ โค้ดที่ขึ้นต้นด้วย
 * คอมเมนต์แล้วค่อยประกาศ const จึงพังด้วย SyntaxError เงียบ ๆ
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('appShell', () => ({
        init() {
            const nav = this.$el.querySelector('[data-app-nav]');

            if (! nav) return;

            const sync = () => document.documentElement.style
                .setProperty('--nav-h', nav.offsetHeight + 'px');

            sync();
            new ResizeObserver(sync).observe(nav);
        },
    }));
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('datePicker', (model, opts = {}) => ({
        open: false,
        cursor: null,

        // ชื่อย่อแบบไทย เรียงจันทร์ก่อนให้ตรงกับแถบเลือกวันด้านบน
        weekdays: ['จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส', 'อา'],
        months: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
                 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'],

        // ตัวย่อไทยไม่ใช่การตัดตัวอักษรหน้า จึงต้องมีชุดของตัวเอง
        monthsShort: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
                      'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],

        init() {
            this.cursor = this.startOfMonth(this.toDate(this.value) ?? new Date());

            // ถ้าค่าถูกเปลี่ยนจากที่อื่น เช่นกดปุ่ม "วันนี้" ปฏิทินต้องเลื่อนตาม
            this.$watch('value', (v) => {
                const d = this.toDate(v);
                if (d) this.cursor = this.startOfMonth(d);
            });
        },

        get value() {
            return this.$wire.get(model) ?? '';
        },

        /* ---------- ตัวช่วยเรื่องวันที่ ----------
           ใช้เวลาท้องถิ่นทั้งหมด ถ้าเผลอใช้ toISOString() จะเพี้ยนไปหนึ่งวัน
           เพราะมันแปลงเป็น UTC ก่อน */
        toDate(s) {
            if (!s) return null;
            const [y, m, d] = String(s).split('-').map(Number);
            return (y && m && d) ? new Date(y, m - 1, d) : null;
        },

        toKey(d) {
            const p = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
        },

        startOfMonth(d) {
            return new Date(d.getFullYear(), d.getMonth(), 1);
        },

        get title() {
            return `${this.months[this.cursor.getMonth()]} ${this.cursor.getFullYear()}`;
        },

        get label() {
            const d = this.toDate(this.value);
            if (!d) return opts.placeholder ?? 'เลือกวันที่';

            return `${this.weekdayFull(d)} ${d.getDate()} ${this.monthsShort[d.getMonth()]} ${d.getFullYear()}`;
        },

        weekdayFull(d) {
            return ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'][d.getDay()];
        },

        /** ช่องทั้งหมดของเดือนที่แสดง เติมช่องว่างหน้าแรกให้ตรงคอลัมน์วัน */
        get days() {
            const first = this.cursor;
            const offset = (first.getDay() + 6) % 7; // จันทร์ = 0
            const total = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
            const cells = [];

            for (let i = 0; i < offset; i++) cells.push(null);

            for (let n = 1; n <= total; n++) {
                const d = new Date(first.getFullYear(), first.getMonth(), n);
                const key = this.toKey(d);

                cells.push({
                    key,
                    n,
                    isToday: key === this.toKey(new Date()),
                    isSelected: key === this.value,
                    isPast: key < this.toKey(new Date()),
                    // ต้องแปลงเป็น boolean จริง ๆ ถ้าปล่อยเป็น undefined
                    // Alpine จะตั้ง attribute disabled ให้ แล้วกดวันไม่ได้ทั้งเดือน
                    isDisabled: Boolean((opts.min && key < opts.min) || (opts.max && key > opts.max)),
                });
            }

            return cells;
        },

        shiftMonth(step) {
            this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + step, 1);
        },

        pick(cell) {
            if (!cell || cell.isDisabled) return;

            this.$wire.set(model, cell.key);
            this.open = false;
        },

        goToday() {
            const key = this.toKey(new Date());
            this.cursor = this.startOfMonth(new Date());
            this.$wire.set(model, key);
            this.open = false;
        },

        toggle() {
            this.open = !this.open;
            if (this.open) {
                const d = this.toDate(this.value);
                this.cursor = this.startOfMonth(d ?? new Date());
            }
        },
    }));
});
