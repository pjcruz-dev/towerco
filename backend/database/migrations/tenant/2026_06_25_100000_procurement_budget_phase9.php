<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_cost_centers')) {
            Schema::create('procurement_cost_centers', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('code', 32)->unique();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('procurement_budget_lines')) {
            Schema::create('procurement_budget_lines', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('project_id')->nullable()->index();
                $table->uuid('rollout_id')->nullable()->index();
                $table->uuid('cost_center_id')->nullable();
                $table->string('line_code', 64)->nullable();
                $table->string('description');
                $table->string('expense_type', 16)->default('capex');
                $table->decimal('budget_amount', 14, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('cost_center_id')->references('id')->on('procurement_cost_centers')->nullOnDelete();
            });
        }

        if (Schema::hasTable('procurement_pr_lines')) {
            Schema::table('procurement_pr_lines', function (Blueprint $table): void {
                if (! Schema::hasColumn('procurement_pr_lines', 'cost_center_id')) {
                    $table->uuid('cost_center_id')->nullable()->after('amount');
                }
                if (! Schema::hasColumn('procurement_pr_lines', 'expense_type')) {
                    $table->string('expense_type', 16)->nullable()->after('cost_center_id');
                }
                if (! Schema::hasColumn('procurement_pr_lines', 'budget_line_id')) {
                    $table->uuid('budget_line_id')->nullable()->after('expense_type');
                }
            });
        }

        if (Schema::hasTable('procurement_po_lines')) {
            Schema::table('procurement_po_lines', function (Blueprint $table): void {
                if (! Schema::hasColumn('procurement_po_lines', 'cost_center_id')) {
                    $table->uuid('cost_center_id')->nullable()->after('amount');
                }
                if (! Schema::hasColumn('procurement_po_lines', 'expense_type')) {
                    $table->string('expense_type', 16)->nullable()->after('cost_center_id');
                }
                if (! Schema::hasColumn('procurement_po_lines', 'budget_line_id')) {
                    $table->uuid('budget_line_id')->nullable()->after('expense_type');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['procurement_po_lines', 'procurement_pr_lines'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    foreach (['budget_line_id', 'expense_type', 'cost_center_id'] as $column) {
                        if (Schema::hasColumn($tableName, $column)) {
                            $table->dropColumn($column);
                        }
                    }
                });
            }
        }

        Schema::dropIfExists('procurement_budget_lines');
        Schema::dropIfExists('procurement_cost_centers');
    }
};
