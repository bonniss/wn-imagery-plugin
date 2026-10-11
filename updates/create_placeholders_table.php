<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bonniss26_imagery_placeholders', function (Blueprint $table) {
            $table->id();
            // sha1 hex; see Classes\CacheKey.
            $table->char('cache_key', 40)->unique();
            // Long enough for a deep media path. Not indexed: only the purge
            // command scans it.
            $table->string('source', 1024);
            $table->string('identity', 191)->default('');
            $table->string('mode', 64);
            $table->string('status', 16)->index();
            $table->string('kind', 16)->nullable();
            // TEXT, not VARCHAR: a blur placeholder at the 64px long-edge cap is
            // ~5KB of base64. The library caps long_edge precisely so this fits.
            $table->text('value')->nullable();
            $table->string('property', 64)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('retry_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bonniss26_imagery_placeholders');
    }
};
