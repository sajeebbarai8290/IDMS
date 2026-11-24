<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';
        $disasterTable = $isPgsql ? 'disaster.disaster_events' : 'disaster_events';

        $householdsTable = $isPgsql ? 'beneficiary.households' : 'households';
        $membersTable = $isPgsql ? 'beneficiary.household_members' : 'household_members';
        $nidVerificationsTable = $isPgsql ? 'beneficiary.nid_verifications' : 'nid_verifications';
        $verificationsTable = $isPgsql ? 'beneficiary.household_verifications' : 'household_verifications';
        $eligibilitiesTable = $isPgsql ? 'beneficiary.disaster_eligibilities' : 'disaster_eligibilities';
        $duplicatesTable = $isPgsql ? 'beneficiary.duplicate_suspects' : 'duplicate_suspects';

        // 1. Households Master Table
        Schema::create($householdsTable, function (Blueprint $table) use ($usersTable, $adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->string('household_code', 30)->unique()->comment('e.g. HH-404712-00001');
            $table->uuid('administrative_unit_id')->index();
            $table->string('village_ward_name', 150)->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();

            // Head of Household (HoH) Demographics
            $table->string('hoh_name_en', 150)->index();
            $table->string('hoh_name_bn', 200);
            $table->char('hoh_nid_hash', 64)->unique()->comment('HMAC-SHA256 for O(1) deduplication');
            $table->text('hoh_nid_encrypted')->comment('AES-256-GCM authenticated cipher');
            $table->string('hoh_nid_masked', 30)->comment('Masked display e.g. 1990********12');
            $table->date('hoh_dob')->nullable()->index();
            $table->string('hoh_gender', 20)->default('MALE')->index();
            $table->char('hoh_mobile_hash', 64)->nullable()->index();
            $table->text('hoh_mobile_encrypted')->nullable();
            $table->string('hoh_mobile_masked', 30)->nullable();
            $table->string('hoh_occupation', 100)->nullable();
            $table->decimal('monthly_income_bdt', 12, 2)->default(0.00);
            $table->string('income_category', 30)->default('POOR')->index();
            $table->integer('family_member_count')->default(1);

            // Vulnerability Profile & Indicators
            $table->decimal('vulnerability_score', 5, 2)->default(0.00)->index()->comment('0.00 to 100.00');
            $table->boolean('is_female_headed')->default(false)->index();
            $table->boolean('has_elderly')->default(false)->index();
            $table->boolean('has_disabled')->default(false)->index();
            $table->boolean('has_children_infants')->default(false)->index();
            $table->boolean('has_pregnant_lactating')->default(false)->index();
            $table->boolean('is_displaced')->default(false)->index();
            $table->json('other_vulnerabilities')->nullable();

            // Geospatial Location
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Multi-Stage Verification Workflow
            $table->string('verification_status', 30)->default('DRAFT')->index()
                ->comment('DRAFT, PENDING_VERIFICATION, FIELD_VERIFIED, APPROVED, REJECTED');
            $table->boolean('nid_verified')->default(false)->index();
            $table->boolean('address_verified')->default(false)->index();
            $table->boolean('field_verified')->default(false)->index();

            // Evidence Files
            $table->text('photo_url')->nullable();
            $table->text('nid_document_url')->nullable();

            // Audit & Soft Deletes
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('administrative_unit_id')
                ->references('id')
                ->on($adminUnitsTable)
                ->onDelete('restrict');

            $table->foreign('created_by')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$householdsTable} ADD COLUMN dwelling_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_households_dwelling_gist ON {$householdsTable} USING GIST (dwelling_geom);");
        }

        // 2. Household Members Table
        Schema::create($membersTable, function (Blueprint $table) use ($householdsTable) {
            $table->uuid('id')->primary();
            $table->uuid('household_id')->index();
            $table->string('full_name_en', 150)->index();
            $table->string('full_name_bn', 200)->nullable();
            $table->string('relationship_to_hoh', 50)->index()->comment('SPOUSE, CHILD, PARENT, SIBLING, OTHER');
            $table->string('gender', 20)->default('MALE')->index();
            $table->date('date_of_birth')->nullable();
            $table->integer('age')->nullable()->index();
            $table->char('nid_hash', 64)->nullable()->index();
            $table->text('nid_encrypted')->nullable();
            $table->string('nid_masked', 30)->nullable();
            $table->boolean('has_disability')->default(false)->index();
            $table->string('disability_type', 50)->nullable()->comment('PHYSICAL, VISUAL, HEARING, INTELLECTUAL, MULTIPLE');
            $table->boolean('is_pregnant_lactating')->default(false)->index();
            $table->string('occupation', 100)->nullable();
            $table->timestamps();

            $table->foreign('household_id')
                ->references('id')
                ->on($householdsTable)
                ->onDelete('cascade');
        });

        // 3. National ID Verification Log Table
        Schema::create($nidVerificationsTable, function (Blueprint $table) use ($householdsTable, $membersTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('household_id')->nullable()->index();
            $table->uuid('household_member_id')->nullable()->index();
            $table->char('nid_hash', 64)->index();
            $table->string('nid_masked', 30)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('verification_status', 30)->index()->comment('VERIFIED, FAILED, NOT_FOUND, DECEASED, TIMEOUT');
            $table->string('verification_token', 255)->nullable();
            $table->string('provider_code', 50)->default('MOCK_ADAPTER');
            $table->char('response_payload_hash', 64)->nullable();
            $table->uuid('verified_by_user_id')->nullable()->index();
            $table->timestamp('verified_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('household_id')
                ->references('id')
                ->on($householdsTable)
                ->onDelete('set null');

            $table->foreign('household_member_id')
                ->references('id')
                ->on($membersTable)
                ->onDelete('set null');

            $table->foreign('verified_by_user_id')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');
        });

        // 4. Household Verification Steps & Approval Trail
        Schema::create($verificationsTable, function (Blueprint $table) use ($householdsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('household_id')->index();
            $table->string('stage', 50)->index()->comment('NID_CHECK, ADDRESS_CHECK, FIELD_SURVEY, COMMITTEE_APPROVAL');
            $table->string('status', 30)->index()->comment('PENDING, VERIFIED, REJECTED');
            $table->text('remarks')->nullable();
            $table->text('evidence_document_path')->nullable();
            $table->uuid('verified_by')->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('household_id')
                ->references('id')
                ->on($householdsTable)
                ->onDelete('cascade');

            $table->foreign('verified_by')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');
        });

        // 5. Disaster Eligibility Linking Table
        Schema::create($eligibilitiesTable, function (Blueprint $table) use ($householdsTable, $disasterTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('household_id')->index();
            $table->uuid('disaster_event_id')->index();
            $table->string('eligibility_status', 30)->default('ELIGIBLE')->index()
                ->comment('ELIGIBLE, INELIGIBLE, ENROLLED, ASSISTANCE_DISBURSED');
            $table->text('eligibility_criteria')->nullable();
            $table->integer('assistance_count')->default(0);
            $table->timestamp('last_assistance_at')->nullable();
            $table->boolean('is_blacklisted')->default(false)->index();
            $table->text('blacklist_reason')->nullable();
            $table->uuid('enrolled_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['household_id', 'disaster_event_id'], 'uq_household_disaster');

            $table->foreign('household_id')
                ->references('id')
                ->on($householdsTable)
                ->onDelete('cascade');

            $table->foreign('disaster_event_id')
                ->references('id')
                ->on($disasterTable)
                ->onDelete('restrict');

            $table->foreign('enrolled_by')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');
        });

        // 6. Duplicate Detection Suspects Table
        Schema::create($duplicatesTable, function (Blueprint $table) use ($householdsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('household_id')->index();
            $table->uuid('matched_household_id')->index();
            $table->string('match_type', 50)->index()
                ->comment('NID_EXACT, MOBILE_MATCH, NAME_DOB_FUZZY, ADDRESS_COORDINATE_PROXIMITY');
            $table->decimal('confidence_score', 5, 2)->comment('0.00 to 100.00');
            $table->string('status', 30)->default('PENDING_REVIEW')->index()
                ->comment('PENDING_REVIEW, CONFIRMED_DUPLICATE, FALSE_POSITIVE_RESOLVED');
            $table->uuid('reviewed_by')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->foreign('household_id')
                ->references('id')
                ->on($householdsTable)
                ->onDelete('cascade');

            $table->foreign('matched_household_id')
                ->references('id')
                ->on($householdsTable)
                ->onDelete('cascade');

            $table->foreign('reviewed_by')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $duplicatesTable = $isPgsql ? 'beneficiary.duplicate_suspects' : 'duplicate_suspects';
        $eligibilitiesTable = $isPgsql ? 'beneficiary.disaster_eligibilities' : 'disaster_eligibilities';
        $verificationsTable = $isPgsql ? 'beneficiary.household_verifications' : 'household_verifications';
        $nidVerificationsTable = $isPgsql ? 'beneficiary.nid_verifications' : 'nid_verifications';
        $membersTable = $isPgsql ? 'beneficiary.household_members' : 'household_members';
        $householdsTable = $isPgsql ? 'beneficiary.households' : 'households';

        Schema::dropIfExists($duplicatesTable);
        Schema::dropIfExists($eligibilitiesTable);
        Schema::dropIfExists($verificationsTable);
        Schema::dropIfExists($nidVerificationsTable);
        Schema::dropIfExists($membersTable);
        Schema::dropIfExists($householdsTable);
    }
};
