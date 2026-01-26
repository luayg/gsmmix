<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class UserResetPasswordCommand extends Command
{
    protected $signature = 'user:reset-password {login : email or username} {password : new password}';
    protected $description = 'Reset a user password by email or username';

    public function handle(): int
    {
        $login = (string) $this->argument('login');
        $password = (string) $this->argument('password');

        $user = User::where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (!$user) {
            $this->error("User not found: {$login}");
            return self::FAILURE;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->info("Password updated for user #{$user->id} ({$user->email})");
        return self::SUCCESS;
    }
}
