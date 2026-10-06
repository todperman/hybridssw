<#
.SYNOPSIS
    Deploy Srisawan Hybrid Workout บน Windows Server + Plesk

.DESCRIPTION
    ดึงโค้ดล่าสุด ติดตั้งเฉพาะสิ่งที่เปลี่ยน migrate ฐานข้อมูล รวบ cache
    แล้วตรวจความพร้อมของระบบจองด้วย php artisan gym:doctor

    ระหว่างอัปเดตเว็บจะขึ้นหน้าปิดปรับปรุงชั่วคราว และเปิดกลับเสมอแม้ขั้นไหนพัง
    ทุกครั้งที่รันจะเก็บ log ไว้ที่ deploy\logs

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1

.EXAMPLE
    # ครั้งแรก: ตั้ง Scheduled Task ให้ระบบสร้างรอบและปิดคำขอค้างเองทุกนาทีด้วย
    powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 -InstallScheduler
#>
param(
    [string]$AppPath = (Split-Path $PSScriptRoot -Parent),
    [string]$Php = '',
    [string]$Composer = '',
    [switch]$InstallScheduler,
    [switch]$SkipBuild,
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
$started = Get-Date

# ---------- เตรียม log ----------
$logDir = Join-Path $AppPath 'deploy\logs'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null
$logFile = Join-Path $logDir ('deploy-' + $started.ToString('yyyyMMdd-HHmmss') + '.log')
Start-Transcript -Path $logFile -Append | Out-Null

function Write-Step([string]$text) {
    Write-Host ''
    Write-Host ('==> ' + $text) -ForegroundColor Cyan
}

function Write-Ok([string]$text) { Write-Host ('    ' + $text) -ForegroundColor Green }
function Write-Note([string]$text) { Write-Host ('    ' + $text) -ForegroundColor DarkGray }

# โปรแกรมภายนอกไม่โยน exception เวลาพัง ต้องเช็ค exit code เองทุกครั้ง
function Invoke-Native([string]$label, [scriptblock]$command) {
    & $command
    if ($LASTEXITCODE -ne 0) {
        throw ($label + ' ล้มเหลว (exit code ' + $LASTEXITCODE + ')')
    }
}

function Invoke-Artisan([string[]]$arguments) {
    Invoke-Native ('artisan ' + ($arguments -join ' ')) { & $script:Php 'artisan' @arguments }
}

$wentDown = $false
$failed = $false

try {
    Set-Location $AppPath

    # ---------- ตรวจเครื่องมือ ----------
    Write-Step 'ตรวจเครื่องมือ'

    if (-not $Php) {
        $candidates = @(
            'C:\Program Files (x86)\Plesk\Additional\PleskPHP84\php.exe',
            'C:\Program Files\Plesk\Additional\PleskPHP84\php.exe'
        )
        $Php = $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1
        if (-not $Php) { $Php = 'php' }
    }
    $script:Php = $Php

    $phpVersion = (& $Php -r 'echo PHP_VERSION;')
    if ([version]$phpVersion -lt [version]'8.4.1') {
        throw ('ต้องใช้ PHP 8.4.1 ขึ้นไป แต่ ' + $Php + ' เป็น ' + $phpVersion + ' ส่ง -Php ที่ถูกต้องเข้ามา')
    }
    Write-Ok ('PHP ' + $phpVersion + '  (' + $Php + ')')

    if (-not $Composer) {
        $Composer = @(
            'C:\ProgramData\ComposerSetup\bin\composer.phar',
            (Join-Path $AppPath 'composer.phar')
        ) | Where-Object { Test-Path $_ } | Select-Object -First 1
    }

    foreach ($tool in @('git', 'npm')) {
        if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
            throw ('ไม่พบ ' + $tool + ' บนเครื่อง')
        }
    }

    if (-not (Test-Path (Join-Path $AppPath '.env'))) {
        throw 'ไม่พบไฟล์ .env สร้างจาก .env.example ก่อน (ดู README หัวข้อขึ้นเซิร์ฟเวอร์จริง)'
    }

    # ---------- ดูว่ามีอะไรใหม่ ----------
    Write-Step 'ตรวจโค้ดใหม่จาก GitHub'

    Invoke-Native 'git fetch' { git fetch --quiet origin }
    $before = (git rev-parse HEAD).Trim()
    $target = (git rev-parse '@{u}').Trim()

    $dirty = git status --porcelain --untracked-files=no
    if ($dirty) {
        Write-Host '    มีไฟล์ที่ถูกแก้บนเซิร์ฟเวอร์โดยตรง git pull จะไม่ยอมทับ:' -ForegroundColor Yellow
        $dirty | ForEach-Object { Write-Host ('      ' + $_) -ForegroundColor Yellow }
        throw 'แก้บน GitHub แทน แล้วล้างการแก้ไขบนเครื่องนี้ด้วย git checkout -- <ไฟล์>'
    }

    if ($before -eq $target -and -not $Force) {
        Write-Ok ('โค้ดล่าสุดอยู่แล้ว (' + $before.Substring(0, 7) + ') ข้ามการอัปเดต ใช้ -Force ถ้าต้องการรันทุกขั้นใหม่')
    }
    else {
        $changed = @(git diff --name-only $before $target)
        Write-Ok ($before.Substring(0, 7) + ' -> ' + $target.Substring(0, 7) + '  เปลี่ยน ' + $changed.Count + ' ไฟล์')
        git log --oneline ($before + '..' + $target) | ForEach-Object { Write-Note $_ }

        $needComposer = $Force -or -not (Test-Path 'vendor\autoload.php') -or ($changed -contains 'composer.lock')
        $needNpmCi = $Force -or -not (Test-Path 'node_modules') -or ($changed -contains 'package-lock.json')
        $needBuild = -not $SkipBuild -and ($Force -or $needNpmCi -or -not (Test-Path 'public\build\manifest.json') -or
            ($changed | Where-Object { $_ -like 'resources/*' -or $_ -in @('vite.config.js', 'tailwind.config.js', 'postcss.config.js', 'package.json') }))

        # ---------- ปิดปรับปรุงชั่วคราว ----------
        Write-Step 'ปิดเว็บชั่วคราวระหว่างอัปเดต'
        Invoke-Artisan @('down', '--retry=60', '--refresh=15')
        $wentDown = $true

        Write-Step 'ดึงโค้ด'
        Invoke-Native 'git pull' { git pull --ff-only --quiet }
        Write-Ok ('ตอนนี้อยู่ที่ ' + (git log -1 --format='%h %s'))

        if ($needComposer) {
            Write-Step 'ติดตั้งแพ็กเกจ PHP'
            if (-not $Composer) { throw 'ไม่พบ composer.phar ส่ง -Composer เข้ามา' }
            Invoke-Native 'composer install' { & $Php $Composer install --no-dev --optimize-autoloader --no-interaction --no-progress }
        }
        else {
            Write-Note 'composer.lock ไม่เปลี่ยน ข้ามการติดตั้งแพ็กเกจ PHP'
        }

        if ($needBuild) {
            Write-Step 'build ไฟล์หน้าเว็บ'
            if ($needNpmCi) { Invoke-Native 'npm ci' { npm ci --no-audit --no-fund } }
            Invoke-Native 'npm run build' { npm run build }
        }
        else {
            Write-Note 'ไฟล์หน้าเว็บไม่เปลี่ยน ข้ามการ build'
        }

        Write-Step 'อัปเดตฐานข้อมูล'
        Invoke-Artisan @('migrate', '--force')

        Write-Step 'รวบ cache ใหม่'
        Invoke-Artisan @('filament:assets')
        Invoke-Artisan @('optimize:clear')
        Invoke-Artisan @('optimize')
    }
}
catch {
    Write-Host ''
    Write-Host ('ล้มเหลว: ' + $_.Exception.Message) -ForegroundColor Red
    $failed = $true
}
finally {
    # เปิดเว็บกลับเสมอ ไม่งั้นพังกลางทางแล้วเว็บค้างหน้าปิดปรับปรุงไปเรื่อย ๆ
    if ($wentDown) {
        Write-Step 'เปิดเว็บกลับ'
        & $script:Php 'artisan' 'up'
    }
}

