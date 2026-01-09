<?php
// database/seeders/RbacSeeder.php
// {{-- [انسخ] --}}
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        // صلاحيات أساسية لوحداتنا الحالية
        $entities = ['users','groups','roles','permissions'];
        $actions  = ['view','create','edit','delete'];

        $allPerms = [];
        foreach ($entities as $e) {
            foreach ($actions as $a) {
                $allPerms[] = "{$e}.{$a}";
            }
        }

        // إنشاء الصلاحيات إن لم تكن موجودة
        foreach ($allPerms as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        // الأدوار
        $admin   = Role::findOrCreate('Administrator', 'web');
        $manager = Role::findOrCreate('Manager', 'web');
        $support = Role::findOrCreate('Support', 'web');
        $basic   = Role::findOrCreate('Basic', 'web');

        // إعطاء كل الصلاحيات للأدمن
        $admin->syncPermissions($allPerms);

        // المدير: مشاهدة + إنشاء/تعديل، بدون حذف
        $managerPerms = collect($allPerms)->filter(fn($p)=>!Str::endsWith($p,'.delete'))->values()->all();
        $manager->syncPermissions($managerPerms);

        // الدعم: مشاهدة فقط
        $supportPerms = collect($allPerms)->filter(fn($p)=>Str::endsWith($p,'.view'))->values()->all();
        $support->syncPermissions($supportPerms);

        // بيسك: لا شيء أو صلاحيات محدودة جدًا (اختر ما يلزم لاحقاً)
        $basic->syncPermissions([]);

        // ربط الأدمن بالمستخدم الأول/بريد معين
        $adminUser = User::query()
            ->where('id', 1)
            ->orWhere('email', 'admin@example.com')
            ->first();

        if ($adminUser) {
            $adminUser->syncRoles(['Administrator']);
        }
    }
}
