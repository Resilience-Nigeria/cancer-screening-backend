<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - bloom_push_tokens: Expo push tokens of phones with the Bloom app (women — signed in or not — and health workers).
 * - bloom_posts: articles and announcements written on the admin dashboard and shown in the app (optionally pushed).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bloom_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 191)->unique();
            $table->string('app_role', 10)->index();              // woman | chw
            $table->unsignedBigInteger('account_id')->nullable()->index(); // bloom_accounts.id (woman, when signed in)
            $table->unsignedBigInteger('user_id')->nullable()->index();    // users.id (health worker)
            $table->string('platform', 10)->nullable();           // ios | android
            $table->string('state', 60)->nullable();              // for state-targeted posts (health worker's facility state)
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('disabled_at')->nullable()->index(); // set when Expo says the app was uninstalled
            $table->timestamps();
        });

        Schema::create('bloom_posts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 15)->default('article');       // article | announcement
            $table->string('title', 150);
            $table->string('summary', 300)->nullable();
            $table->longText('body')->nullable();
            $table->string('audience', 10)->default('all')->index(); // all | women | chw
            $table->string('state', 60)->nullable();              // only health workers in this state (null = everyone)
            $table->string('cover_path', 255)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('status', 12)->default('draft')->index(); // draft | published
            $table->boolean('pinned')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('pushed_at')->nullable();
            $table->unsignedInteger('push_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloom_posts');
        Schema::dropIfExists('bloom_push_tokens');
    }
};
