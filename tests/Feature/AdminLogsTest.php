<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\File;use Tests\Support\SecurityTestCase;
final class AdminLogsTest extends SecurityTestCase{
 protected function setUp():void{parent::setUp();(require database_path('migrations/2026_09_16_000700_create_audit_logs.php'))->up();}
 public function test_login_and_logout_are_recorded_without_raw_identity_or_ip():void{$user=$this->user('Administrator');$this->post('/login',['login'=>$user->email,'password'=>'A-strong-test-password-123!'])->assertRedirect();$row=DB::table('access_logs')->first();$this->assertTrue((bool)$row->successful);$this->assertNotSame($user->email,$row->identity_hash);$this->assertSame(64,strlen($row->ip_hash));$this->post('/logout')->assertRedirect(route('home'));$this->assertDatabaseHas('access_logs',['event'=>'logout','user_id'=>$user->id]);}
 public function test_admin_mutation_is_logged_and_secrets_are_redacted():void{$admin=$this->user('Administrator');$this->actingAs($admin);$this->post(route('admin.downloads.store'),['password'=>'hidden'])->assertSessionHasErrors();$row=DB::table('activity_logs')->latest('id')->first();$this->assertNotNull($row);$this->assertStringNotContainsString('hidden',(string)$row->changes);}
 public function test_log_pages_require_log_permission():void{$staff=$this->user();$staff->givePermissionTo(['admin.access','logs.view']);$this->actingAs($staff);$this->get(route('admin.logs.access'))->assertOk();$this->get(route('admin.logs.activity'))->assertOk();$this->get(route('admin.logs.error'))->assertOk();}
}
