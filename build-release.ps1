# build-release.ps1
# Build release for cleantalk.antispam: UTF-8 и Windows-1251.
# It is placed NEXT to the folder cleantalk.antispam. Run: build-release.bat  or  powershell -File build-release.ps1

[CmdletBinding()]
param(
    [string]$SourceFolder = 'cleantalk.antispam',
    [string]$OutputFolder = 'build',
    [string]$Version = ''   # if empty — take from install/version.php
)

$ErrorActionPreference = 'Stop'
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

$src = Join-Path $scriptDir $SourceFolder
if (-not (Test-Path $src)) {
    Write-Error "Source folder not found: $src"
    exit 1
}

# Auto-detect version from install/version.php
if ([string]::IsNullOrWhiteSpace($Version)) {
    $verFile = Join-Path $src 'install\version.php'
    if (Test-Path $verFile) {
        $verText = Get-Content -Raw -LiteralPath $verFile
        if ($verText -match '"VERSION"\s*=>\s*"([^"]+)"') {
            $Version = $Matches[1]
        }
    }
}
if ([string]::IsNullOrWhiteSpace($Version)) { $Version = Get-Date -Format 'yyyyMMdd' }

$outDir = Join-Path $scriptDir $OutputFolder
if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir | Out-Null }

# Extensions considered "text" and processed during conversion
$textExtensions = @('.php','.html','.htm','.js','.css','.txt','.md','.tpl','.xml','.json','.en','.ru','.sql')
# Files without extensions that Bitrix uses as text
$textFilenames  = @('description.en','description.ru')

function Test-TextFile([string]$path) {
    $ext = [System.IO.Path]::GetExtension($path).ToLowerInvariant()
    if ($textExtensions -contains $ext) { return $true }
    $name = [System.IO.Path]::GetFileName($path)
    if ($textFilenames -contains $name) { return $true }
    return $false
}

function Test-Utf8Bytes([byte[]]$bytes) {
    try {
        $enc = New-Object System.Text.UTF8Encoding($false, $true)
        [void]$enc.GetString($bytes)
        return $true
    } catch { return $false }
}

# If the filename explicitly indicates the encoding — do not touch the content
function Test-FilenameLocksEncoding([string]$path) {
    $name = [System.IO.Path]::GetFileName($path).ToLowerInvariant()
    return ($name -match 'cp1251' -or $name -match 'utf-?8' -or $name -match 'win-?1251')
}

function Convert-Tree {
    param(
        [string]$Source,
        [string]$Target,
        [ValidateSet('utf8','cp1251')][string]$TargetEncoding
    )

    if (Test-Path $Target) { Remove-Item -Recurse -Force $Target }
    $parent = Split-Path -Parent $Target
    if (-not (Test-Path $parent)) { New-Item -ItemType Directory -Path $parent | Out-Null }
    Copy-Item -Recurse -Force -LiteralPath $Source -Destination $Target

    $cp1251 = [System.Text.Encoding]::GetEncoding(1251)
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false, $false)
    $processed = 0
    $converted = 0

    Get-ChildItem -Recurse -File -LiteralPath $Target | ForEach-Object {
        if (-not (Test-TextFile $_.FullName)) { return }
        if (Test-FilenameLocksEncoding $_.FullName) { return }

        $processed++
        $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
        if ($bytes.Length -eq 0) { return }

        # Remove UTF-8 BOM if present
        $hasBom = ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF)
        if ($hasBom) { $bytes = $bytes[3..($bytes.Length-1)] }

        # Pure ASCII — nothing to convert
        $hasHigh = $false
        foreach ($b in $bytes) { if ($b -ge 0x80) { $hasHigh = $true; break } }
        if (-not $hasHigh) {
            if ($hasBom) { [System.IO.File]::WriteAllBytes($_.FullName, $bytes) }
            return
        }

        $sourceIsUtf8 = Test-Utf8Bytes $bytes

        if ($TargetEncoding -eq 'utf8') {
            if ($sourceIsUtf8) {
                if ($hasBom) { [System.IO.File]::WriteAllBytes($_.FullName, $bytes) }
                return
            }
            $text = $cp1251.GetString($bytes)
            [System.IO.File]::WriteAllBytes($_.FullName, $utf8NoBom.GetBytes($text))
            $converted++
        }
        else {
            if (-not $sourceIsUtf8) { return }
            $text = $utf8NoBom.GetString($bytes)
            [System.IO.File]::WriteAllBytes($_.FullName, $cp1251.GetBytes($text))
            $converted++
        }
    }

    Write-Host ("  processed text files: {0}, converted: {1}" -f $processed, $converted)
}

$utf8Work   = Join-Path $outDir '_work-utf8'
$cp1251Work = Join-Path $outDir '_work-cp1251'
$utf8Inner   = Join-Path $utf8Work   $SourceFolder
$cp1251Inner = Join-Path $cp1251Work $SourceFolder

Write-Host "==> Building UTF-8 tree"
Convert-Tree -Source $src -Target $utf8Inner -TargetEncoding 'utf8'

Write-Host "==> Building Windows-1251 tree"
Convert-Tree -Source $src -Target $cp1251Inner -TargetEncoding 'cp1251'

$utf8Zip   = Join-Path $outDir ("cleantalk.antispam-{0}-utf8.zip"   -f $Version)
$cp1251Zip = Join-Path $outDir ("cleantalk.antispam-{0}-cp1251.zip" -f $Version)
if (Test-Path $utf8Zip)   { Remove-Item -Force $utf8Zip }
if (Test-Path $cp1251Zip) { Remove-Item -Force $cp1251Zip }

Write-Host "==> Zipping UTF-8   -> $utf8Zip"
Compress-Archive -Path (Join-Path $utf8Inner   '*') -DestinationPath $utf8Zip   -Force
Write-Host "==> Zipping CP1251  -> $cp1251Zip"
Compress-Archive -Path (Join-Path $cp1251Inner '*') -DestinationPath $cp1251Zip -Force

Remove-Item -Recurse -Force $utf8Work
Remove-Item -Recurse -Force $cp1251Work

Write-Host ""
Write-Host "Done. Version: $Version"
Write-Host "  $utf8Zip"
Write-Host "  $cp1251Zip"
