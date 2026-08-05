# Compare host clock vs API container and print APP_KEY fingerprint (MFA/TOTP diagnostics).
# Usage (repo root): powershell -ExecutionPolicy Bypass -File scripts/mfa-health-check.ps1

$ErrorActionPreference = "Stop"
$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot

$envFile = Join-Path $repoRoot ".env.docker"
if (-not (Test-Path $envFile)) {
    Write-Error "Missing .env.docker - copy env.docker.example first."
}

$hostUtc = [DateTimeOffset]::UtcNow
Write-Host "Host UTC:  $($hostUtc.ToString('o'))"
Write-Host "Host unix: $($hostUtc.ToUnixTimeSeconds())"
Write-Host ""

# Prefer project compose wrapper (respects TOWEROS_CONTAINER_CLI / .env.docker).
$json = node scripts/compose-run.js --env-file .env.docker exec -T api php artisan toweros:mfa-health --json 2>$null
if ($LASTEXITCODE -ne 0 -and -not $json) {
    Write-Error "Failed to run toweros:mfa-health inside api container. Is the stack up? (npm run dev)"
}

$health = $json | ConvertFrom-Json
Write-Host "Container UTC:  $($health.server_utc)"
Write-Host "Container unix: $($health.server_unix)"
Write-Host "APP_KEY fingerprint: $($health.app_key_fingerprint)"
Write-Host "Encrypt round-trip: $($health.encrypt_round_trip_ok)"
Write-Host ""

$skew = [math]::Abs([int64]$health.server_unix - $hostUtc.ToUnixTimeSeconds())
Write-Host "Clock skew (host vs container): ${skew}s"

$failed = $false
if (-not $health.ok) {
    Write-Host "FAIL: APP_KEY missing/invalid or encrypt round-trip failed." -ForegroundColor Red
    $failed = $true
}
if ($skew -gt 30) {
    Write-Host "FAIL: Clock skew > 30s - TOTP codes will often show as Invalid MFA code." -ForegroundColor Red
    Write-Host "  Fix: sync Windows time (start W32Time), restart Docker/Podman, retry." -ForegroundColor Yellow
    $failed = $true
} elseif ($skew -gt 5) {
    Write-Host "WARN: Clock skew > 5s - monitor; TOTP window is +/-30s." -ForegroundColor Yellow
}

if (-not $failed) {
    Write-Host "OK: MFA prerequisites look healthy for this machine." -ForegroundColor Green
    exit 0
}

exit 1
