<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Module 14: Document Management (e-Docs).
     * Supports PostgreSQL (`document` schema) and SQLite (testing fallback).
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS document;');
        }

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';
        $organizationsTable = $isPgsql ? 'core.organizations' : 'organizations';

        $categoriesTable = $isPgsql ? 'document.document_categories' : 'document_categories';
        $documentsTable = $isPgsql ? 'document.documents' : 'documents';
        $versionsTable = $isPgsql ? 'document.document_versions' : 'document_versions';
        $accessLogsTable = $isPgsql ? 'document.document_access_logs' : 'document_access_logs';

        // 1. Configurable Document Categories
        Schema::create($categoriesTable, function (Blueprint $table) use ($usersTable) {
            $table->uuid('id')->primary();
            $table->string('code', 60)->unique();
            $table->string('name_en', 150);
            $table->string('name_bn', 200);
            $table->text('description_en')->nullable();
            $table->text('description_bn')->nullable();
            $table->string('default_classification', 30)->default('INTERNAL')
                ->comment('PUBLIC, INTERNAL, CONFIDENTIAL, RESTRICTED');
            $table->json('allowed_formats')->nullable();
            $table->unsignedInteger('retention_days')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_system')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 2. Centralized Sovereign Document Registry
        Schema::create($documentsTable, function (Blueprint $table) use (
            $categoriesTable,
            $organizationsTable,
            $adminUnitsTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->string('document_no', 60)->unique();
            $table->string('reference_no', 120)->nullable()->index();
            $table->uuid('category_id')->index();
            $table->string('document_type', 60)->default('OTHER')->index();
            $table->string('title_bn', 300);
            $table->string('title_en', 300);
            $table->text('description_bn')->nullable();
            $table->text('description_en')->nullable();
            $table->string('issuing_organization', 200)->nullable()->index();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->uuid('owner_user_id')->nullable()->index();
            $table->uuid('uploaded_by')->nullable()->index();
            $table->timestamp('uploaded_at')->nullable()->index();
            $table->date('effective_date')->nullable();
            $table->timestamp('expires_at')->nullable()->index();

            // Cross-module entity integration
            $table->string('module_code', 40)->default('GENERAL')->index();
            $table->string('entity_type', 80)->nullable()->index();
            $table->uuid('entity_id')->nullable()->index();
            $table->string('entity_reference', 120)->nullable()->index();

            // Classification, tags & approval lifecycle
            $table->string('classification', 30)->default('INTERNAL')->index()
                ->comment('PUBLIC, INTERNAL, CONFIDENTIAL, RESTRICTED');
            $table->json('tags')->nullable();
            $table->string('approval_status', 30)->default('DRAFT')->index()
                ->comment('DRAFT, PENDING_APPROVAL, APPROVED, REJECTED, ARCHIVED');
            $table->uuid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Current active file version metadata
            $table->unsignedInteger('current_version_number')->default(1);
            $table->uuid('current_version_id')->nullable()->index();
            $table->string('original_filename', 255);
            $table->string('storage_disk', 40)->default('documents_private');
            $table->string('storage_path', 500);
            $table->string('mime_type', 120)->index();
            $table->string('file_extension', 20)->index();
            $table->unsignedBigInteger('file_size_bytes');
            $table->char('file_hash_sha256', 64)->index();
            $table->string('scan_status', 30)->default('PENDING')->index()
                ->comment('PENDING, CLEAN, INFECTED, FAILED');
            $table->timestamp('scanned_at')->nullable();
            $table->string('scan_engine', 60)->nullable();
            $table->text('scan_details')->nullable();
            $table->boolean('is_confidential')->default(false)->index();
            $table->json('metadata')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entity_type', 'entity_id'], 'idx_doc_entity_lookup');
            $table->index(['module_code', 'approval_status'], 'idx_doc_module_status');
            $table->index(['expires_at', 'approval_status'], 'idx_doc_expiry_status');

            $table->foreign('category_id')->references('id')->on($categoriesTable)->onDelete('restrict');
            $table->foreign('organization_id')->references('id')->on($organizationsTable)->onDelete('set null');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('owner_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('uploaded_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 3. Immutable Document Version History
        Schema::create($versionsTable, function (Blueprint $table) use ($documentsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('document_id')->index();
            $table->unsignedInteger('version_number');
            $table->string('version_label', 30)->nullable();
            $table->text('change_notes')->nullable();
            $table->string('original_filename', 255);
            $table->string('storage_disk', 40)->default('documents_private');
            $table->string('storage_path', 500);
            $table->string('mime_type', 120);
            $table->string('file_extension', 20);
            $table->unsignedBigInteger('file_size_bytes');
            $table->char('file_hash_sha256', 64)->index();
            $table->string('scan_status', 30)->default('PENDING')->index();
            $table->timestamp('scanned_at')->nullable();
            $table->string('scan_engine', 60)->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->uuid('uploaded_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['document_id', 'version_number'], 'uq_doc_version_number');
            $table->foreign('document_id')->references('id')->on($documentsTable)->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 4. Document Access & Modification History
        Schema::create($accessLogsTable, function (Blueprint $table) use (
            $documentsTable,
            $versionsTable,
            $usersTable,
            $adminUnitsTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('document_id')->index();
            $table->uuid('document_version_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->string('action', 40)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->foreign('document_id')->references('id')->on($documentsTable)->onDelete('cascade');
            $table->foreign('document_version_id')->references('id')->on($versionsTable)->onDelete('set null');
            $table->foreign('user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        Schema::dropIfExists($isPgsql ? 'document.document_access_logs' : 'document_access_logs');
        Schema::dropIfExists($isPgsql ? 'document.document_versions' : 'document_versions');
        Schema::dropIfExists($isPgsql ? 'document.documents' : 'documents');
        Schema::dropIfExists($isPgsql ? 'document.document_categories' : 'document_categories');

        if ($isPgsql) {
            DB::statement('DROP SCHEMA IF EXISTS document CASCADE;');
        }
    }
};
