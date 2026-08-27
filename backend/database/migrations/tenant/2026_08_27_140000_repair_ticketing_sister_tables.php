<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs tenants where ticketing_tickets existed but comments/attachments/links
 * (or SLA columns) never landed because the original create migration returned early.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticketing_tickets')) {
            return;
        }

        if (! Schema::hasTable('ticketing_comments')) {
            Schema::create('ticketing_comments', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('ticket_id')->constrained('ticketing_tickets')->cascadeOnDelete();
                $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->boolean('is_internal')->default(false);
                $table->timestamps();

                $table->index(['ticket_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('ticketing_attachments')) {
            Schema::create('ticketing_attachments', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('ticket_id')->constrained('ticketing_tickets')->cascadeOnDelete();
                $table->foreignUuid('uploaded_by_id')->constrained('users')->cascadeOnDelete();
                $table->string('file_path');
                $table->string('file_name');
                $table->string('mime_type', 128)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->timestamps();

                $table->index('ticket_id');
            });
        }

        if (! Schema::hasTable('ticketing_links')) {
            Schema::create('ticketing_links', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('ticket_id')->constrained('ticketing_tickets')->cascadeOnDelete();
                $table->string('link_module', 64);
                $table->string('link_type', 128);
                $table->string('link_id', 36);
                $table->string('link_label', 255)->nullable();
                $table->timestamps();

                $table->index(['ticket_id', 'link_module']);
                $table->index(['link_module', 'link_type', 'link_id']);
            });
        }

        if (! Schema::hasTable('ticketing_settings')) {
            Schema::create('ticketing_settings', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('ticketing_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticketing_tickets', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable()->after('closed_at');
            }
            if (! Schema::hasColumn('ticketing_tickets', 'sla_reminder_sent_at')) {
                $table->timestamp('sla_reminder_sent_at')->nullable()->after('sla_due_at');
            }
            if (! Schema::hasColumn('ticketing_tickets', 'sla_escalated_at')) {
                $table->timestamp('sla_escalated_at')->nullable()->after('sla_reminder_sent_at');
            }
            if (! Schema::hasColumn('ticketing_tickets', 'sla_status')) {
                $table->string('sla_status', 16)->nullable()->after('sla_escalated_at');
            }
        });
    }

    public function down(): void
    {
        // Non-destructive repair — do not drop tables on rollback.
    }
};
