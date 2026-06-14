<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
	public function run(): void
	{
		$admin = User::updateOrCreate(
			[
				'email' => 'admin@syncro.com'
			],
			[
				'name'     => 'Super Admin',
				'password' => Hash::make('password123'), // change later
			]
		);

		// If using Spatie Roles (recommended)
		if (method_exists($admin, 'assignRole')) {
			$admin->assignRole('admin');
		}
	}
}