# ---------- Scheduled Task ----------
if (-not $failed -and $InstallScheduler) {
    Write-Step 'ตั้ง Scheduled Task (schedule:run ทุก 1 นาที)'

    $taskName = 'hybridssw-scheduler'
    $action = New-ScheduledTaskAction -Execute ('"' + $Php + '"') -Argument 'artisan schedule:run' -WorkingDirectory $AppPath
    # ระบุช่วงเวลาทำซ้ำชัด ๆ ไม่พึ่งค่าเริ่มต้นของ Windows ที่ต่างกันไปตามเวอร์ชัน
    $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
    $settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable `
        -ExecutionTimeLimit (New-TimeSpan -Minutes 10) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries

    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings `
        -User 'SYSTEM' -RunLevel Highest -Force | Out-Null

    Start-ScheduledTask -TaskName $taskName
    Start-Sleep -Seconds 5
    $info = Get-ScheduledTaskInfo -TaskName $taskName
    Write-Ok ('ตั้งแล้ว รันล่าสุด ' + $info.LastRunTime + ' ผล ' + $info.LastTaskResult + ' (0 = สำเร็จ)')
}

# ---------- ตรวจความพร้อม ----------
if (-not $failed) {
    Write-Step 'ตรวจความพร้อมของระบบจอง'
    & $Php 'artisan' 'gym:doctor'
}

$elapsed = [int]((Get-Date) - $started).TotalSeconds
Write-Host ''
if ($failed) {
    Write-Host ('Deploy ไม่สำเร็จ ใช้เวลา ' + $elapsed + ' วินาที  log: ' + $logFile) -ForegroundColor Red
}
else {
    Write-Host ('Deploy เสร็จ ใช้เวลา ' + $elapsed + ' วินาที  log: ' + $logFile) -ForegroundColor Green
}

Stop-Transcript | Out-Null
if ($failed) { exit 1 }
