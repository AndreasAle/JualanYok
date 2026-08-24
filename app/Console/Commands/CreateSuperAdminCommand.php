<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'jualanyok:make-admin
        {--name= : Nama lengkap admin}
        {--email= : Alamat email admin}
        {--username= : Username admin}';

    protected $description = 'Membuat atau memperbarui akun super admin secara aman';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Nama admin')));
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Email admin'))));
        $username = strtolower(trim((string) ($this->option('username') ?: $this->ask('Username admin'))));
        $password = (string) $this->secret('Password admin (minimal 12 karakter)');
        $passwordConfirmation = (string) $this->secret('Ulangi password admin');

        $existing = User::where('email', $email)->first();

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($existing?->id),
            ],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($existing?->id),
            ],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], [
            'username.regex' => 'Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, dan tanda hubung.',
            'password.min' => 'Password wajib memiliki minimal 12 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $role = Role::where('slug', Role::SUPER_ADMIN)->first();

        if (! $role) {
            $this->components->error('Role super admin belum tersedia. Jalankan `php artisan db:seed --force` dahulu.');

            return self::FAILURE;
        }

        if ($existing && ! $this->confirm(
            "Akun {$existing->email} sudah ada. Perbarui profil, password, dan jadikan super admin?",
        )) {
            $this->components->warn('Tidak ada perubahan yang dibuat.');

            return self::SUCCESS;
        }

        $user = DB::transaction(function () use ($existing, $name, $email, $username, $password, $role): User {
            $user = $existing ?? new User;

            $user->fill([
                'name' => $name,
                'email' => $email,
                'username' => $username,
                'password' => $password,
            ]);

            $user->forceFill([
                'email_verified_at' => $user->email_verified_at ?? now(),
                'tos_accepted_at' => $user->tos_accepted_at ?? now(),
                'status' => 'active',
                'suspension_reason' => null,
            ])->save();

            $user->roles()->syncWithoutDetaching([$role->id]);

            return $user;
        });

        $this->newLine();
        $this->components->info("Super admin {$user->email} berhasil disimpan.");
        $this->line('Masuk melalui: '.rtrim((string) config('app.url'), '/').'/login');

        return self::SUCCESS;
    }
}
