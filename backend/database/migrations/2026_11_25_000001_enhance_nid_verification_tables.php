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
        $nidVerificationsTable = $isPgsql ? 'beneficiary.nid_verifications' : 'nid_verifications';
        $attemptsTable = $isPgsql ? 'beneficiary.nid_verification_attempts' : 'nid_verification_attempts';

        Schema::table($nidVerificationsTable, function (Blueprint $table) use ($usersTable, $adminUnitsTable) {
            $table->uuid('administrative_unit_id')->nullable()->after('household_member_id')->index();
            $table->text('nid_encrypted_payload')->nullable()->after('nid_masked');
            $table->string('submitted_name_en', 150)->nullable()->after('date_of_birth');
            $table->string('verification_reference', 100)->nullable()->unique()->after('verification_status');
            $table->boolean('is_mock')->default(true)->after('provider_code')->index();
            $table->json('matched_fields')->nullable()->after('response_payload_hash');
            $table->integer('attempt_count')->default(0)->after('matched_fields');
            $table->string('last_error_code', 50)->nullable()->after('attempt_count')->index();
            $table->text('last_error_message')->nullable()->after('last_error_code');
            $table->boolean('manual_override')->default(false)->after('last_error_message')->index();
            $table->text('override_reason')->nullable()->after('manual_override');
            $table->string('previous_status', 30)->nullable()->after('override_reason');
            $table->uuid('requested_by_user_id')->nullable()->after('previous_status')->index();
            $table->uuid('reviewed_by_user_id')->nullable()->after('verified_by_user_id')->index();
            $table->timestamp('reviewed_at')->nullable()->after('verified_at');
            $table->softDeletes();

            $table->foreign('administrative_unit_id')
                ->references('id')
                ->on($adminUnitsTable)
                ->onDelete('set null');

            $table->foreign('requested_by_user_id')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');

            $table->foreign('reviewed_by_user_id')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');
        });

        Schema::create($attemptsTable, function (Blueprint $table) use ($nidVerificationsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('nid_verification_id')->index();
            $table->integer('attempt_number')->default(1);
            $table->string('provider_code', 50)->index();
            $table->boolean('is_mock')->default(true);
            $table->string('status', 30)->index()->comment('VERIFIED, FAILED, MANUAL_REVIEW_REQUIRED, TIMEOUT, ERROR');
            $table->integer('http_status')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->string('error_code', 50)->nullable()->index();
            $table->text('error_message')->nullable();
            $table->uuid('attempted_by_user_id')->nullable()->index();
            $table->timestamp('attempted_at')->index();
            $table->timestamps();

            $table->foreign('nid_verification_id')
                ->references('id')
                ->on($nidVerificationsTable)
                ->onDelete('cascade');

            $table->foreign('attempted_by_user_id')
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

        $nidVerificationsTable = $isPgsql ? 'beneficiary.nid_verifications' : 'nid_verifications';
        $attemptsTable = $isPgsql ? 'beneficiary.nid_verification_attempts' : 'nid_verification_attempts';

        Schema::dropIfExists($attemptsTable);

        Schema::table($nidVerificationsTable, function (Blueprint $table) {
            $table->dropForeign(['administrative_unit_id']);
            $table->dropForeign(['requested_by_user_id']);
            $table->dropForeign(['reviewed_by_user_id']);

            $table->dropColumn([
                'administrative_unit_id',
                'nid_encrypted_payload',
                'submitted_name_en',
                'verification_reference',
                'is_mock',
                'matched_fields',
                'attempt_count',
                'last_error_code',
                'last_error_message',
                'manual_override',
                'override_reason',
                'previous_status',
                'requested_by_user_id',
                'reviewed_by_user_id',
                'reviewed_at',
                'deleted_at',
            ]);
        });
    }
};
