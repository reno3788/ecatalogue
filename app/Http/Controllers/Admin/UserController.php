<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['company', 'roles'])->latest()->get();
        $companies = Company::all();
        $roles = Role::all();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'companies' => $companies,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'company_id' => 'nullable|exists:companies,id',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'company_id' => $request->company_id,
        ]);

        $user->assignRole($request->role);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'role' => 'required|string',
        ]);

        $user->update([
            'company_id' => $request->company_id,
        ]);

        $user->syncRoles([$request->role]);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        // Prevent deleting self
        if (auth()->id() === $user->id) {
            return redirect()->back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}
