<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

/**
 * The image's displayed size, cached beside its placeholder so a template can
 * reserve the space without reading the file. Nullable: rows written by 1.0
 * have no size until regenerated, which imagery:warm does for them.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('bonniss26_imagery_placeholders', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable()->after('property');
            $table->unsignedInteger('height')->nullable()->after('width');
        });
    }

    public function down()
    {
        Schema::table('bonniss26_imagery_placeholders', function (Blueprint $table) {
            $table->dropColumn(['width', 'height']);
        });
    }
};
