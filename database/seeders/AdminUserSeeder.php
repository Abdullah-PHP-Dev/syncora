<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
	public function run(): void
	{
		// 1. Create role first
		$role = Role::firstOrCreate([
			                            'name' => 'admin',
			                            'guard_name' => 'web',
		                            ]);

		// 2. Create user
		$user = User::firstOrCreate(
			['email' => 'admin@test.com'],
			[
				'name' => 'Admin',
				'password' => bcrypt('password'),
			]
		);

		// 3. Assign role
		$user->assignRole($role);
	}
}