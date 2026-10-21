<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin {--name= : Full name} {--email= : Login email}')]
#[Description('Create an administrator account (asks for the password so it never appears in shell history)')]
class CreateAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?: text('Full name', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $password = password('Password (at least 8 characters)', required: true);
        $confirmation = password('Confirm password', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'branch_id' => null,
            'locale' => 'en',
            'is_active' => true,
        ]);

        $this->components->info("Administrator {$email} created. You can now sign in.");

        return self::SUCCESS;
    }
}
