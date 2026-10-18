<?php

namespace App\Notifications;

/**
 * แจ้งเตือนเรื่องการจองทุกประเภท (รอชำระ ยืนยัน เลื่อน ยกเลิก คืนเงิน ฯลฯ)
 * เนื้อหาและช่องทางอยู่ที่ GymNotice ชื่อคลาสนี้ถูกเก็บในตาราง notifications จึงคงไว้
 */
class ReservationNotice extends GymNotice {}
