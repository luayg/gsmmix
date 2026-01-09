<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // تحقّق من وجود الإندكس قبل الإضافة
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'users')
            ->where('index_name', 'users_email_unique')
            ->exists();

        if (! $exists) {
            Schema::table('users', function (Blueprint $t) {
                $t->unique('email'); // غادي يتزاد غير إذا ماكانش
            });
        }

        // زيد أي أعمدة أخرى لكن دير نفس التحقق بإسم الإندكس ديالها
    }

    public function down(): void
    {
        // إسقاط الإندكس إذا كان موجود
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'users')
            ->where('index_name', 'users_email_unique')
            ->exists();

        if ($exists) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropUnique('users_email_unique');
            });
        }
    }
};
