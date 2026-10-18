<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\DemoData;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * แม่แบบแจ้งเตือนทุกเรื่องของระบบ (การจอง บัญชี การชำระ)
 *
 * เก็บลงฐานข้อมูลให้เห็นที่กระดิ่งในเว็บ และส่งอีเมลด้วยเมื่อเปิดใช้
 * ฉบับเดียวกันส่งเป็น "สำเนาถึงผู้ดูแล" ได้ด้วย asAdminCopy() ซึ่งจะบอกในเมลว่าต้นฉบับส่งถึงใคร
 */
abstract class GymNotice extends Notification
{
    /** true = ฉบับนี้เป็นสำเนาที่ส่งเข้ากล่องผู้ดูแล ไม่ใช่ถึงผู้เกี่ยวข้องโดยตรง */
    public bool $adminCopy = false;

    /** @var array<int, string> ชื่อผู้รับต้นฉบับ (ใช้เฉพาะสำเนาผู้ดูแล) */
    public array $recipients = [];

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public ?string $reference = null,
    ) {}

    public function via(object $notifiable): array
    {
        // สำเนาถึงอีเมลผู้ดูแลไม่มีบัญชีผู้ใช้รองรับ จึงมีแต่ช่องทางเมล
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        // บัญชีตัวอย่างใช้โดเมนที่ไม่มีอยู่จริง ส่งเมลไปก็เด้งกลับ เก็บไว้ในเว็บอย่างเดียว
        $mail = config('gym.notifications.mail', true) && ! DemoData::isDemoEmail($notifiable->email ?? null);

        return $mail ? ['database', 'mail'] : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'reference' => $this->reference,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(($this->adminCopy ? '[สำเนาผู้ดูแล] ' : '').$this->title.($this->reference ? ' · '.$this->reference : ''))
            ->greeting($this->title);

        if ($this->adminCopy) {
            $mail->line('สำเนาการแจ้งเตือนที่ระบบส่งถึง: '.(implode(', ', $this->recipients) ?: 'ไม่มีผู้รับโดยตรง'));
        } elseif ($notifiable instanceof User) {
            $mail->line('สวัสดีคุณ'.$notifiable->displayName());
        }

        $mail->line($this->body);

        if ($this->reference) {
            $mail->line('เลขที่อ้างอิง: '.$this->reference);
        }

        if ($this->url) {
            $mail->action('ดูรายละเอียด', $this->url);
        }

        return $mail
            ->line('อีเมลนี้ส่งจากระบบอัตโนมัติ ถ้ามีข้อสงสัยติดต่อเจ้าหน้าที่ได้ที่อีเมลผู้ส่ง')
            ->salutation('ทีมงาน '.config('app.name'));
    }

    /** @param  array<int, string>  $recipients */
    public function asAdminCopy(array $recipients): static
    {
        $copy = clone $this;
        $copy->adminCopy = true;
        $copy->recipients = array_values($recipients);

        return $copy;
    }
}
