<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * รายการแพ็กเกจที่เปิดขาย
 *
 * เป็นแม่แบบเฉย ๆ ยังไม่ผูกกับใคร แอดมินเอาไปออกให้ลูกทีมอีกทีจากหน้าลูกทีม
 * รันซ้ำได้ ของเดิมที่ชื่อตรงกันจะถูกอัปเดตค่า ไม่สร้างใบใหม่
 *
 * แก้ราคาหรือเพิ่มแพ็กเกจได้ที่อาเรย์ข้างล่าง แล้วรันซ้ำ
 * หรือจะไปแก้ในหน้าเว็บก็ได้ ผลเหมือนกัน
 *
 *   php artisan db:seed --class=PackageCatalogSeeder --force
 */
class PackageCatalogSeeder extends Seeder
{
    /**
     * credits คือจำนวนครั้งที่จองได้ ตัดครั้งละ 1 ทุกครั้งที่จอง
     * ระบบไม่มีแพ็กเกจแบบไม่จำกัด 365 จึงเท่ากับเฉลี่ยวันละครั้งตลอดปี
     */
    protected array $packages = [
        [
            'name' => 'รายปี',
            'description' => 'จองได้ 365 ครั้ง ภายในหนึ่งปีนับจากวันที่เปิดใช้',
            'credits' => 365,
            'price' => 12000,
            'validity_days' => 365,
        ],
    ];

    public function run(): void
    {
        $branch = Branch::query()->first();

        if (! $branch) {
            $this->command?->error('ยังไม่มีสาขาในระบบ รัน ProductionSeeder ก่อน');

            return;
        }

        foreach ($this->packages as $package) {
            Package::updateOrCreate(
                ['branch_id' => $branch->id, 'name' => $package['name']],
                $package + ['is_active' => true],
            );

            $this->command?->info(sprintf(
                '%s — %d ครั้ง %s บาท อายุ %d วัน',
                $package['name'],
                $package['credits'],
                number_format($package['price']),
                $package['validity_days'],
            ));
        }
    }
}
