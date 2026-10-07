<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * List all staff accounts and assigned roles.
     * Viewable by admin and owner.
     */
    public function index(): View
    {
        $users = User::with('roles')->orderBy('name')->get();
        $roles = Role::whereIn('name', ['owner', 'admin', 'cashier', 'kitchen', 'customer_display'])->get();
        $isOwner = Auth::user()?->hasRole('owner') ?? false;

        return view('admin.users.index', compact('users', 'roles', 'isOwner'));
    }

    /**
     * Create a new Staff account (strictly Owner ONLY).
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole('owner'), 403, 'Unauthorized. Staff accounts can only be provisioned by the Property Owner.');

        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:150|unique:users,email',
            'role'     => 'required|string|in:cashier,kitchen,admin,owner,customer_display',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$validated['role']]);

        return back()->with('success', "Staff account for {$user->name} ({$validated['role']}) created successfully!");
    }

    /**
     * Reset staff account password (strictly Owner ONLY).
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole('owner'), 403, 'Unauthorized. Password resets can only be performed by the Property Owner.');

        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', "Password for {$user->name} ({$user->email}) was reset successfully.");
    }

    /**
     * Delete / Deactivate staff account (strictly Owner ONLY).
     */
    public function destroy(User $user): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole('owner'), 403, 'Unauthorized. Account deactivation can only be performed by the Property Owner.');

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Security rule: You cannot delete your own active owner account.');
        }

        $userName = $user->name;
        $user->delete();

        return back()->with('success', "Staff account for {$userName} has been removed.");
    }
}
