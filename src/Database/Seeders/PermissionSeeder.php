<?php

namespace Nawasara\News\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'news.article.view',
            'news.settings.view',
            'news.settings.update',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::where('name', 'developer')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}
