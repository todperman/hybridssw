<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * แจ้งเตือนเรื่องการจองทุกประเภทใช้คลาสเดียว ต่างกันที่หัวเรื่องกับเนื้อความ
 * เก็บลงฐานข้อมูลให้เห็นในเว็บ และส่งอีเมลด้วยเมื่อตั้งค่าเมลไว้
 */
class ReservationNotice extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public ?string $reference = null,
    ) {}

    public function via(object $notifiable): array
    {
        // บัญชีตัวอย่างใช้โดเมนที่ไม่มีอยู่จริง ส่งเมลไปก็เด้งกลับ เก็บไว้ในเว็บอย่างเดียว
        $mail = config('gym.notifications.mail', true) && ! \App\Services\DemoData::isDemoEmail($notifiable->email ?? null);

        return $mail ? ['database', 'mail'] : ['database'];
    }

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
            ->subject($this->title.($this->reference ? ' · '.$this->reference : ''))
            ->greeting($this->title)
            ->line($this->body);

        return $this->url ? $mail->action('ดูรายละเอียด', $this->url) : $mail;
    }
}
