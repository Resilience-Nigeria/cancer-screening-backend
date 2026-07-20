<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * ClientReferral has no $table override, so Eloquent's naming
     * convention expects `client_referrals` (snake_case plural), but the
     * original migration created `clientReferrals` (camelCase). On
     * case-sensitive hosting these are different tables — every
     * ClientReferral::create()/query has likely been failing, which
     * means ReferralService's referToMainHub()/referToTreatment() have
     * never actually persisted a referral record.
     */
    public function up(): void
    {
        if (Schema::hasTable('clientReferrals') && !Schema::hasTable('client_referrals')) {
            Schema::rename('clientReferrals', 'client_referrals');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client_referrals') && !Schema::hasTable('clientReferrals')) {
            Schema::rename('client_referrals', 'clientReferrals');
        }
    }
};
