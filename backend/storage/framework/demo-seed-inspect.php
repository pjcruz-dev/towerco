<?php

use App\Models\Tenant;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

tenancy()->initialize(Tenant::query()->find('5b70ed8e-2433-4bf6-958e-7f25329c8757'));

$slugs = [
    'construction_activities',
    'site_documents',
    'postcon_trackers',
    'payments_and_receipts',
    'electric_utilities',
    'fiscal_periods',
    'capex_budgets',
    'bir_form_2307_certificates',
];

$names = [
    'activity', 'document_type', 'document_status', 'cfei_milestone', 'type',
    'payment_method', 'status', 'utility_type', 'region', 'cost_classification', 'cost_category',
];

$entityIds = DynEntity::query()->whereIn('slug', $slugs)->pluck('id', 'slug');
foreach ($entityIds as $slug => $id) {
    $fields = DynField::query()->where('entity_id', $id)->whereIn('name', $names)->get();
    foreach ($fields as $f) {
        echo $slug.'.'.$f->name.'='.json_encode($f->options_json).PHP_EOL;
    }
}
