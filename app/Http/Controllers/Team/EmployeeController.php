<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function index()
    {
        return view('team.employees.index', ['employees' => User::role(['admin', 'customer_support'])->with('roles')->latest()->paginate(20)]);
    }
    public function create() { return view('team.employees.form', ['employee' => new User]); }
    public function edit(User $employee)
    {
        abort_unless($employee->isTeamMember(), 404);
        return view('team.employees.form', compact('employee'));
    }
    public function store(Request $request) { return $this->save($request, new User); }
    public function update(Request $request, User $employee)
    {
        abort_unless($employee->isTeamMember(), 404);
        return $this->save($request, $employee);
    }
    private function save(Request $request, User $employee)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($employee->id)],
            'role' => ['required', Rule::in(['admin', 'customer_support'])],
            'is_active' => ['required', 'boolean'],
            'password' => [$employee->exists ? 'nullable' : 'required', 'confirmed', Password::min(12)],
        ]);
        if ($employee->id === $request->user()->id) {
            abort_unless($data['role'] === 'admin' && (bool) $data['is_active'], 422, __('You cannot disable or demote your own account.'));
        }
        DB::transaction(function () use ($employee, $data) {
            if ($employee->exists && $employee->hasRole('admin') && ($data['role'] !== 'admin' || !$data['is_active'])) {
                $admins = User::role('admin')->where('is_active', true)->lockForUpdate()->get();
                abort_if($employee->is_active && $admins->count() <= 1, 422, __('At least one active administrator is required.'));
            }
            // Only team accounts can be edited; public registration always creates sellers.
            $employee->fill(['name' => $data['name'], 'email' => $data['email']]);
            $employee->is_active = (bool) $data['is_active'];
            if (!empty($data['password'])) { $employee->password = $data['password']; }
            $employee->save();
            $employee->syncRoles([Role::findOrCreate($data['role'], 'web')]);
        });
        return redirect()->route('employees.index')->with('success', __('Employee saved.'));
    }
}
