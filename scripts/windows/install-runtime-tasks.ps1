param(
    [string]$PhpExe = $(if ($env:GSMIX_PHP_EXE) { $env:GSMIX_PHP_EXE } else { 'C:\xampp\php\php.exe' })
)

$ErrorActionPreference = 'Stop'

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identity)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Run PowerShell as Administrator to install the GSMIX scheduled tasks.'
}

$ProjectDir = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$SchedulerScript = Join-Path $PSScriptRoot 'run-scheduler-once.ps1'
$QueueScript = Join-Path $PSScriptRoot 'run-queue-worker.ps1'

if (-not (Test-Path -LiteralPath $PhpExe)) {
    throw "PHP executable not found: $PhpExe"
}
if (-not (Test-Path -LiteralPath $SchedulerScript)) {
    throw "Scheduler script not found: $SchedulerScript"
}
if (-not (Test-Path -LiteralPath $QueueScript)) {
    throw "Queue worker script not found: $QueueScript"
}

$ps = (Get-Command powershell.exe).Source
$schedulerAction = '"{0}" -NoProfile -ExecutionPolicy Bypass -File "{1}" -PhpExe "{2}"' -f $ps, $SchedulerScript, $PhpExe
$queueAction = '"{0}" -NoProfile -ExecutionPolicy Bypass -File "{1}" -PhpExe "{2}"' -f $ps, $QueueScript, $PhpExe

& schtasks.exe /Create /TN 'GSMIX Scheduler' /TR $schedulerAction /SC MINUTE /MO 1 /RU SYSTEM /RL HIGHEST /F | Out-Host
if ($LASTEXITCODE -ne 0) { throw 'Failed to create GSMIX Scheduler task.' }

& schtasks.exe /Create /TN 'GSMIX Queue Worker' /TR $queueAction /SC ONSTART /RU SYSTEM /RL HIGHEST /F | Out-Host
if ($LASTEXITCODE -ne 0) { throw 'Failed to create GSMIX Queue Worker task.' }

& schtasks.exe /Run /TN 'GSMIX Queue Worker' | Out-Host
if ($LASTEXITCODE -ne 0) { throw 'GSMIX Queue Worker task was created but could not be started.' }

Write-Host ''
Write-Host 'Installed:' -ForegroundColor Green
Write-Host '  GSMIX Scheduler   - runs Laravel schedule:run every minute'
Write-Host '  GSMIX Queue Worker - starts at Windows boot and continuously processes the database queue'
Write-Host ''
Write-Host "Project: $ProjectDir"
Write-Host "PHP:     $PhpExe"
Write-Host ''
Write-Host 'Logs:'
Write-Host '  storage\logs\scheduler-task.log'
Write-Host '  storage\logs\queue-worker-task.log'
