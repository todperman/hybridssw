<?php

return [
    /*
     * รหัสที่แอดมินแจกให้เทรนเนอร์ในสังกัด ใช้ยืนยันตอนสมัครว่าเป็นคนภายในจริง
     * ถ้าไม่ตั้งค่าไว้ จะไม่มีใครสมัครเป็นเทรนเนอร์ภายในได้เอง
     */
    'internal_trainer_code' => env('GYM_INTERNAL_TRAINER_CODE', ''),

    'registration' => [
        /*
         * แสดงตัวเลือกสาขาตอนสมัครหรือไม่
         * ปิดไว้เมื่อมีสาขาเดียว ระบบจะผูกให้กับสาขาที่เปิดใช้งานอันแรกโดยอัตโนมัติ
         */
        /*
        | เปิดให้สมัครเป็นเทรนเนอร์ภายในเองหรือไม่
        | ปิดไว้ก่อน หน้าสมัครจะเหลือเฉพาะเทรนเนอร์ภายนอก
        | ส่วนเทรนเนอร์ภายในให้แอดมินสร้างให้จากหลังบ้านแทน
        */
        'allow_internal' => env('GYM_REGISTRATION_ALLOW_INTERNAL', false),

        'show_branch_selector' => env('GYM_REGISTRATION_SHOW_BRANCH', true),

        /*
         * บังคับยืนยันตัวตนตอนสมัครหรือไม่
         * เปิด = เทรนเนอร์ภายนอกต้องแนบใบรับรอง และภายในต้องกรอกรหัสพนักงาน
         *
         * ปิดแล้วขั้นตอนนี้จะหายไปทั้งขั้น และ "เทรนเนอร์ภายในจะไม่ถูกอนุมัติอัตโนมัติอีกต่อไป"
         * เพราะไม่มีอะไรยืนยันว่าเป็นพนักงานจริง ทุกคนจะเข้าคิวรอแอดมินตรวจแทน
         */
        'require_identity_verification' => env('GYM_REGISTRATION_VERIFY_IDENTITY', true),
    ],

    /*
     * ค่าเริ่มต้นของเทรนเนอร์แต่ละประเภท ใช้เมื่อแอดมินไม่ได้ตั้งค่าเฉพาะราย
     * max_team_size ใส่ null แปลว่าไม่จำกัดจำนวนลูกทีม
     */
    /*
    |--------------------------------------------------------------------------
    | การแจ้งเตือนก่อนถึงรอบ
    |--------------------------------------------------------------------------
    |
    | lead_minutes คือเริ่มเตือนก่อนรอบเริ่มกี่นาที
    | grace_minutes คือยังเตือนต่ออีกกี่นาทีหลังรอบเริ่มแล้ว เผื่อคนมาสาย
    |
    */
    'reminder' => [
        'lead_minutes' => env('GYM_REMINDER_LEAD_MINUTES', 90),
        'grace_minutes' => env('GYM_REMINDER_GRACE_MINUTES', 15),
    ],

    'trainer_defaults' => [
        'external' => [
            'seats_per_session' => env('GYM_EXTERNAL_SEATS', 5),
            'advance_booking_days' => env('GYM_EXTERNAL_ADVANCE_DAYS', 7),
            'max_team_size' => env('GYM_EXTERNAL_MAX_TEAM', 20),
        ],

        'internal' => [
            'seats_per_session' => env('GYM_INTERNAL_SEATS', 5),
            'advance_booking_days' => env('GYM_INTERNAL_ADVANCE_DAYS', 30),
            'max_team_size' => env('GYM_INTERNAL_MAX_TEAM', null),
        ],
    ],
];
