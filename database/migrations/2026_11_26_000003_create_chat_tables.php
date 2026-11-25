<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One-to-one chat between staff (navigators, nurses, admins) across facilities. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_conversations')) {
            Schema::create('chat_conversations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('userA')->index();   // lower user id
                $t->unsignedBigInteger('userB')->index();   // higher user id
                $t->timestamp('lastMessageAt')->nullable()->index();
                $t->timestamps();
                $t->unique(['userA', 'userB']);
            });
        }
        if (!Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('conversationId')->index();
                $t->unsignedBigInteger('senderId')->index();
                $t->text('body');
                $t->string('clientId')->nullable();          // optional: a client the message is about
                $t->string('clientMsgId', 64)->nullable();   // sent from a phone offline: stops duplicates on retry
                $t->timestamp('readAt')->nullable();
                $t->timestamps();
                $t->unique(['senderId', 'clientMsgId']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
