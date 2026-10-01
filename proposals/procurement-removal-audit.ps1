# ProcurementOne removal audit (read-only)
# Finds everything that would break if backend/app/Modules/ProcurementOne and its frontend
# counterpart were deleted, so the E-Forms decoupling work can be scoped precisely.
# Never touches .env files. Masks anything that looks like a credential before saving.

cd C:\LaravelProject\TowerOS
$out  = "$env:TEMP\TowerOS-procurement-removal-audit.txt"
$root = (Get-Location).Path + '\'
Set-Content $out "ProcurementOne removal audit (read-only)" -Encoding UTF8
function Put($text) { $text | Out-File $out -Append -Encoding UTF8 }
function Section($t) { Put "`r`n`r`n######## $t ########" }
function ShowFile($label, $path, $first = 90, $skipLines = 0) {
  Put "`r`n-- $label   [$($path.Replace($root,''))]"
  if (Test-Path $path) { Put (Get-Content $path -Encoding UTF8 | Select-Object -Skip $skipLines -First $first) }
  else { Put "(not found)" }
}
function GrepOutside($label, $paths, $pattern, $excludeDirPattern, $max = 200) {
  Put "`r`n-- $label   /$pattern/"
  $files = @(foreach ($p in $paths) { if (Test-Path $p) { Get-ChildItem $p -Recurse -File -Include *.php,*.ts,*.tsx -ErrorAction SilentlyContinue } })
  $files = $files | Where-Object { $_.FullName -notmatch $excludeDirPattern -and $_.FullName -notmatch '\\(vendor|node_modules|\.git|storage|\.next)\\' }
  if ($files.Count -eq 0) { Put "(no files)"; return }
  $hits = @($files | Select-String -Pattern $pattern -ErrorAction SilentlyContinue | Where-Object { $_.Line -notmatch '^\s*use ' } | Select-Object -First $max)
  if ($hits.Count -eq 0) { Put "(no matches outside ProcurementOne)" }
  else { $hits | ForEach-Object { Put ("{0}:{1}: {2}" -f $_.Path.Replace($root,''), $_.LineNumber, $_.Line.Trim()) } }
}

Section "1. Every backend file OUTSIDE ProcurementOne that references it"
GrepOutside "cross-module references" @('backend\app') 'ProcurementOne|Procurement(Pr|Po|Vendor|ApInvoice|Budget|Contract)\w*(Service|Model|Sync)' 'app\\Modules\\ProcurementOne'

Section "2. E-Forms files that would need changing"
$ef = Get-ChildItem backend\app\Modules\EApproval -Recurse -File -Include *.php |
  Select-String -Pattern 'ProcurementOne' -List
Put ($ef | ForEach-Object { $_.Path.Replace($root,'') })
ShowFile "ApprovalDecisionService (constructor + hook calls)" "backend\app\Modules\EApproval\Services\ApprovalDecisionService.php" 60
ShowFile "EApprovalMasterDataService" "backend\app\Modules\EApproval\Services\EApprovalMasterDataService.php" 60
ShowFile "EApprovalFinanceProcurementKpiService" "backend\app\Modules\EApproval\Services\EApprovalFinanceProcurementKpiService.php" 90
ShowFile "EApprovalFinanceProcurementPolicyService" "backend\app\Modules\EApproval\Services\EApprovalFinanceProcurementPolicyService.php" 90
ShowFile "EApprovalPurchaseRequisitionService" "backend\app\Modules\EApproval\Services\EApprovalPurchaseRequisitionService.php" 90

Section "3. Frontend references"
GrepOutside "frontend cross-references" @('frontend') 'procurement-one|ProcurementOne|procurement_one' 'components\\procurement-one|modules\\procurement-one|lib\\procurement|lib\\api\\modules\\procurement'
Put "`r`n-- e-approval frontend files mentioning procurement/purchase (first 25):"
Put (Get-ChildItem frontend\components\e-approval, frontend\lib\e-approval, frontend\modules\e-approval -Recurse -File -Include *.ts,*.tsx -ErrorAction SilentlyContinue |
  Select-String -Pattern '(?i)procurement|purchase.?order|purchase.?requisition' | Select-Object -First 25 |
  ForEach-Object { "{0}:{1}: {2}" -f $_.Filename, $_.LineNumber, $_.Line.Trim() })

Section "4. RBAC, permissions and seed data"
GrepOutside "role/permission catalog" @('backend\app\Modules\AdminOne', 'backend\app\Modules\Tenancy', 'backend\database\seeders') 'procurement' 'app\\Modules\\ProcurementOne'
Put "`r`n-- seeders mentioning procurement:"
Put (Get-ChildItem backend\database\seeders -Recurse -File -Include *.php -ErrorAction SilentlyContinue |
  Select-String -Pattern '(?i)procurement' -List | ForEach-Object { $_.Path.Replace($root,'') })

Section "5. Migrations (tenant DB tables that would become orphaned)"
Put (Get-ChildItem backend\database\migrations\tenant -File -Name -ErrorAction SilentlyContinue | Where-Object { $_ -match '(?i)procurement' })
Put "`r`n-- central migrations mentioning procurement:"
Put (Get-ChildItem backend\database\migrations -File -Name -ErrorAction SilentlyContinue | Where-Object { $_ -match '(?i)procurement' })

Section "6. Scheduled commands and queued jobs"
Put (Get-ChildItem backend\app\Console\Commands\ProcurementOne -File -Name -ErrorAction SilentlyContinue)
Put "`r`n-- bootstrap\app.php lines mentioning procurement:"
Select-String -Path backend\bootstrap\app.php -Pattern '(?i)procurement' | ForEach-Object { Put ("{0}: {1}" -f $_.LineNumber, $_.Line.Trim()) }

Section "7. AiAssistant tool references (search/answer surface)"
GrepOutside "assistant tools" @('backend\app\Modules\AiAssistant') 'procurement' 'app\\Modules\\ProcurementOne'

$t = Get-Content $out -Raw -Encoding UTF8
$t = [regex]::Replace($t, '(?i)((?:password|secret|api_?key|token)\W{0,3}\s*(?:=>|=|:)\s*)\S+', '${1}***')
Set-Content $out $t -Encoding UTF8
notepad $out
