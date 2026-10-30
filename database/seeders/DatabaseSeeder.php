<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Персонал: пароль з ADMIN_PASSWORD (на сервері — з GitHub Secrets)
        $password = config('vrosvita.admin_password');
        foreach ([
            ['admin@vr-osvita.ua', 'Суперадмін', Role::SuperAdmin],
            ['methodist@vr-osvita.ua', 'Олена Методист', Role::Manager],
            ['finance@vr-osvita.ua', 'Ігор Бухгалтер', Role::FinanceViewer],
        ] as [$email, $name, $role]) {
            User::updateOrCreate(['email' => $email], ['contact_person_name' => $name, 'role' => $role, 'password' => $password]);
        }

        if (! app()->environment('production') || config('vrosvita.seed_demo')) {
            $this->call(DemoSeeder::class);
        }
    }
}
