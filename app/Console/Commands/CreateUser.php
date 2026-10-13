<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    protected $signature = 'user:create {email} {--name=} {--role=viewer : admin or viewer}';

    protected $description = 'Create or update a user and set the role (admin or viewer)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $role = (string) $this->option('role');

        if (! in_array($role, [User::ROLE_ADMIN, User::ROLE_VIEWER], true)) {
            $this->error('Role must be "admin" or "viewer".');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (min 10 characters)');
        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', 'min:10']]
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);

        // Never remove the last admin.
        if ($user->exists && $user->isAdmin() && $role !== User::ROLE_ADMIN
            && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            $this->error('Refusing to demote the only admin.');

            return self::FAILURE;
        }

        $user->name = $this->option('name') ?: ($user->name ?: Str::before($email, '@'));
        $user->password = $password; // hashed by the model cast
        $user->role = $role;
        $user->save();

        $this->info(($user->wasRecentlyCreated ? 'Created ' : 'Updated ') . "{$user->email} as {$role}.");

        return self::SUCCESS;
    }
}
