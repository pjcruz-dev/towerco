# TowerOS scenario test runner (Windows PowerShell)
#
# Usage:
#   .\scripts\test.ps1                    # smoke (fast sanity)
#   .\scripts\test.ps1 all                # backend + frontend checks
#   .\scripts\test.ps1 list               # show scenarios
#   .\scripts\test.ps1 scenario team-access
#   .\scripts\test.ps1 scenario e-approval
#   .\scripts\test.ps1 backend unit
#   .\scripts\test.ps1 frontend
#
param(
    [Parameter(Position = 0)]
    [ValidateSet("smoke", "all", "list", "backend", "frontend", "scenario")]
    [string]$Command = "smoke",

    [Parameter(Position = 1)]
    [string]$Target = ""
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"
$Frontend = Join-Path $Root "frontend"

$Scenarios = [ordered]@{
    smoke           = @{ Label = "Smoke (AdminOne + Http + Workspace)"; Testsuite = "Smoke" }
    team-access     = @{ Label = "Team & Access / IAM"; Testsuite = "TeamAccess" }
    admin           = @{ Label = "Team & Access (alias)"; Testsuite = "TeamAccess" }
    e-approval      = @{ Label = "E-Approval"; Testsuite = "EApproval" }
    rollout         = @{ Label = "Rollout / Project-One gates"; Testsuite = "Rollout" }
    procurement     = @{ Label = "Procurement-One"; Testsuite = "ProcurementOne" }
    documents       = @{ Label = "Documents"; Testsuite = "Documents" }
    project         = @{ Label = "Project-One"; Testsuite = "ProjectOne" }
    ticketing       = @{ Label = "Ticketing"; Testsuite = "Ticketing" }
    platform        = @{ Label = "Platform superadmin"; Testsuite = "Platform" }
    infrastructure  = @{ Label = "Infrastructure"; Testsuite = "Infrastructure" }
    notifications   = @{ Label = "Notifications"; Testsuite = "Notifications" }
    unit            = @{ Label = "Backend unit tests"; Testsuite = "Unit" }
    feature         = @{ Label = "All backend feature tests"; Testsuite = "Feature" }
}

function Write-Header([string]$Text) {
    Write-Host ""
    Write-Host "==> $Text" -ForegroundColor Cyan
}

function Invoke-BackendSuite([string]$Testsuite) {
    Write-Header "Backend: $Testsuite"
    Push-Location $Backend
    try {
        if ($Testsuite -eq "Feature") {
            php -d memory_limit=1G artisan test --testsuite=Feature
        } elseif ($Testsuite -eq "Unit") {
            php artisan test --testsuite=Unit
        } else {
            php -d memory_limit=1G artisan test --testsuite=$Testsuite
        }
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    } finally {
        Pop-Location
    }
}

function Invoke-FrontendUnitTests() {
    Write-Header "Frontend: vitest"
    Push-Location $Frontend
    try {
        npm run test
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    } finally {
        Pop-Location
    }
}

function Invoke-FrontendChecks() {
    Invoke-FrontendUnitTests

    Push-Location $Frontend
    try {
        Write-Header "Frontend: typecheck"
        npm run typecheck
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

        Write-Header "Frontend: eslint"
        npm run lint
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    } finally {
        Pop-Location
    }
}

function Invoke-All() {
    Invoke-BackendSuite -Testsuite "Feature"
    Invoke-BackendSuite -Testsuite "Unit"
    Invoke-FrontendChecks
}

switch ($Command) {
    "list" {
        Write-Host "Available backend scenarios:" -ForegroundColor Green
        foreach ($key in $Scenarios.Keys) {
            $entry = $Scenarios[$key]
            Write-Host ("  {0,-16} {1}" -f $key, $entry.Label)
        }
        Write-Host ""
        Write-Host "Examples:"
        Write-Host "  .\scripts\test.ps1"
        Write-Host "  .\scripts\test.ps1 scenario team-access"
        Write-Host "  .\scripts\test.ps1 all"
        exit 0
    }
    "smoke" {
        Invoke-BackendSuite -Testsuite "Smoke"
        Invoke-FrontendUnitTests
    }
    "all" {
        Invoke-All
    }
    "backend" {
        $suite = if ($Target -ne "") { $Target } else { "Feature" }
        if (-not $Scenarios.Contains($suite)) {
            Write-Error "Unknown backend suite '$suite'. Run .\scripts\test.ps1 list"
        }
        Invoke-BackendSuite -Testsuite $Scenarios[$suite].Testsuite
    }
    "frontend" {
        Invoke-FrontendChecks
    }
    "scenario" {
        if ($Target -eq "") {
            Write-Error "Specify a scenario name. Example: .\scripts\test.ps1 scenario team-access"
        }
        if (-not $Scenarios.Contains($Target)) {
            Write-Error "Unknown scenario '$Target'. Run .\scripts\test.ps1 list"
        }
        Invoke-BackendSuite -Testsuite $Scenarios[$Target].Testsuite
    }
}

Write-Host ""
Write-Host "Done." -ForegroundColor Green
