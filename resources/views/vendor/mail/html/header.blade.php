@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- โลโก้แบรนด์โหลดจากเว็บจริง ไคลเอนต์อีเมลส่วนใหญ่แสดงรูปจาก https ได้ ถ้าถูกบล็อกจะเห็นชื่อระบบจาก alt แทน --}}
<img src="{{ asset('img/brand.png') }}" class="logo" alt="{{ config('app.name') }}" width="180" style="width: 180px; height: auto;">
</a>
</td>
</tr>
