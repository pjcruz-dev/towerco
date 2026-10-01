# Multi-module removal audit (read-only)
# Confirmed for removal: AssetOne, FiberOne, TowerOne, Sites, Rollout, ProjectOne (ProcurementOne
# already has its own plan/audit). Finds what depends on each one from OUTSIDE itself, so nothing
# gets deleted out from under Ticketing, E-Forms, DocExtract or Document Control.
# Never touches .env files. Masks anything that looks like a credential before saving.

cd C:\LaravelProject\TowerOS
$out  = "$env:TEMP\TowerOS-module-removal-audit.txt"
$root = (Get-Location).Path + '\'
Set-Content $out "Multi-module removal audit (read-only)" -Encoding UTF8
function Put($text) { $text | Out-File $out -Append -Encoding UTF8 }
function Section($t) { Put "`r`n`r`n######## $t ########" }

# module => @(folder name, PascalCase name used in class/namespace, module key used in TenantEnabledModulesResolver)
$modules = @(
  @{ Folder = 'AssetOne';  Pascal = 'AssetOne';  Key = 'asset_one' },
  @{ Folder = 'FiberOne';  Pascal = 'FiberOne';  Key = 'fiber_one' },
  @{ Folder = 'TowerOne';  Pascal = 'TowerOne';  Key = 'tower_one' },
  @{ Folder = 'Sites';     Pascal = 'Sites';     Key = 'sites' },
  @{ Folder = 'Rollout';   Pascal = 'Rollout';   Key = 'rollout' },
  @{ Folder = 'ProjectOne';Pascal = 'ProjectOne';Key = 'project_one' }
)

function GrepOutside($label, $paths, $pattern, $excludeDirPattern, $max = 150) {
  Put "`r`n-- $label   /$pattern/"
  $files = @(foreach ($p in $paths) { if (Test-Path $p) { Get-ChildItem $p -Recurse -File -Include *.php,*.ts,*.tsx -ErrorAction SilentlyContinue } })
  $files = $files | Where-Object { $_.FullName -notmatch $excludeDirPattern -and $_.FullName -notmatch '\\(vendor|node_modules|\.git|storage|\.next)\\' }
  if ($files.Count -eq 0) { Put "(no files)"; return }
  $hits = @($files | Select-String -Pattern $pattern -ErrorAction SilentlyContinue | Where-Object { $_.Line -notmatch '^\s*use ' } | Select-Object -First $max)
  if ($hits.Count -eq 0) { Put "(no matches outside the module)" }
  else { $hits | ForEach-Object { Put ("{0}:{1}: {2}" -f $_.Path.Replace($root,''), $_.LineNumber, $_.Line.Trim()) } }
}

foreach ($m in $modules) {
  Section ("MODULE: " + $m.Folder + "  (key: " + $m.Key + ")")

  Put "`r`n-- does backend\app\Modules\$($m.Folder) exist?"
  Put (Test-Path "backend\app\Modules\$($m.Folder)")

  GrepOutside "cross-module PHP references (imports, class usage)" @('backend\app') `
    ($m.Pascal + '|' + $m.Pascal.Substring(0,$m.Pascal.Length-3) + '(Service|Model|Sync)') `
    ("app\\\\Modules\\\\" + $m.Folder)

  Put "`r`n-- frontend references (components/modules/lib/app, excluding the module's own folders)"
  $feFiles = @(Get-ChildItem frontend\components, frontend\modules, frontend\lib, frontend\app -Recurse -File -Include *.ts,*.tsx -ErrorAction SilentlyContinue |
    Where-Object { $_.FullName -notmatch '\\(node_modules|\.next)\\' -and $_.FullName -notmatch [regex]::Escape($m.Folder.ToLower()) -and $_.FullName -notmatch [regex]::Escape(($m.Folder -creplace '([A-Z])', '-$1').ToLower().Trim('-')) })
  $feHits = @($feFiles | Select-String -Pattern $m.Pascal -ErrorAction SilentlyContinue | Select-Object -First 60)
  if ($feHits.Count -eq 0) { Put "(no matches outside the module's own frontend folders)" }
  else { $feHits | ForEach-Object { Put ("{0}:{1}: {2}" -f $_.Path.Replace($root,''), $_.LineNumber, $_.Line.Trim()) } }

  Put "`r`n-- RBAC / permission catalog / role templates mentioning the module key or name"
  GrepOutside "RBAC" @('backend\app\Modules\Tenancy', 'backend\app\Modules\AdminOne') ($m.Key + '|' + $m.Pascal) 'NEVER_EXCLUDE'

  Put "`r`n-- billing / plan entitlements"
  GrepOutside "billing" @('backend\app\Modules\Billing', 'backend\app\Modules\AdminOne') ($m.Key + '|' + $m.Pascal + 'Features') 'NEVER_EXCLUDE'

  Put "`r`n-- tenant provisioning / onboarding coupling"
  GrepOutside "provisioning" @('backend\app\Modules\Tenancy') ($m.Key + '|' + $m.Pascal) 'NEVER_EXCLUDE'

  Put "`r`n-- scheduled commands mentioning it"
  Select-String -Path backend\bootstrap\app.php -Pattern ($m.Key -replace '_','[-_]') -ErrorAction SilentlyContinue | ForEach-Object { Put ("{0}: {1}" -f $_.LineNumber, $_.Line.Trim()) }
  Put "`r`n-- console command files under its own folder (for later deletion, not cross-refs)"
  Put (Get-ChildItem "backend\app\Console\Commands\$($m.Folder)" -File -Name -ErrorAction SilentlyContinue)

  Put "`r`n-- AiAssistant catalog / tools referencing it"
  GrepOutside "assistant" @('backend\app\Modules\AiAssistant') ($m.Key + '|' + $m.Pascal) 'NEVER_EXCLUDE'

  Put "`r`n-- Ticketing coupling (category packs, source-module links)"
  GrepOutside "ticketing" @('backend\app\Modules\Ticketing') ($m.Key + '|' + $m.Pascal) 'NEVER_EXCLUDE'

  Put "`r`n-- tenant-database migrations for this module"
  Put (Get-ChildItem backend\database\migrations\tenant -File -Name -ErrorAction SilentlyContinue | Where-Object { $_ -match ($m.Key -replace '_','[-_]') -or $_ -match $m.Folder })

  Put "`r`n-- module resolver entry"
  Select-String -Path backend\app\Modules\Tenancy\Support\TenantEnabledModulesResolver.php -Pattern ("'" + $m.Key + "'") -ErrorAction SilentlyContinue | ForEach-Object { Put ("{0}: {1}" -f $_.LineNumber, $_.Line.Trim()) }
}

Section "CROSS-CHECK: does anything in the four ACTIVE modules reference any of the six?"
$activePaths = @('backend\app\Modules\Ticketing','backend\app\Modules\EApproval','backend\app\Modules\DocExtract','backend\app\Modules\Documents',
                 'frontend\components\ticketing','frontend\lib\ticketing','frontend\components\e-approval','frontend\lib\e-approval',
                 'frontend\components\doc-extract','frontend\lib\doc-extract','frontend\components\documents','frontend\lib\documents')
$allNames = ($modules | ForEach-Object { $_.Pascal }) -join '|'
GrepOutside "active-module -> to-be-removed-module references" $activePaths $allNames 'NEVER_EXCLUDE' 200

$t = Get-Content $out -Raw -Encoding UTF8
$t = [regex]::Replace($t, '(?i)((?:password|secret|api_?key|token)\W{0,3}\s*(?:=>|=|:)\s*)\S+', '${1}***')
Set-Content $out $t -Encoding UTF8
notepad $out
