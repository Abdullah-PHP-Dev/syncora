<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
	    app()[\Spatie\Permission\PermissionRegistrar::class]
		    ->forgetCachedPermissions();

	    $permissions = [

		    'user.view',
		    'user.create',
		    'user.edit',
		    'user.delete',

		    'ad.view',
		    'ad.create',
		    'ad.edit',
		    'ad.delete',
		    'ad.approve',
		    'ad.reject',

		    'role.view',
		    'role.create',
		    'role.edit',
		    'role.delete',
	    ];

	    foreach ($permissions as $permission) {
		    Permission::firstOrCreate([
			                              'name' => $permission
		                              ]);
	    }

	    $superAdmin = Role::firstOrCreate([
		                                      'name' => 'Super Admin'
	                                      ]);

	    $superAdmin->givePermissionTo(Permission::all());

	    $seller = Role::firstOrCreate([
		                                  'name' => 'Seller'
	                                  ]);

	    $seller->givePermissionTo([
		                              'ad.view',
		                              'ad.create',
		                              'ad.edit',
		                              'ad.delete'
	                              ]);
    }
}
