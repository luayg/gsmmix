param(
    [string]$PhpExe = $(if ($env:GSMIX_PHP_EXE) { $env:GSMIX_PHP_EXE } else { 'C:\xampp\php\php.exe' })
)

$ErrorActionPreference = 'Stop'
$ProjectDir = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$LogDir = Join-Path $ProjectDir 'storage\logs'
$LogFile = Join-Path $LogDir 'scheduler-task.log'

if (-not (Test-Path -LiteralPath $PhpExe)) {
    throw "PHP executable not found: $PhpExe"
}

if (-not (Test-Path -LiteralPath (Join-Path $ProjectDir 'artisan'))) {
    throw "Laravel artisan file not found in: $ProjectDir"
}

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
Set-Location -LiteralPath $ProjectDir

$stamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
Add-Content -LiteralPath $LogFile -Value "[$stamp] schedule:run start"

& $PhpExe artisan schedule:run *>> $LogFile
$exit = $LASTEXITCODE

$stamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
Add-Content -LiteralPath $LogFile -Value "[$stamp] schedule:run exit=$exit"

exit $exit
