<?php

namespace App\Http\Controllers;

use App\Models\PbxConnection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index()
    {
        $users = User::orderBy('name')->paginate(15);
        $allConnections = PbxConnection::orderBy('name')->get();
        
        return view('users.index', compact('users', 'allConnections'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        $allConnections = PbxConnection::orderBy('name')->get();
        return view('users.create', compact('allConnections'));
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

        $user = User::create($validated);

        // Sync allowed PBX connections
        if ($request->has('allowed_pbx_ids')) {
            $user->pbxConnections()->sync($request->input('allowed_pbx_ids', []));
        }

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

        $allConnections = PbxConnection::orderBy('name')->get();
        return view('users.edit', compact('user', 'allConnections'));
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

        // Sync allowed PBX connections
        if ($request->has('allowed_pbx_ids')) {
            $user->pbxConnections()->sync($request->input('allowed_pbx_ids', []));
        }

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
     * @deprecated Templates removed, kept for backward compatibility.
     */
    public function getTemplatePermissions(Request $request)
    {
        return response()->json([]);
    }

    // ==========================================
    // API Methods (JSON responses for PBX index modal)
    // ==========================================

    /**
     * API: List all users with their permissions (JSON).
     */
    public function apiIndex()
    {
        $users = User::with('pbxConnections')->orderBy('name')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'role_display' => $user->getRoleDisplayName(),
                'is_admin' => $user->isAdmin(),
                'can_sync_calls' => (bool) $user->can_sync_calls,
                'can_edit_extensions' => (bool) $user->can_edit_extensions,
                'can_update_ips' => (bool) $user->can_update_ips,
                'can_edit_rates' => (bool) $user->can_edit_rates,
                'can_manage_pbx' => (bool) $user->can_manage_pbx,
                'can_export_pdf' => (bool) $user->can_export_pdf,
                'can_export_excel' => (bool) $user->can_export_excel,
                'can_view_charts' => (bool) $user->can_view_charts,
                'created_at' => $user->created_at->format('d/m/Y'),
                'is_current' => $user->id === auth()->id(),
                'allowed_pbx_ids' => $user->pbxConnections->pluck('id')->toArray(),
            ];
        });

        $allConnections = PbxConnection::orderBy('name')->get()->map(function ($conn) {
            return [
                'id' => $conn->id,
                'name' => $conn->name,
                'ip' => $conn->ip,
                'port' => $conn->port,
                'status' => $conn->status,
            ];
        });

        return response()->json([
            'users' => $users,
            'pbxConnections' => $allConnections,
        ]);
    }

    /**
     * API: Store a new user (JSON).
     */
    public function apiStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'role' => ['required', 'string', 'in:admin,supervisor,user'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['password'] = Hash::make($data['password']);

        $permissions = [
            'can_sync_calls', 'can_edit_extensions', 'can_update_ips',
            'can_edit_rates', 'can_manage_pbx', 'can_export_pdf',
            'can_export_excel', 'can_view_charts'
        ];

        foreach ($permissions as $perm) {
            $data[$perm] = $data['role'] === 'admin' ? true : (bool) $request->input($perm);
        }

        $user = User::create($data);

        // Sync allowed PBX connections
        if ($request->has('allowed_pbx_ids')) {
            $user->pbxConnections()->sync($request->input('allowed_pbx_ids', []));
        }

        return response()->json(['success' => true, 'message' => 'Usuario creado exitosamente.']);
    }

    /**
     * API: Update an existing user (JSON).
     */
    public function apiUpdate(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'No puedes editar tu propia cuenta desde aquí.'], 403);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['required', 'string', 'in:admin,supervisor,user'],
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['confirmed', Password::min(6)];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($request->filled('password')) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $permissions = [
            'can_sync_calls', 'can_edit_extensions', 'can_update_ips',
            'can_edit_rates', 'can_manage_pbx', 'can_export_pdf',
            'can_export_excel', 'can_view_charts'
        ];

        foreach ($permissions as $perm) {
            $data[$perm] = $data['role'] === 'admin' ? true : (bool) $request->input($perm);
        }

        $user->update($data);

        // Sync allowed PBX connections
        if ($request->has('allowed_pbx_ids')) {
            $user->pbxConnections()->sync($request->input('allowed_pbx_ids', []));
        }

        return response()->json(['success' => true, 'message' => 'Usuario actualizado exitosamente.']);
    }

    /**
     * API: Delete a user (JSON).
     */
    public function apiDestroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'No puedes eliminar tu propia cuenta.'], 403);
        }

        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return response()->json(['success' => false, 'message' => 'No puedes eliminar al último administrador.'], 403);
        }

        $user->delete();

        return response()->json(['success' => true, 'message' => 'Usuario eliminado exitosamente.']);
    }
}
