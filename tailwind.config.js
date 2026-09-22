import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// พาเลตต์เซจ + ทองบนครีม
// สีหลักที่กำหนดไว้: #218dae #2bbbd7 #f3f6f8 #ffd758 ส่วนเฉดที่เหลือคำนวณต่อใน OKLCH
// โดยคุมสีสัน (hue) ให้อยู่ตระกูลเดียวกันและไล่ความสว่างอย่างสม่ำเสมอ
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans Thai"', ...defaultTheme.fontFamily.sans],
                display: ['"Plus Jakarta Sans"', '"IBM Plex Sans Thai"', ...defaultTheme.fontFamily.sans],
            },

            colors: {
                ink: '#1c2f3a',
                muted: '#5b676c',
                paper: '#f3f6f8',
                mist: '#e8eef1',
                line: '#d6dcde',
                // เทากลางสำหรับแถบที่ยังไม่ถูกใช้ เช่นช่องที่นั่งว่างและโครงร่างระหว่างโหลด
                track: '#e4e7e8',
                surface: 'rgba(255, 255, 255, 0.78)',

                // ฟ้า-เขียวน้ำทะเล — สีหลักของแบรนด์
                brand: {
                    light: '#2bbbd7',
                    DEFAULT: '#218dae',
                    deep: '#007595',
                    dark: '#00516f',
                    deepest: '#00334a',
                },

                // ส้มดินเผา — ใช้หมายถึง "ของคุณ" เท่านั้น
                // เลือกคนละเฉดสีกับแบรนด์เพื่อให้แยกจากที่นั่งของผู้เล่นอื่นได้ทันที
                // ก่อนหน้านี้ใช้น้ำเงินเข้มคู่กับฟ้าอ่อน ซึ่งเป็นสีตระกูลเดียวกันจึงแยกยาก
                mine: {
                    soft: '#ffe4cd',
                    DEFAULT: '#cf6a26',
                    deep: '#a9521b',
                    ink: '#82400f',
                },

                // เหลืองทอง — สีรอง ใช้เท่าที่จำเป็นกับจุดที่ต้องสะดุดตา
                accent: {
                    light: '#fce59a',
                    DEFAULT: '#ffd758',
                    deep: '#d2a40f',
                    ink: '#7a5800',
                },
            },

            borderRadius: {
                md2: '20px',
                lg2: '28px',
                xl2: '36px',
            },

            boxShadow: {
                soft: '0 1px 2px rgba(28, 47, 58, 0.04), 0 12px 32px -12px rgba(28, 47, 58, 0.12)',
                lift: '0 2px 4px rgba(28, 47, 58, 0.04), 0 24px 48px -18px rgba(28, 47, 58, 0.2)',
            },

            transitionTimingFunction: {
                'out-soft': 'cubic-bezier(0.16, 1, 0.3, 1)',
                spring: 'cubic-bezier(0.34, 1.35, 0.64, 1)',
            },
        },
    },

    plugins: [forms],
};
