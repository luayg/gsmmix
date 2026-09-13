<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\RbacSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GrantAdminAccess extends Command
{
    use ConfirmableTrait;

    protected $signature = 'admin:grant {user_id : ID of an existing active user} {--force : Skip the production confirmation}';
    protected $description = 'Grant administrative access to an explicitly selected existing active user.';

    public function handle(): int
    {
        $id = (string) $this->argument('user_id');
        if (!ctype_digit($id) || (int) $id < 1) {
            $this->error('Provide a positive numeric user ID. No account was changed.');
            return self::FAILURE;
        }
        if (!$this->confirmToProceed('This grants full administrative access to the selected account.')) {
            return self::FAILURE;
        }

        $granted = DB::transaction(function () use ($id): bool {
            $user = User::query()->lockForUpdate()->find($id);
            if (!$user || $user->status !== 'active') {
                return false;
            }
            (new RbacSeeder)->run();
            $user->assignRole(AdminPermissions::ADMIN_ROLE);
            return true;
        });

        if (!$granted) {
            $this->error('The selected user does not exist or is inactive. No account was changed.');
            return self::FAILURE;
        }

        Log::notice('Administrative access granted from console.', ['user_id' => (int) $id]);
        $this->info("Administrative access granted to user #{$id}. Existing roles and password were preserved.");
        return self::SUCCESS;
    }
}
