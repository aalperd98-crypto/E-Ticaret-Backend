<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

#[Signature('make:admin {name? : Ad Soyad} {email? : E-posta}')]
#[Description('Verilen e-posta ile admin kullanıcı oluşturur ya da mevcut kullanıcıyı admin yapar')]
class MakeAdminCommand extends Command
{
    public function handle()
    {
        $name = $this->argument('name') ?? $this->ask('Ad Soyad');
        $email = $this->argument('email') ?? $this->ask('E-posta');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['name' => $name]);
            $user->assignRole($adminRole);

            $this->info("Kullanıcı güncellendi ve admin yapıldı: {$user->email}");

            return self::SUCCESS;
        }

        $password = Str::password(12);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $user->assignRole($adminRole);

        $this->info("Admin kullanıcı oluşturuldu: {$user->email}");
        $this->info("Şifre: {$password}");

        return self::SUCCESS;
    }
}
