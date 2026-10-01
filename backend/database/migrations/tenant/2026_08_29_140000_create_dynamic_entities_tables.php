<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dyn_entities')) {
            Schema::create('dyn_entities', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('slug', 128);
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('module_pack', 32)->default('pm');
                $table->string('storage_mode', 32)->default('json');
                $table->boolean('is_location_based')->default(false);
                $table->string('source_linked_table', 128)->nullable();
                $table->json('related_tabs_json')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('slug');
                $table->index(['module_pack', 'is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('dyn_field_groups')) {
            Schema::create('dyn_field_groups', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('entity_id')->constrained('dyn_entities')->cascadeOnDelete();
                $table->string('name');
                $table->boolean('applies_to_form')->default(true);
                $table->boolean('applies_to_view')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['entity_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('dyn_fields')) {
            Schema::create('dyn_fields', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('entity_id')->constrained('dyn_entities')->cascadeOnDelete();
                $table->string('name', 128);
                $table->string('label');
                $table->string('type', 64)->default('text');
                $table->boolean('is_required')->default(false);
                $table->boolean('is_system_field')->default(false);
                $table->string('system_column', 128)->nullable();
                $table->boolean('show_in_table')->default(false);
                $table->boolean('is_filterable')->default(false);
                $table->json('options_json')->nullable();
                $table->foreignUuid('target_entity_id')->nullable()->constrained('dyn_entities')->nullOnDelete();
                $table->string('placeholder')->nullable();
                $table->text('formula_definition')->nullable();
                $table->unsignedTinyInteger('column_span')->default(6);
                $table->unsignedInteger('field_order')->default(10);
                $table->foreignUuid('form_group_id')->nullable()->constrained('dyn_field_groups')->nullOnDelete();
                $table->foreignUuid('view_group_id')->nullable()->constrained('dyn_field_groups')->nullOnDelete();
                $table->json('conditional_rules_json')->nullable();
                $table->boolean('is_virtual')->default(false);
                $table->timestamps();

                $table->unique(['entity_id', 'name']);
                $table->index(['entity_id', 'field_order']);
                $table->index(['entity_id', 'show_in_table']);
            });
        }

        if (! Schema::hasTable('dyn_records')) {
            Schema::create('dyn_records', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('entity_id')->constrained('dyn_entities')->cascadeOnDelete();
                $table->string('status', 64)->nullable();
                $table->string('title')->nullable();
                $table->json('values_json');
                $table->foreignUuid('parent_record_id')->nullable()->constrained('dyn_records')->nullOnDelete();
                $table->uuid('location_id')->nullable();
                $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('source_external_id', 64)->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamp('deleted_at')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['entity_id', 'is_deleted', 'status']);
                $table->index(['entity_id', 'parent_record_id']);
                $table->index(['entity_id', 'source_external_id']);
                $table->index(['entity_id', 'title']);
            });
        }

        if (! Schema::hasTable('dyn_record_indexes')) {
            Schema::create('dyn_record_indexes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('record_id')->constrained('dyn_records')->cascadeOnDelete();
                $table->foreignUuid('entity_id')->constrained('dyn_entities')->cascadeOnDelete();
                $table->string('field_name', 128);
                $table->string('value_string', 512)->nullable();
                $table->decimal('value_number', 20, 6)->nullable();
                $table->date('value_date')->nullable();
                $table->timestamps();

                $table->unique(['record_id', 'field_name']);
                $table->index(['entity_id', 'field_name', 'value_string']);
                $table->index(['entity_id', 'field_name', 'value_number']);
                $table->index(['entity_id', 'field_name', 'value_date']);
            });
        }

        if (! Schema::hasTable('atc_import_id_maps')) {
            Schema::create('atc_import_id_maps', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('source_table', 128);
                $table->string('source_id', 64);
                $table->string('target_type', 32);
                $table->uuid('target_uuid');
                $table->timestamps();

                $table->unique(['source_table', 'source_id', 'target_type'], 'atc_import_id_maps_unique');
                $table->index(['target_type', 'target_uuid']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('atc_import_id_maps');
        Schema::dropIfExists('dyn_record_indexes');
        Schema::dropIfExists('dyn_records');
        Schema::dropIfExists('dyn_fields');
        Schema::dropIfExists('dyn_field_groups');
        Schema::dropIfExists('dyn_entities');
    }
};
