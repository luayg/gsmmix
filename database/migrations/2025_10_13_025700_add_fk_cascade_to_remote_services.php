<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // وحّد نوع العمود مع api_providers.id (bigIncrements => unsignedBigInteger)
        Schema::table('remote_imei_services', function (Blueprint $t) {
            $t->unsignedBigInteger('api_id')->change();
        });
        Schema::table('remote_server_services', function (Blueprint $t) {
            $t->unsignedBigInteger('api_id')->change();
        });
        Schema::table('remote_file_services', function (Blueprint $t) {
            $t->unsignedBigInteger('api_id')->change();
        });

        // احذف أي صف يتيم قبل إضافة الـFK (DELETE مع LEFT JOIN أضمن من whereNotIn)
        DB::statement('
            DELETE ris FROM remote_imei_services ris
            LEFT JOIN api_providers ap ON ap.id = ris.api_id
            WHERE ap.id IS NULL
        ');
        DB::statement('
            DELETE rss FROM remote_server_services rss
            LEFT JOIN api_providers ap ON ap.id = rss.api_id
            WHERE ap.id IS NULL
        ');
        DB::statement('
            DELETE rfs FROM remote_file_services rfs
            LEFT JOIN api_providers ap ON ap.id = rfs.api_id
            WHERE ap.id IS NULL
        ');

        // أضف المفاتيح الأجنبية مع الحذف المتسلسل
        Schema::table('remote_imei_services', function (Blueprint $t) {
            $t->foreign('api_id', 'ris_api_fk')
              ->references('id')->on('api_providers')
              ->onDelete('cascade');
        });
        Schema::table('remote_server_services', function (Blueprint $t) {
            $t->foreign('api_id', 'rss_api_fk')
              ->references('id')->on('api_providers')
              ->onDelete('cascade');
        });
        Schema::table('remote_file_services', function (Blueprint $t) {
            $t->foreign('api_id', 'rfs_api_fk')
              ->references('id')->on('api_providers')
              ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('remote_imei_services', function (Blueprint $t) {
            $t->dropForeign('ris_api_fk');
        });
        Schema::table('remote_server_services', function (Blueprint $t) {
            $t->dropForeign('rss_api_fk');
        });
        Schema::table('remote_file_services', function (Blueprint $t) {
            $t->dropForeign('rfs_api_fk');
        });
    }
};
