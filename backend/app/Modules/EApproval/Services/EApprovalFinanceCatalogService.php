<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\Documents\Models\Site;
use App\Modules\EApproval\Models\EApprovalMasterDataRow;
use App\Modules\EApproval\Models\EApprovalMasterDataSet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class EApprovalFinanceCatalogService
{
    public function seed(): void
    {
        $this->seedSet('expense_types', 'Expense types', [
            ['code' => 'land', 'label' => 'Transportation - Land', 'data' => ['vatable' => true], 'sort' => 1],
            ['code' => 'sea', 'label' => 'Transportation - Sea', 'data' => ['vatable' => true], 'sort' => 2],
            ['code' => 'air', 'label' => 'Transportation - Air', 'data' => ['vatable' => true], 'sort' => 3],
            ['code' => 'gasoline', 'label' => 'Gasoline', 'data' => ['vatable' => true], 'sort' => 4],
            ['code' => 'lodging', 'label' => 'Lodging', 'data' => ['vatable' => true], 'sort' => 5],
            ['code' => 'per_diem', 'label' => 'Per Diem', 'data' => ['vatable' => false, 'per_diem' => true], 'sort' => 6],
        ]);

        $this->seedSet('departments', 'Departments', [
            ['code' => 'operations', 'label' => 'Operations', 'data' => [], 'sort' => 1],
            ['code' => 'finance', 'label' => 'Finance', 'data' => [], 'sort' => 2],
            ['code' => 'engineering', 'label' => 'Engineering', 'data' => [], 'sort' => 3],
            ['code' => 'hr', 'label' => 'Human resources', 'data' => [], 'sort' => 4],
        ]);

        $siteRows = [];
        foreach (self::sampleSites() as $index => $site) {
            $siteRows[] = [
                'code' => $site['site_code'],
                'label' => $site['site_code'].' — '.$site['name'],
                'data' => [
                    'address' => $site['full_address'],
                    'name' => $site['name'],
                ],
                'sort' => $index + 1,
            ];
        }
        $this->seedSet('sites', 'Sites', $siteRows);

        $this->seedSampleSites();
    }

    /**
     * Sample sites for Project site, From site, and To site when this workspace has no sites table yet.
     *
     * @return list<array{site_code: string, name: string, full_address: string}>
     */
    public static function sampleSites(): array
    {
        return [
            ['site_code' => 'MNL-ATC-01', 'name' => 'Makati', 'full_address' => '32nd Street, Bonifacio Global City, Taguig'],
            ['site_code' => 'CEB-ATC-02', 'name' => 'Cebu IT Park', 'full_address' => 'Jose Maria del Mar, Cebu City'],
            ['site_code' => 'DVO-ATC-03', 'name' => 'Davao Lanang', 'full_address' => 'J.P. Laurel Avenue, Davao City'],
            ['site_code' => 'BCD-ATC-04', 'name' => 'Bacolod', 'full_address' => 'Lacson Street, Bacolod City'],
        ];
    }

    public function seedSampleSites(): void
    {
        try {
            if (! Schema::connection('tenant')->hasTable('sites') || Site::query()->exists()) {
                return;
            }
        } catch (QueryException) {
            return;
        }

        $samples = self::sampleSites();

        foreach ($samples as $sample) {
            Site::query()->create([
                ...$sample,
                'type' => 'tower',
                'status' => 'active',
            ]);
        }
    }

    /**
     * @param  list<array{code: string, label: string, data: array<string, mixed>, sort: int}>  $rows
     */
    private function seedSet(string $key, string $name, array $rows): void
    {
        $set = EApprovalMasterDataSet::query()->where('key', $key)->first();
        if ($set === null) {
            $set = EApprovalMasterDataSet::query()->create([
                'id' => (string) Str::uuid(),
                'key' => $key,
                'name' => $name,
                'status' => 'active',
            ]);
        }

        foreach ($rows as $row) {
            $existing = $set->rows()->where('code', $row['code'])->first();
            if ($existing instanceof EApprovalMasterDataRow) {
                continue;
            }

            EApprovalMasterDataRow::query()->create([
                'id' => (string) Str::uuid(),
                'set_id' => $set->id,
                'code' => $row['code'],
                'label' => $row['label'],
                'data_json' => $row['data'],
                'sort_order' => $row['sort'],
                'is_active' => true,
            ]);
        }
    }
}
