<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index()
    {
        $users = User::orderBy('name')->paginate(15);
        $roleTemplates = User::getRoleTemplates();
        
        return view('users.index', compact('users', 'roleTemplates'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        $roleTemplates = User::getRoleTemplates();
        return view('users.create', compact('roleTemplates'));
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'role' => ['required', 'string', 'in:admin,supervisor,user'],
            'can_sync_calls' => ['nullable', 'boolean'],
            'can_edit_extensions' => ['nullable', 'boolean'],
            'can_update_ips' => ['nullable', 'boolean'],
            'can_edit_rates' => ['nullable', 'boolean'],
            'can_manage_pbx' => ['nullable', 'boolean'],
            'can_export_pdf' => ['nullable', 'boolean'],
            'can_export_excel' => ['nullable', 'boolean'],
            'can_view_charts' => ['nullable', 'boolean'],
        ]);

        // Hash password
        $validated['password'] = Hash::make($validated['password']);

        // Convert checkbox values to boolean (inputs send '1' or '0')
        $permissions = [
            'can_sync_calls', 'can_edit_extensions', 'can_update_ips',
            'can_edit_rates', 'can_manage_pbx', 'can_export_pdf',
            'can_export_excel', 'can_view_charts'
        ];

        foreach ($permissions as $perm) {
            $validated[$perm] = $request->input($perm) == '1';
        }

        // If admin role, grant all permissions
        if ($validated['role'] === 'admin') {
            foreach ($permissions as $perm) {
                $validated[$perm] = true;
            }
        }

        User::create($validated);

        return redirect()->route('users.index')
            ->with('success', 'Usuario creado exitosamente.');
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(User $user)
    {
        // Prevent editing yourself through this interface
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('warning', 'Para editar tu propio perfil, usa la sección de perfil.');
        }

        $roleTemplates = User::getRoleTemplates();
        return view('users.edit', compact('user', 'roleTemplates'));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        // Prevent editing yourself
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('warning', 'Para editar tu propio perfil, usa la sección de perfil.');
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['required', 'string', 'in:admin,supervisor,user'],
            'can_sync_calls' => ['nullable', 'boolean'],
            'can_edit_extensions' => ['nullable', 'boolean'],
            'can_update_ips' => ['nullable', 'boolean'],
            'can_edit_rates' => ['nullable', 'boolean'],
            'can_manage_pbx' => ['nullable', 'boolean'],
            'can_export_pdf' => ['nullable', 'boolean'],
            'can_export_excel' => ['nullable', 'boolean'],
            'can_view_charts' => ['nullable', 'boolean'],
        ];

        // Only validate password if provided
        if ($request->filled('password')) {
            $rules['password'] = ['confirmed', Password::min(6)];
        }

        $validated = $request->validate($rules);

        // Hash password if provided
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Convert checkbox values to boolean (inputs send '1' or '0')
        $permissions = [
            'can_sync_calls', 'can_edit_extensions', 'can_update_ips',
            'can_edit_rates', 'can_manage_pbx', 'can_export_pdf',
            'can_export_excel', 'can_view_charts'
        ];

        foreach ($permissions as $perm) {
            $validated[$perm] = $request->input($perm) == '1';
        }

        // If admin role, grant all permissions
        if ($validated['role'] === 'admin') {
            foreach ($permissions as $perm) {
                $validated[$perm] = true;
            }
        }

        $user->update($validated);

        return redirect()->route('users.index')
            ->with('success', 'Usuario actualizado exitosamente.');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        // Prevent deleting the last admin
        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return redirect()->route('users.index')
                ->with('error', 'No puedes eliminar al último administrador.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario eliminado exitosamente.');
    }

    /**
     * Apply a role template to get permissions.
     */
    public function getTemplatePermissions(Request $request)
    {
        $template = $request->input('template');
        $templates = User::getRoleTemplates();

        if (isset($templates[$template])) {
            return response()->json($templates[$template]['permissions']);
        }

        return response()->json([]);
    }
}
