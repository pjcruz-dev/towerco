# Local-only HTTP timing for TowerOS API (no auth required endpoints).
# Usage: powershell -ExecutionPolicy Bypass -File scripts/profile-local-benchmark.ps1
# Optional: -ApiBase http://127.0.0.1:8000 -WebBase http://127.0.0.1:80 -Runs 5

param(
    [string]$ApiBase = "http://127.0.0.1:8000",
    [string]$WebBase = "http://127.0.0.1:80",
    [int]$Runs = 5
)

$ErrorActionPreference = "Stop"

function Measure-Url {
    param([string]$Label, [string]$Url)
    $times = @()
    $status = 0
    for ($i = 0; $i -lt $Runs; $i++) {
        $sw = [System.Diagnostics.Stopwatch]::StartNew()
        try {
            $resp = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 120
            $status = [int]$resp.StatusCode
        } catch {
            if ($_.Exception.Response) {
                $status = [int]$_.Exception.Response.StatusCode.value__
            } else {
                $status = 0
            }
        }
        $sw.Stop()
        $times += $sw.Elapsed.TotalMilliseconds
    }
    $sorted = $times | Sort-Object
    $mid = [math]::Floor($sorted.Count / 2)
    return [pscustomobject]@{
        Label  = $Label
        Url    = $Url
        Status = $status
        MinMs  = [math]::Round(($sorted | Measure-Object -Minimum).Minimum, 1)
        MedMs  = [math]::Round($sorted[$mid], 1)
        MaxMs  = [math]::Round(($sorted | Measure-Object -Maximum).Maximum, 1)
        Runs   = $Runs
    }
}

Write-Host "TowerOS local benchmark (median of $Runs runs)" -ForegroundColor Cyan
Write-Host "API: $ApiBase"
Write-Host "Web: $WebBase"
Write-Host ""

$targets = @(
    @{ Label = "Laravel /up (health HTML)"; Url = "$ApiBase/up" },
    @{ Label = "Central health JSON"; Url = "$ApiBase/api/v1/health" },
    @{ Label = "Next.js home (dev)"; Url = "$WebBase/" }
)

$rows = foreach ($t in $targets) {
    Measure-Url -Label $t.Label -Url $t.Url
}

$rows | Format-Table Label, Status, MinMs, MedMs, MaxMs -AutoSize

$outDir = Join-Path $PSScriptRoot "output"
New-Item -ItemType Directory -Force -Path $outDir | Out-Null
$outFile = Join-Path $outDir "local-benchmark.json"
$payload = @{
    generated_at = (Get-Date).ToUniversalTime().ToString("o")
    api_base     = $ApiBase
    web_base     = $WebBase
    runs         = $Runs
    results      = $rows
    notes        = @(
        "High /up times are normal in Docker + php artisan serve on Windows."
        "Use dev:host (API on host PHP) for faster local API."
        "Authenticated tenant endpoints need a token - use browser Network tab per page."
    )
}
$payload | ConvertTo-Json -Depth 5 | Set-Content -Encoding utf8 $outFile
Write-Host ""
Write-Host "Wrote $outFile" -ForegroundColor Green
