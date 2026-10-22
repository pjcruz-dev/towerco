<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_approval_comments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('pin_page')->nullable()->after('attachment_id');
            $table->decimal('pin_x', 8, 6)->nullable()->after('pin_page');
            $table->decimal('pin_y', 8, 6)->nullable()->after('pin_x');
            $table->decimal('pin_w', 8, 6)->nullable()->after('pin_y');
            $table->decimal('pin_h', 8, 6)->nullable()->after('pin_w');
        });
    }

    public function down(): void
    {
        Schema::table('e_approval_comments', function (Blueprint $table): void {
            $table->dropColumn(['pin_page', 'pin_x', 'pin_y', 'pin_w', 'pin_h']);
        });
    }
};
