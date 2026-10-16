<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'given_name')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('given_name', 120)->nullable();
            });
        }
        if (! Schema::hasColumn('users', 'surname')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('surname', 120)->nullable();
            });
        }

        Schema::create('department_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('department_name', 180)->unique();
            $table->string('code', 8);
            $table->timestamps();
        });

        Schema::create('asset_tag_sequences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('prefix', 16)->unique();
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('it_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('asset_tag', 32)->unique();
            $table->string('serial_number', 128)->unique();
            $table->string('asset_type', 32);
            $table->string('status', 32);
            $table->string('manufacturer', 180)->nullable();
            $table->string('product_name', 180)->nullable();
            $table->string('product_version', 120)->nullable();
            $table->string('hostname', 64)->nullable();
            $table->string('old_hostname', 64)->nullable();
            $table->string('processor', 255)->nullable();
            $table->string('installed_ram', 64)->nullable();
            $table->string('operating_system', 180)->nullable();
            $table->string('system_type', 64)->nullable();
            $table->string('bios_version', 120)->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_end')->nullable();
            $table->date('commission_date')->nullable();
            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('custom_values_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('asset_type');
            $table->index('hostname');
            $table->index('assigned_user_id');
            $table->index(['status', 'asset_type']);
        });

        Schema::create('asset_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained('it_assets')->restrictOnDelete();
            $table->foreignUuid('employee_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('returned_at')->nullable();
            $table->string('status', 32);
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('asset_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained('it_assets')->restrictOnDelete();
            $table->string('event_type', 32);
            $table->string('from_place', 180)->nullable();
            $table->string('to_place', 180)->nullable();
            $table->json('payload_json')->nullable();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['asset_id', 'occurred_at']);
        });

        Schema::create('hostname_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained('it_assets')->restrictOnDelete();
            $table->string('old_hostname', 64)->nullable();
            $table->string('new_hostname', 64);
            $table->string('reason', 180)->nullable();
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['asset_id', 'changed_at']);
        });

        Schema::create('asset_network_adapters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained('it_assets')->cascadeOnDelete();
            $table->string('adapter_name', 180)->nullable();
            $table->string('adapter_type', 64)->nullable();
            $table->string('mac_address', 32)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_detected')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'mac_address']);
            $table->index('mac_address');
        });

        Schema::create('asset_disks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained('it_assets')->cascadeOnDelete();
            $table->string('model', 180)->nullable();
            $table->string('serial_number', 128)->nullable();
            $table->string('size_label', 64)->nullable();
            $table->string('interface', 64)->nullable();
            $table->timestamp('last_detected')->nullable();
            $table->timestamps();

            $table->index('asset_id');
        });

        Schema::create('asset_video_cards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained('it_assets')->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('driver_version', 64)->nullable();
            $table->timestamp('last_detected')->nullable();
            $table->timestamps();

            $table->index('asset_id');
        });

        Schema::create('asset_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained('it_assets')->cascadeOnDelete();
            $table->string('application_name', 255);
            $table->string('version', 120)->nullable();
            $table->string('publisher', 180)->nullable();
            $table->date('installed_date')->nullable();
            $table->timestamp('last_detected')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'application_name']);
            $table->index('application_name');
        });

        Schema::create('asset_repairs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained('it_assets')->restrictOnDelete();
            $table->string('status', 32);
            $table->boolean('usable')->nullable();
            $table->boolean('replacement_required')->nullable();
            $table->string('problem_type', 32)->nullable();
            $table->string('severity', 32)->nullable();
            $table->text('problem')->nullable();
            $table->text('notes')->nullable();
            $table->text('diagnosis')->nullable();
            $table->string('result', 32)->nullable();
            $table->string('vendor_email_subject', 255)->nullable();
            $table->text('vendor_email_body')->nullable();
            $table->foreignUuid('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'status']);
        });

        Schema::create('asset_repair_replacements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('repair_id')->constrained('asset_repairs')->cascadeOnDelete();
            $table->foreignUuid('original_asset_id')->constrained('it_assets')->restrictOnDelete();
            $table->foreignUuid('replacement_asset_id')->constrained('it_assets')->restrictOnDelete();
            $table->string('replacement_type', 32);
            $table->string('status', 32);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index(['original_asset_id', 'status']);
            $table->index(['replacement_asset_id', 'status']);
        });

        Schema::create('accountability', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 32)->unique();
            $table->foreignUuid('employee_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedSmallInteger('reminder_count')->default(0);
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('status');
        });

        Schema::create('accountability_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('accountability_id')->constrained('accountability')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->nullable()->constrained('it_assets')->nullOnDelete();
            $table->string('label', 180);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('accountability_id');
        });

        Schema::create('accountability_signatures', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('accountability_id')->constrained('accountability')->cascadeOnDelete();
            $table->foreignUuid('signed_by')->constrained('users')->restrictOnDelete();
            $table->text('signature');
            $table->timestamp('signed_at');
            $table->timestamps();
        });

        Schema::create('offboarding', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 32)->unique();
            $table->foreignUuid('employee_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->date('last_working_day')->nullable();
            $table->foreignUuid('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('offboarding_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('offboarding_id')->constrained('offboarding')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained('it_assets')->restrictOnDelete();
            $table->string('result', 32)->nullable();
            $table->string('condition', 32)->nullable();
            $table->boolean('physical_damage')->nullable();
            $table->string('accessories', 180)->nullable();
            $table->string('battery_condition', 64)->nullable();
            $table->string('hardware_condition', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamps();

            $table->index(['offboarding_id', 'result']);
        });

        Schema::create('asset_disposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained('it_assets')->restrictOnDelete();
            $table->string('disposition', 32);
            $table->date('disposition_date');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('buyer_vendor', 180)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('asset_id');
        });

        Schema::create('asset_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->nullable()->constrained('it_assets')->nullOnDelete();
            $table->foreignUuid('repair_id')->nullable()->constrained('asset_repairs')->nullOnDelete();
            $table->foreignUuid('disposal_id')->nullable()->constrained('asset_disposals')->nullOnDelete();
            $table->string('file_path', 512);
            $table->string('file_name', 255);
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('asset_id');
        });

        Schema::create('asset_inventory_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('client_key', 64)->unique();
            $table->foreignUuid('asset_id')->nullable()->constrained('it_assets')->nullOnDelete();
            $table->string('windows_identity', 180)->nullable();
            $table->foreignUuid('matched_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->index('asset_id');
        });

        Schema::create('asset_field_indexes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained('it_assets')->cascadeOnDelete();
            $table->string('field_name', 128);
            $table->string('value_string', 512)->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->date('value_date')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'field_name']);
            $table->index(['field_name', 'value_string']);
        });

        Schema::create('asset_settings', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->json('value_json')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('sidebar_nav_items') && ! DB::table('sidebar_nav_items')->where('key', 'group.operations.assets')->exists()) {
            $parentId = (string) Str::uuid();
            $now = now();
            DB::table('sidebar_nav_items')->insert([
                'id' => $parentId,
                'parent_id' => null,
                'key' => 'group.operations.assets',
                'type' => 'header',
                'title' => 'Assets',
                'icon' => 'Package',
                'href' => null,
                'entity_slug' => null,
                'permission_key' => null,
                'required_permissions' => json_encode(['assets:view', 'assets:self:view']),
                'permissions_match' => 'any',
                'module' => 'asset_management',
                'sort_order' => 8,
                'is_system' => true,
                'is_visible' => true,
                'is_global_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ([
                ['group.operations.assets.inventory', 'Inventory', '/assets', 0, ['assets:view', 'assets:self:view']],
                ['group.operations.assets.scan', 'Scan', '/assets/scan', 1, ['assets:view', 'assets:manage']],
                ['group.operations.assets.offboarding', 'Offboarding', '/assets/offboarding', 2, ['assets:offboard']],
                ['group.operations.assets.fields', 'Fields', '/assets/fields', 3, ['assets:fields:manage']],
            ] as [$key, $title, $href, $order, $permissions]) {
                DB::table('sidebar_nav_items')->insert([
                    'id' => (string) Str::uuid(),
                    'parent_id' => $parentId,
                    'key' => $key,
                    'type' => 'internal_page',
                    'title' => $title,
                    'icon' => null,
                    'href' => $href,
                    'entity_slug' => null,
                    'permission_key' => null,
                    'required_permissions' => json_encode($permissions),
                    'permissions_match' => 'any',
                    'module' => 'asset_management',
                    'sort_order' => $order,
                    'is_system' => true,
                    'is_visible' => true,
                    'is_global_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sidebar_nav_items')) {
            $parent = DB::table('sidebar_nav_items')->where('key', 'group.operations.assets')->value('id');
            if (is_string($parent)) {
                DB::table('sidebar_nav_items')->where('parent_id', $parent)->delete();
                DB::table('sidebar_nav_items')->where('id', $parent)->delete();
            }
        }

        Schema::dropIfExists('asset_settings');
        Schema::dropIfExists('asset_field_indexes');
        Schema::dropIfExists('asset_inventory_submissions');
        Schema::dropIfExists('asset_documents');
        Schema::dropIfExists('asset_disposals');
        Schema::dropIfExists('offboarding_items');
        Schema::dropIfExists('offboarding');
        Schema::dropIfExists('accountability_signatures');
        Schema::dropIfExists('accountability_items');
        Schema::dropIfExists('accountability');
        Schema::dropIfExists('asset_repair_replacements');
        Schema::dropIfExists('asset_repairs');
        Schema::dropIfExists('asset_applications');
        Schema::dropIfExists('asset_video_cards');
        Schema::dropIfExists('asset_disks');
        Schema::dropIfExists('asset_network_adapters');
        Schema::dropIfExists('hostname_history');
        Schema::dropIfExists('asset_events');
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('it_assets');
        Schema::dropIfExists('asset_tag_sequences');
        Schema::dropIfExists('department_codes');

        if (Schema::hasColumn('users', 'surname')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('surname');
            });
        }
        if (Schema::hasColumn('users', 'given_name')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('given_name');
            });
        }
    }
};
