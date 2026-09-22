# ตรวจความพร้อมของเซิร์ฟเวอร์ก่อนติดตั้ง Srisawan Hybrid Workout
# อ่านอย่างเดียว ไม่แก้ไขหรือติดตั้งอะไรทั้งสิ้น
#
#   powershell -ExecutionPolicy Bypass -File check-server.ps1

$ErrorActionPreference = "Continue"
$missing = @()

function Show-Tool {
    param([string]$Label, [string]$Exe, [string]$VersionArg)

    $found = Get-Command $Exe -ErrorAction SilentlyContinue
    if (-not $found) {
        Write-Host ("  {0,-12} MISSING" -f $Label) -ForegroundColor Red
        $script:missing += $Label
        return $null
    }

    $out = & $Exe $VersionArg 2>&1 | Select-Object -First 1
    Write-Host ("  {0,-12} {1}" -f $Label, $out) -ForegroundColor Green
    return $found.Source
}

Write-Host ""
Write-Host "=== เครื่องมือที่ต้องใช้ ===" -ForegroundColor Cyan
$php = Show-Tool "PHP" "php" "-v"
Show-Tool "Composer" "composer" "--version" | Out-Null
Show-Tool "Git"      "git"      "--version" | Out-Null
Show-Tool "Node"     "node"     "--version" | Out-Null
Show-Tool "npm"      "npm"      "--version" | Out-Null

Write-Host ""
Write-Host "=== PHP extension ที่ระบบต้องใช้ ===" -ForegroundColor Cyan
if ($php) {
    $loaded = & php -m 2>&1
    foreach ($ext in @("intl","mbstring","openssl","pdo_mysql","fileinfo","curl","zip","gd")) {
        if ($loaded -contains $ext) {
            Write-Host ("  {0,-12} OK" -f $ext) -ForegroundColor Green
        } else {
            Write-Host ("  {0,-12} MISSING" -f $ext) -ForegroundColor Red
            $missing += "php-$ext"
        }
    }
    Write-Host ""
    Write-Host ("  php.ini: " + (& php --ini 2>&1 | Select-String "Loaded Configuration" | ForEach-Object { $_.ToString().Split(":",2)[1].Trim() }))
} else {
    Write-Host "  ข้ามเพราะไม่มี PHP" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "=== IIS URL Rewrite Module ===" -ForegroundColor Cyan
$rw = Get-ItemProperty "HKLM:\SOFTWARE\Microsoft\IIS Extensions\URL Rewrite" -ErrorAction SilentlyContinue
if ($rw -and $rw.Version) {
    Write-Host ("  ติดตั้งแล้ว เวอร์ชัน " + $rw.Version) -ForegroundColor Green
} else {
    Write-Host "  MISSING - ถ้าไม่มี ทุก URL นอกจากหน้าแรกจะ 500" -ForegroundColor Red
    $missing += "IIS URL Rewrite"
}

Write-Host ""
Write-Host "=== โฟลเดอร์เว็บปัจจุบัน ===" -ForegroundColor Cyan
$vhost = "C:\Inetpub\vhosts\srisawan.com\hybridssw.srisawan.com"
if (Test-Path $vhost) {
    Write-Host ("  " + $vhost)
    Get-ChildItem $vhost | ForEach-Object {
        $kind = if ($_.PSIsContainer) { "[DIR ]" } else { "[FILE]" }
        Write-Host ("    {0} {1}" -f $kind, $_.Name)
    }
} else {
    Write-Host ("  ไม่พบ " + $vhost) -ForegroundColor Yellow
}

Write-Host ""
Write-Host "=== สรุป ===" -ForegroundColor Cyan
if ($missing.Count -eq 0) {
    Write-Host "  พร้อมติดตั้ง ไม่ขาดอะไร" -ForegroundColor Green
} else {
    Write-Host ("  ขาด " + $missing.Count + " อย่าง: " + ($missing -join ", ")) -ForegroundColor Yellow
}
Write-Host ""
