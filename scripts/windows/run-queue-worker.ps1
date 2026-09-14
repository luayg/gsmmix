param(
    [string]$PhpExe = $(if ($env:GSMIX_PHP_EXE) { $env:GSMIX_PHP_EXE } else { 'C:\xampp\php\php.exe' })
)

$ErrorActionPreference = 'Stop'
$ProjectDir = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$LogDir = Join-Path $ProjectDir 'storage\logs'
$LogFile = Join-Path $LogDir 'queue-worker-task.log'

if (-not (Test-Path -LiteralPath $PhpExe)) {
    throw "PHP executable not found: $PhpExe"
}

if (-not (Test-Path -LiteralPath (Join-Path $ProjectDir 'artisan'))) {
    throw "Laravel artisan file not found in: $ProjectDir"
}

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
Set-Location -LiteralPath $ProjectDir

while ($true) {
    $stamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Add-Content -LiteralPath $LogFile -Value "[$stamp] queue:work start"

    & $PhpExe artisan queue:work database --queue=default --sleep=2 --tries=1 --timeout=300 --max-time=3600 *>> $LogFile
    $exit = $LASTEXITCODE

    $stamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Add-Content -LiteralPath $LogFile -Value "[$stamp] queue:work exit=$exit; restarting in 5 seconds"
    Start-Sleep -Seconds 5
}
