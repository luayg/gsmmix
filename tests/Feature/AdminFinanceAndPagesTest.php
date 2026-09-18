<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Page;
use App\Models\PageTheme;
use App\Models\Menu;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecurityTestCase;

class AdminFinanceAndPagesTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('users', fn(Blueprint $t)=>$t->decimal('balance',14,4)->default(0));
        (require database_path('migrations/2025_10_06_000000_create_finances_tables.php'))->up();
        Schema::create('settings', function(Blueprint $t){ $t->id(); $t->string('setting_key')->unique(); $t->string('group_name'); $t->text('value')->nullable(); $t->string('value_type')->default('string'); $t->boolean('is_encrypted')->default(false); $t->timestamps(); });
        (require database_path('migrations/2026_09_16_000200_create_languages_and_currencies_tables.php'))->up();
        Schema::create('payment_transactions', function(Blueprint $t){ $t->id(); });
        (require database_path('migrations/2026_09_16_000500_create_invoices_and_content_pages.php'))->up();
        (require database_path('migrations/2026_09_19_000100_add_page_themes_and_styles.php'))->up();
        (require database_path('migrations/2026_09_19_000300_seed_default_header_menu.php'))->up();
    }

    public function test_transaction_filters_statement_and_exports_are_read_only(): void
    {
        $admin=$this->user('Administrator'); $admin->update(['balance'=>'75.0000']);
        DB::table('finance_accounts')->insert(['user_id'=>$admin->id,'locked_amount'=>0,'total_receipts'=>100,'paid_credits'=>25,'overdraft_limit'=>0]);
        DB::table('finance_transactions')->insert(['user_id'=>$admin->id,'kind'=>'payment','direction'=>'income','paid'=>1,'amount'=>25,'reference'=>'PAY-100','balance_before'=>50,'balance_after'=>75,'created_at'=>now(),'updated_at'=>now()]);
        $this->actingAs($admin);
        $this->get(route('admin.finances.transactions.index',['q'=>'PAY-100']))->assertOk()->assertSee('PAY-100');
        $this->get(route('admin.finances.transactions.show',1))->assertOk()->assertSee('Balance before')->assertSee('50.0000');
        $this->get(route('admin.finances.transactions.export'))->assertOk()->assertDownload();
        $this->get(route('admin.finances.statements.show',$admin))->assertOk()->assertSee('PAY-100')->assertSee('Credits');
        $this->assertDatabaseCount('finance_transactions',1);
    }

    public function test_invoice_totals_snapshots_and_issued_record_guards(): void
    {
        $admin=$this->user('Administrator'); $customer=$this->user(); $this->actingAs($admin);
        $response=$this->post(route('admin.finances.invoices.store'),['user_id'=>$customer->id,'status'=>'pending','currency_code'=>'USD','exchange_rate'=>'1','issued_at'=>'2026-09-16','due_at'=>'2026-09-30','discount_total'=>'2','fee_total'=>'1','items'=>[['description'=>'Service','quantity'=>'2','unit_price'=>'10','discount'=>'1','tax_rate'=>'10']]]);
        $invoice=Invoice::firstOrFail(); $response->assertRedirect(route('admin.finances.invoices.show',$invoice));
        $this->assertSame('pending',$invoice->status); $this->assertSame($customer->email,$invoice->customer_snapshot['email']); $this->assertSame('20.9000',$invoice->total);
        $this->get(route('admin.finances.invoices.show',$invoice))->assertOk()->assertSee($invoice->number)->assertSee('20.9000');
        $this->post(route('admin.finances.invoices.payments.store',$invoice),['amount'=>'20.9','reference'=>'BANK-1','paid_at'=>'2026-09-16 10:00:00'])->assertRedirect();
        $this->assertSame('paid',$invoice->fresh()->status);
        $this->get(route('admin.finances.invoices.edit',$invoice))->assertStatus(409);
        $this->delete(route('admin.finances.invoices.destroy',$invoice))->assertStatus(409);
    }

    public function test_multilingual_pages_are_sanitized_and_system_pages_are_protected(): void
    {
        $admin=$this->user('Administrator'); $this->actingAs($admin); $language=DB::table('languages')->first();
        $response=$this->post(route('admin.pages.store'),['slug'=>'about-us','status'=>'published','placement'=>'footer','ordering'=>2,'translations'=>[['language_id'=>$language->id,'title'=>'About','content'=>'<p onclick="bad()">Safe</p><script>alert(1)</script>','seo_title'=>'About us','seo_description'=>'Company profile']]]);
        $page=Page::firstOrFail(); $response->assertRedirect(route('admin.pages.edit',$page));
        $this->assertStringNotContainsString('script',$page->translations()->first()->content); $this->assertStringNotContainsString('onclick',$page->translations()->first()->content);
        $this->followingRedirects()->get(route('admin.pages.preview',$page))->assertOk()->assertSee('Safe');
        $page->update(['system'=>true]); $this->delete(route('admin.pages.destroy',$page))->assertSessionHasErrors('page');
    }

    public function test_page_editor_and_finance_mutations_require_separate_permissions(): void
    {
        $staff=$this->user(); $staff->givePermissionTo(['admin.access','pages.view','finances.view']); $this->actingAs($staff);
        $this->get(route('admin.pages.index'))->assertOk(); $this->get(route('admin.finances.invoices.index'))->assertOk();
        $this->post(route('admin.pages.store'),[])->assertForbidden(); $this->post(route('admin.finances.invoices.store'),[])->assertForbidden();
    }

    public function test_core_page_content_is_locked_and_saved_themes_can_be_activated(): void
    {
        $admin=$this->user('Administrator'); $this->actingAs($admin); $language=DB::table('languages')->first();
        $page=Page::create(['slug'=>'home','status'=>'published','placement'=>'home','system'=>true,'ordering'=>1]);
        $page->translations()->create(['language_id'=>$language->id,'title'=>'Original home','content'=>'<p>Original</p>']);
        $this->put(route('admin.pages.update',$page),['status'=>'published','style_background'=>'#112233','style_text'=>'#ffffff','style_accent'=>'#00ccff','style_font_size'=>18,'style_heading_scale'=>1.2,'style_content_width'=>1280,'translations'=>[['language_id'=>$language->id,'title'=>'Changed','content'=>'Changed']]])->assertRedirect();
        $this->assertSame('Original home',$page->translations()->first()->title); $this->assertSame('#112233',$page->fresh()->style['background']);
        $one=PageTheme::create(['name'=>'One','active'=>true,'settings'=>['primary'=>'#111111']]); $two=PageTheme::create(['name'=>'Two','active'=>false,'settings'=>['primary'=>'#222222']]);
        $this->post(route('admin.pages.themes.activate',$two))->assertRedirect();
        $this->assertFalse($one->fresh()->active); $this->assertTrue($two->fresh()->active);
        $this->delete(route('admin.pages.themes.destroy',$two))->assertSessionHasErrors('theme');
        $this->post(route('admin.pages.themes.restore'))->assertRedirect()->assertSessionHas('ok');
        $this->assertFalse($two->fresh()->active); $this->assertSame(2,PageTheme::count());
    }

    public function test_privacy_and_terms_keep_their_routes_but_allow_safe_content_and_access_updates(): void
    {
        $admin=$this->user('Administrator'); $this->actingAs($admin); $language=DB::table('languages')->first();
        $page=Page::create(['slug'=>'privacy','status'=>'published','placement'=>'footer','system'=>true,'ordering'=>80]);
        $page->translations()->create(['language_id'=>$language->id,'title'=>'Privacy policy','content'=>'Old']);
        $this->put(route('admin.pages.update',$page),[
            'status'=>'published','authenticated_only'=>'1','open_new_window'=>'0',
            'translations'=>[['language_id'=>$language->id,'title'=>'Privacy & data','content'=>'<h2>Safe</h2><script>bad()</script>','seo_title'=>'Privacy','seo_description'=>'Privacy information']],
        ])->assertRedirect()->assertSessionHas('ok');
        $page->refresh();
        $this->assertSame('privacy',$page->slug); $this->assertSame('footer',$page->placement); $this->assertTrue($page->authenticated_only);
        $this->assertSame('Privacy & data',$page->translations()->first()->title);
        $this->assertStringNotContainsString('script',$page->translations()->first()->content);
    }

    public function test_main_menu_can_be_reordered_into_a_safe_tree(): void
    {
        $admin=$this->user('Administrator'); $this->actingAs($admin);
        $menu=Menu::where('location','header-primary')->firstOrFail();
        $parent=$menu->items()->create(['label'=>'Services','ordering'=>10]);
        $child=$menu->items()->create(['label'=>'IMEI','url'=>'/imei','ordering'=>20]);
        $this->postJson(route('admin.pages.menus.reorder',$menu),['items'=>[
            ['id'=>$parent->id,'parent_id'=>null,'ordering'=>10],
            ['id'=>$child->id,'parent_id'=>$parent->id,'ordering'=>10],
        ]])->assertOk()->assertJson(['ok'=>true]);
        $this->assertSame($parent->id,$child->fresh()->parent_id);
        $this->postJson(route('admin.pages.menus.reorder',$menu),['items'=>[
            ['id'=>$parent->id,'parent_id'=>$child->id,'ordering'=>10],
            ['id'=>$child->id,'parent_id'=>$parent->id,'ordering'=>10],
        ]])->assertStatus(422);
    }

    public function test_published_pages_can_be_added_to_menu_without_sending_a_label(): void
    {
        $admin=$this->user('Administrator'); $this->actingAs($admin);
        $language=DB::table('languages')->first();
        $menu=Menu::where('location','header-primary')->firstOrFail();

        $privacy=Page::create(['slug'=>'privacy','status'=>'published','placement'=>'footer','system'=>true,'ordering'=>80]);
        $privacy->translations()->create(['language_id'=>$language->id,'title'=>'Privacy Policy','content'=>'Privacy']);
        $terms=Page::create(['slug'=>'terms','status'=>'published','placement'=>'footer','system'=>true,'ordering'=>90]);
        $terms->translations()->create(['language_id'=>$language->id,'title'=>'Terms & Conditions','content'=>'Terms']);
        $withoutTranslation=Page::create(['slug'=>'shipping-policy','status'=>'published','placement'=>'footer','ordering'=>100]);

        foreach([$privacy,$terms,$withoutTranslation] as $page){
            $this->post(route('admin.pages.menus.items.store',$menu),['page_id'=>$page->id])
                ->assertRedirect()
                ->assertSessionHas('ok');
        }

        $this->assertDatabaseHas('menu_items',['menu_id'=>$menu->id,'page_id'=>$privacy->id,'label'=>'Privacy Policy','url'=>null]);
        $this->assertDatabaseHas('menu_items',['menu_id'=>$menu->id,'page_id'=>$terms->id,'label'=>'Terms & Conditions','url'=>null]);
        $this->assertDatabaseHas('menu_items',['menu_id'=>$menu->id,'page_id'=>$withoutTranslation->id,'label'=>'Shipping Policy','url'=>null]);
    }

    public function test_custom_links_and_label_only_branches_can_be_added_to_menu(): void
    {
        $admin=$this->user('Administrator'); $this->actingAs($admin);
        $menu=Menu::where('location','header-primary')->firstOrFail();

        $this->post(route('admin.pages.menus.items.store',$menu),['label'=>'Company'])
            ->assertRedirect()->assertSessionHas('ok');
        $this->post(route('admin.pages.menus.items.store',$menu),['label'=>'Support','url'=>'/support'])
            ->assertRedirect()->assertSessionHas('ok');

        $this->assertDatabaseHas('menu_items',['menu_id'=>$menu->id,'label'=>'Company','url'=>null]);
        $this->assertDatabaseHas('menu_items',['menu_id'=>$menu->id,'label'=>'Support','url'=>'/support']);
    }

}
