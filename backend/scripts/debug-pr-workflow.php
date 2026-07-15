<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementPrFormResolverService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tenantId = $argv[1] ?? 'tenant973abde9-02f1-430a-9108-f0d5b68198e0';
$formId = $argv[2] ?? null;

$tenant = Tenant::query()->find($tenantId);
if ($tenant === null) {
    fwrite(STDERR, "Tenant not found: {$tenantId}\n");
    exit(1);
}

tenancy()->initialize($tenant);

$resolver = app(ProcurementPrFormResolverService::class);
$resolved = $resolver->resolvePublishedForm();
echo 'Resolved PR form: '.($resolved?->id ?? 'none').' | '.($resolved?->name ?? '')."\n";

$forms = EApprovalForm::query()
    ->where('status', 'published')
    ->orderByDesc('updated_at')
    ->get()
    ->filter(static fn (EApprovalForm $form): bool => ($form->metadata_json['form_family'] ?? null) === 'purchase_requisition');

foreach ($forms as $form) {
    if ($formId !== null && (string) $form->id !== $formId) {
        continue;
    }

    $form->load('workflowTemplate.steps');
    echo "\nForm: {$form->id} | {$form->name} | updated: {$form->updated_at}\n";
    echo ' use_approval_policy: '.(($form->metadata_json['use_approval_policy'] ?? false) ? 'yes' : 'no')."\n";

    $permanent = $form->workflowTemplate?->steps ?? collect();
    $all = $form->workflowTemplate?->allSteps()->get() ?? collect();
    echo ' permanent steps: '.$permanent->count()."\n";
    echo ' all steps (incl compiled): '.$all->count()."\n";

    foreach ($permanent as $step) {
        $user = TenantUser::query()->find($step->approver_id);
        echo "  step {$step->step_order} type={$step->approver_type} approver_id={$step->approver_id}\n";
        echo '    user: '.($user?->email ?? 'NOT FOUND').' active='.($user ? ($user->is_active ? 'yes' : 'no') : 'n/a')."\n";
    }
}

tenancy()->end();
