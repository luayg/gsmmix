<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('download_categories',function(Blueprint $t){$t->id();$t->string('name');$t->text('description')->nullable();$t->boolean('active')->default(true)->index();$t->unsignedInteger('ordering')->default(0);$t->string('required_permission')->nullable();$t->timestamps();});
  Schema::create('downloads',function(Blueprint $t){$t->id();$t->foreignId('download_category_id')->nullable()->constrained()->nullOnDelete();$t->string('name');$t->text('description')->nullable();$t->enum('source_type',['file','external']);$t->string('storage_path')->nullable();$t->string('original_name')->nullable();$t->string('mime_type')->nullable();$t->unsignedBigInteger('size')->nullable();$t->text('external_url')->nullable();$t->string('version',50)->nullable();$t->boolean('active')->default(true)->index();$t->boolean('is_free')->default(true);$t->timestamp('expires_at')->nullable()->index();$t->unsignedBigInteger('download_count')->default(0);$t->timestamps();});
  Schema::create('download_group',function(Blueprint $t){$t->foreignId('download_id')->constrained()->cascadeOnDelete();$t->unsignedBigInteger('group_id');$t->primary(['download_id','group_id']);});
  Schema::create('download_user',function(Blueprint $t){$t->foreignId('download_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->primary(['download_id','user_id']);});
  Schema::create('download_logs',function(Blueprint $t){$t->id();$t->foreignId('download_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->string('ip_hash',64);$t->string('user_agent_hash',64)->nullable();$t->timestamp('downloaded_at');$t->index(['download_id','downloaded_at']);});
 }
 public function down(): void {Schema::dropIfExists('download_logs');Schema::dropIfExists('download_user');Schema::dropIfExists('download_group');Schema::dropIfExists('downloads');Schema::dropIfExists('download_categories');}
};
