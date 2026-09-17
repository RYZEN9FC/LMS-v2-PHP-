<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->string('source', 10);
            $table->string('document_key', 64);
            $table->string('content_hash', 64);
            $table->string('file_name');
            $table->date('effective_from');
            $table->date('effective_to');
            $table->string('granularity', 10);
            $table->json('metadata');
            $table->timestamps();
            $table->unique(['outlet_id', 'source', 'document_key']);
        });
        Schema::create('source_mappings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->string('source', 10);
            $table->string('source_key', 64);
            $table->string('source_name');
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('rules');
            $table->timestamps();
            $table->unique(['outlet_id', 'source', 'source_key']);
        });
        Schema::create('upload_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('document_id')->constrained('upload_documents')->cascadeOnDelete();
            $table->string('row_key', 64);
            $table->string('source_key', 64);
            $table->json('payload');
            $table->json('mapping')->nullable();
            $table->foreignId('applied_import_id')->nullable()->constrained('imports')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'row_key']);
        });
        Schema::create('mapping_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_mapping_id')->constrained('source_mappings')->restrictOnDelete();
            $table->json('before_rules')->nullable();
            $table->json('after_rules');
            $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->unique(['outlet_id', 'name', 'bottle_size_ml'], 'products_name_size_unique');
            $table->dropUnique(['outlet_id', 'name']);
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('sale_type', 30)->nullable()->change();
        });
        Schema::table('import_lines', function (Blueprint $table) {
            $table->string('sale_type', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapping_changes');
        Schema::dropIfExists('upload_rows');
        Schema::dropIfExists('source_mappings');
        Schema::dropIfExists('upload_documents');
        // Keep the wider sale-type and product-size indexes: narrowing them may destroy valid data.
    }
};
