<!DOCTYPE html>
<html>
<head>
    <title>Seleccionar Central - {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: sans-serif; 
            background: #f0f2f5; 
            margin: 0; 
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 900px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .header h1 { margin: 0; color: #333; font-size: 1.5rem; }
        .header-right { display: flex; align-items: center; gap: 15px; }
        .user-info { color: #666; }
        .btn-logout { 
            background: none; 
            border: none; 
            color: #666; 
            cursor: pointer; 
            font-size: 0.9rem;
        }
        .btn-logout:hover { color: #333; }
        
        .page-title {
            text-align: center;
            margin-bottom: 30px;
        }
        .page-title h2 { color: #333; margin-bottom: 10px; }
        .page-title p { color: #666; margin: 0; }
        
        .btn-add {
            display: inline-block;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 15px;
        }
        .btn-add:hover { background: #218838; }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card.active { border: 2px solid #28a745; }
        
        .card-header {
            background: #343a40;
            color: white;
            padding: 15px;
        }
        .card-header h3 { margin: 0 0 5px 0; font-size: 1.1rem; }
        .card-header .ip { color: #adb5bd; font-family: monospace; font-size: 0.9rem; }
        .badge-active {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-left: 10px;
        }
        
        .card-body { padding: 15px; }
        .card-body .info { color: #666; font-size: 0.9rem; margin-bottom: 15px; }
        .card-body .info span { color: #333; }
        
        .btn-connect {
            display: block;
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            margin-bottom: 10px;
        }
        .btn-connect:hover { background: #0056b3; }
        
        .btn-disconnect {
            display: block;
            width: 100%;
            padding: 12px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        .btn-disconnect:hover { background: #5a6268; }
        
        .card-actions {
            display: flex;
            gap: 10px;
        }
        .btn-edit, .btn-delete {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-edit { background: #ffc107; color: #333; }
        .btn-edit:hover { background: #e0a800; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-delete:hover { background: #c82333; }
        .btn-delete:disabled { background: #ccc; cursor: not-allowed; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .empty-state i { font-size: 4rem; color: #ccc; margin-bottom: 20px; }
        .empty-state h3 { color: #666; margin-bottom: 10px; }
        .empty-state p { color: #999; }
        
        /* Modal */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal {
            background: white;
            border-radius: 8px;
            width: 100%;
            max-width: 450px;
            margin: 20px;
        }
        .modal-header {
            background: #343a40;
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
        }
        .modal-header h3 { margin: 0; }
        .modal-body { padding: 20px; }
        .modal-body label { 
            display: block; 
            margin-bottom: 5px; 
            color: #333;
            font-weight: 500;
        }
        .modal-body input[type="text"],
        .modal-body input[type="number"],
        .modal-body input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .modal-body .row {
            display: flex;
            gap: 15px;
        }
        .modal-body .row > div { flex: 1; }
        .modal-body .row > div:last-child { flex: 0 0 100px; }
        .modal-body .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .modal-body .checkbox-group input { width: auto; margin: 0; }
        .modal-body .hint { font-size: 0.8rem; color: #666; margin-top: -10px; margin-bottom: 15px; }
        .modal-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 0 0 8px 8px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-cancel {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-cancel:hover { background: #5a6268; }
        .btn-save {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-save:hover { background: #0056b3; }

        /* ===== User Management Modal ===== */
        .btn-users {
            display: inline-block;
            padding: 10px 20px;
            background: #6f42c1;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 15px;
            margin-left: 10px;
        }
        .btn-users:hover { background: #5a32a3; }
        .modal-users { max-width: 920px; }
        .users-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .users-table th {
            background: #f8f9fa; padding: 10px 12px; text-align: left;
            font-size: 0.8rem; text-transform: uppercase; color: #666;
            border-bottom: 2px solid #dee2e6;
        }
        .users-table td { padding: 10px 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .users-table tr:hover { background: #f8f9fa; }
        .user-avatar {
            display: inline-flex; align-items: center; justify-content: center;
            width: 35px; height: 35px; border-radius: 50%;
            color: white; font-weight: bold; font-size: 0.9rem; flex-shrink: 0;
        }
        .badge-role {
            display: inline-block; padding: 3px 10px; border-radius: 10px;
            font-size: 0.75rem; font-weight: 600;
        }
        .badge-perm {
            display: inline-block; padding: 2px 6px; border-radius: 3px;
            font-size: 0.7rem; font-weight: 500;
        }
        .user-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .form-group { margin-bottom: 12px; }
        .form-group label {
            display: block; margin-bottom: 5px; color: #333;
            font-weight: 500; font-size: 0.9rem;
        }
        .form-group input, .form-group select {
            width: 100%; padding: 8px 10px; border: 1px solid #ddd;
            border-radius: 4px; font-size: 0.9rem;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #007bff; outline: none;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        .field-error { color: #dc3545; font-size: 0.8rem; margin-top: 3px; display: block; }
        .perm-checkbox {
            display: flex; align-items: center; gap: 8px; padding: 5px 8px;
            cursor: pointer; border-radius: 4px; font-size: 0.85rem;
            color: #333; margin-bottom: 2px;
        }
        .perm-checkbox:hover { background: #e9ecef; }
        .perm-checkbox input[type="checkbox"] { width: 16px; height: 16px; flex-shrink: 0; }
        @media (max-width: 700px) {
            .user-form-grid { grid-template-columns: 1fr; }
            .modal-users { max-width: 95vw; }
        }
    </style>
</head>
<body x-data="pbxManager()">
    <div class="container">
        {{-- Header --}}
        <div class="header">
            <h1><i class="fas fa-phone-volume"></i> Mis Centrales</h1>
            <div class="header-right">
                <span class="user-info">
                    <i class="fas fa-user"></i> {{ auth()->user()->name }}
                    @if(auth()->user()->isAdmin())
                        <span style="background: #ffc107; color: #333; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; margin-left: 5px;">Admin</span>
                    @endif
                </span>
                <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Salir</button>
                </form>
            </div>
        </div>

        {{-- Alertas --}}
        @if(session('success'))
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}</div>
        @endif
        @if(session('info'))
            <div class="alert alert-info"><i class="fas fa-info-circle"></i> {{ session('info') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <i class="fas fa-times-circle"></i> 
                @foreach($errors->all() as $error) {{ $error }} @endforeach
            </div>
        @endif

        {{-- Título --}}
        <div class="page-title">
            <h2><i class="fas fa-server"></i> Seleccionar Central</h2>
            <p>Elige la central telefónica con la que deseas trabajar</p>
            @if(auth()->user()->isAdmin())
            <button @click="openCreateModal()" class="btn-add">
                <i class="fas fa-plus"></i> Agregar Nueva Central
            </button>
            {{-- User Management (modal inline, separate Alpine scope) --}}
            <div x-data="userManager()" style="display: inline-block; vertical-align: top;">
                <button @click="openModal()" class="btn-users">
                    <i class="fas fa-users-cog"></i> Gestión Usuarios
                </button>

                {{-- ===== USER MANAGEMENT MODAL ===== --}}
                <div x-show="showUserModal" class="modal-backdrop" style="display: none;" x-transition>
                    <div class="modal modal-users" @click.outside="closeModal()">

                        {{-- ===== LIST VIEW ===== --}}
                        <div x-show="currentView === 'list'">
                            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;">
                                <h3><i class="fas fa-users"></i> Gestión de Usuarios</h3>
                                <button @click="closeModal()" style="background:none;border:none;color:white;font-size:1.2rem;cursor:pointer;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                                <div x-show="successMessage" class="alert alert-success" style="margin-bottom:15px;" x-text="successMessage" x-transition></div>
                                <div x-show="errorMessage && currentView === 'list'" class="alert alert-danger" style="margin-bottom:15px;" x-text="errorMessage" x-transition></div>

                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                                    <span style="color:#666;" x-text="'Total: ' + users.length + ' usuarios'"></span>
                                    <button @click="showCreateForm()" class="btn-add" style="margin:0;">
                                        <i class="fas fa-user-plus"></i> Nuevo Usuario
                                    </button>
                                </div>

                                <div x-show="isLoading" style="text-align:center;padding:30px;">
                                    <i class="fas fa-spinner fa-spin fa-2x" style="color:#007bff;"></i>
                                    <p style="color:#666;margin-top:10px;">Cargando usuarios...</p>
                                </div>

                                <div x-show="!isLoading">
                                    <table class="users-table">
                                        <thead>
                                            <tr>
                                                <th>Usuario</th>
                                                <th>Email</th>
                                                <th style="text-align:center;">Rol</th>
                                                <th style="text-align:center;">Permisos</th>
                                                <th style="text-align:center;">Centrales</th>
                                                <th style="text-align:center;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="user in users" :key="user.id">
                                                <tr :style="user.is_current ? 'background:#e8f4fd' : ''">
                                                    <td>
                                                        <div style="display:flex;align-items:center;gap:10px;">
                                                            <span class="user-avatar" :style="'background:' + (user.is_admin ? '#ffc107' : '#6c757d')" x-text="user.name.charAt(0).toUpperCase()"></span>
                                                            <div>
                                                                <span style="font-weight:500;" x-text="user.name"></span>
                                                                <span x-show="user.is_current" style="color:#007bff;font-size:0.8rem;"> (Tú)</span>
                                                                <div style="font-size:0.8rem;color:#999;" x-text="'Creado: ' + user.created_at"></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td x-text="user.email" style="color:#666;"></td>
                                                    <td style="text-align:center;">
                                                        <span class="badge-role" :style="'background:' + getRoleBadge(user).bg + ';color:' + getRoleBadge(user).text" x-text="user.role_display"></span>
                                                    </td>
                                                    <td style="text-align:center;">
                                                        <div style="display:flex;flex-wrap:wrap;gap:3px;justify-content:center;">
                                                            <template x-for="badge in getPermBadges(user)" :key="badge.text">
                                                                <span class="badge-perm" :style="'background:' + badge.bg + ';color:' + badge.fg" x-text="badge.text"></span>
                                                            </template>
                                                        </div>
                                                    </td>
                                                    <td style="text-align:center;">
                                                        <div style="display:flex;flex-wrap:wrap;gap:3px;justify-content:center;">
                                                            <template x-if="user.is_admin">
                                                                <span class="badge-perm" style="background:#d4edda;color:#155724;">Todas</span>
                                                            </template>
                                                            <template x-if="!user.is_admin && user.allowed_pbx_ids.length === 0">
                                                                <span class="badge-perm" style="background:#f8d7da;color:#721c24;">Ninguna</span>
                                                            </template>
                                                            <template x-if="!user.is_admin && user.allowed_pbx_ids.length > 0">
                                                                <span class="badge-perm" style="background:#d6eaf8;color:#2874a6;" x-text="user.allowed_pbx_ids.length + ' central(es)'"></span>
                                                            </template>
                                                        </div>
                                                    </td>
                                                    <td style="text-align:center;">
                                                        <div x-show="!user.is_current" style="display:flex;gap:5px;justify-content:center;">
                                                            <button @click="showEditForm(user)" class="btn-edit" style="padding:5px 10px;font-size:0.8rem;">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button @click="deleteUser(user)" class="btn-delete" style="padding:5px 10px;font-size:0.8rem;">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                        <span x-show="user.is_current" style="color:#999;font-size:0.8rem;">N/A</span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- ===== FORM VIEW (Create / Edit) ===== --}}
                        <div x-show="currentView === 'form'">
                            <div class="modal-header">
                                <h3>
                                    <i class="fas" :class="editingUserId ? 'fa-user-edit' : 'fa-user-plus'"></i>
                                    <span x-text="editingUserId ? 'Editar: ' + form.name : 'Nuevo Usuario'"></span>
                                </h3>
                            </div>
                            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                                <div x-show="errorMessage && currentView === 'form'" class="alert alert-danger" style="margin-bottom:15px;" x-text="errorMessage" x-transition></div>

                                <div class="user-form-grid">
                                    {{-- Left: User Info --}}
                                    <div>
                                        <h4 style="margin:0 0 15px;color:#333;border-bottom:1px solid #eee;padding-bottom:8px;">
                                            <i class="fas fa-user" style="color:#999;margin-right:5px;"></i> Información
                                        </h4>
                                        <div class="form-group">
                                            <label>Nombre Completo *</label>
                                            <input type="text" x-model="form.name" placeholder="Juan Pérez">
                                            <template x-if="errors.name"><span class="field-error" x-text="errors.name[0]"></span></template>
                                        </div>
                                        <div class="form-group">
                                            <label>Correo Electrónico *</label>
                                            <input type="email" x-model="form.email" placeholder="correo@ejemplo.com">
                                            <template x-if="errors.email"><span class="field-error" x-text="errors.email[0]"></span></template>
                                        </div>
                                        <div class="form-group">
                                            <label x-text="editingUserId ? 'Nueva Contraseña (dejar vacío para mantener)' : 'Contraseña *'"></label>
                                            <input type="password" x-model="form.password" placeholder="Mínimo 6 caracteres">
                                            <template x-if="errors.password"><span class="field-error" x-text="errors.password[0]"></span></template>
                                        </div>
                                        <div class="form-group">
                                            <label x-text="editingUserId ? 'Confirmar Nueva Contraseña' : 'Confirmar Contraseña *'"></label>
                                            <input type="password" x-model="form.password_confirmation" placeholder="Repite la contraseña">
                                        </div>
                                        <div class="form-group">
                                            <label>Rol Base *</label>
                                            <select x-model="form.role" @change="updateRole()">
                                                <option value="user">Usuario</option>
                                                <option value="supervisor">Supervisor</option>
                                                <option value="admin">Administrador</option>
                                            </select>
                                            <p x-show="form.role === 'admin'" style="color:#e67e22;font-size:0.8rem;margin-top:5px;">
                                                <i class="fas fa-crown"></i> El administrador tiene acceso total.
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Right: Permissions + Centrals --}}
                                    <div>
                                        <h4 style="margin:0 0 15px;color:#333;border-bottom:1px solid #eee;padding-bottom:8px;">
                                            <i class="fas fa-key" style="color:#999;margin-right:5px;"></i> Permisos
                                        </h4>
                                        <div :style="form.role === 'admin' ? 'opacity:0.5;pointer-events:none;' : ''">
                                            <div style="background:#f8f9fa;padding:10px;border-radius:4px;margin-bottom:10px;">
                                                <h5 style="font-size:0.85rem;color:#666;margin:0 0 8px;">
                                                    <i class="fas fa-sync-alt" style="color:#007bff;"></i> Sincronización
                                                </h5>
                                                <label class="perm-checkbox"><input type="checkbox" x-model="form.can_sync_calls"> Sincronizar Llamadas</label>
                                                <label class="perm-checkbox"><input type="checkbox" x-model="form.can_sync_extensions"> Sincronizar Anexos</label>
                                                <label class="perm-checkbox"><input type="checkbox" x-model="form.can_sync_queues"> Sincronizar Colas</label>
                                            </div>
                                            <div style="background:#f8f9fa;padding:10px;border-radius:4px;margin-bottom:10px;">
                                                <h5 style="font-size:0.85rem;color:#666;margin:0 0 8px;">
                                                    <i class="fas fa-server" style="color:#e67e22;"></i> Acciones de API
                                                </h5>
                                                <label class="perm-checkbox"><input type="checkbox" x-model="form.can_edit_extensions"> Editar Anexos</label>
                                                <label class="perm-checkbox"><input type="checkbox" x-model="form.can_update_ips"> Actualizar IPs</label>
                                                <label class="perm-checkbox"><input type="checkbox" x-model="form.can_manage_pbx"> Gestionar PBX</label>
                                            </div>
                                            <div style="background:#f8f9fa;padding:10px;border-radius:4px;margin-bottom:10px;">
                                                <h5 style="font-size:0.85rem;color:#666;margin:0 0 8px;">
                                                    <i class="fas fa-cog" style="color:#8e44ad;"></i> Configuración
                                                </h5>
                                                <label class="perm-checkbox"><input type="checkbox" x-model="form.can_edit_rates"> Editar Tarifas</label>
                                            </div>
                                            <div style="background:#f8f9fa;padding:10px;border-radius:4px;margin-bottom:10px;">
                                                <h5 style="font-size:0.85rem;color:#666;margin:0 0 8px;">
                                                    <i class="fas fa-eye" style="color:#6366f1;"></i> Visualización de Secciones
                                                </h5>
                                                <label class="perm-checkbox"><input type="checkbox" x-model="form.can_view_charts"> Ver Gráficos y Colas</label>
                                                <label class="perm-checkbox"><input type="checkbox" x-model="form.can_view_extensions"> Ver Anexos</label>
                                                <label class="perm-checkbox"><input type="checkbox" x-model="form.can_view_rates"> Ver Tarifas</label>
                                            </div>
                                            <div style="background:#f8f9fa;padding:10px;border-radius:4px;">
                                                <h5 style="font-size:0.85rem;color:#666;margin:0 0 8px;">
                                                    <i class="fas fa-file-alt" style="color:#27ae60;"></i> Reportes
                                                </h5>
                                                <label class="perm-checkbox"><input type="checkbox" x-model="form.can_export_pdf"> Exportar PDF</label>
                                                <label class="perm-checkbox"><input type="checkbox" x-model="form.can_export_excel"> Exportar Excel</label>
                                            </div>
                                        </div>
                                        <div x-show="form.role === 'admin'" style="background:#fff3cd;border:1px solid #ffc107;padding:10px;border-radius:4px;margin-top:10px;">
                                            <p style="font-size:0.85rem;color:#856404;margin:0;">
                                                <i class="fas fa-info-circle"></i> Los administradores tienen todos los permisos y acceso a todas las centrales.
                                            </p>
                                        </div>

                                        {{-- Centrales Permitidas --}}
                                        <div style="margin-top:15px;" :style="form.role === 'admin' ? 'opacity:0.5;pointer-events:none;' : ''">
                                            <h4 style="margin:0 0 10px;color:#333;border-bottom:1px solid #eee;padding-bottom:8px;">
                                                <i class="fas fa-server" style="color:#17a2b8;margin-right:5px;"></i> Centrales Permitidas
                                            </h4>
                                            <p style="font-size:0.8rem;color:#666;margin:0 0 10px;">
                                                Selecciona las centrales a las que este usuario tendrá acceso.
                                            </p>
                                            <div x-show="pbxConnections.length === 0" style="background:#f8f9fa;padding:15px;border-radius:4px;text-align:center;color:#999;">
                                                <i class="fas fa-server"></i> No hay centrales configuradas.
                                            </div>
                                            <div x-show="pbxConnections.length > 0" style="max-height:200px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;">
                                                <template x-for="conn in pbxConnections" :key="conn.id">
                                                    <label class="perm-checkbox" style="padding:8px 10px;border-bottom:1px solid #f0f0f0;margin:0;">
                                                        <input type="checkbox"
                                                               :value="conn.id"
                                                               :checked="form.allowed_pbx_ids.includes(conn.id)"
                                                               @change="togglePbx(conn.id)">
                                                        <div style="flex:1;">
                                                            <span style="font-weight:500;" x-text="conn.name || 'Sin nombre'"></span>
                                                            <span style="font-size:0.8rem;color:#666;font-family:monospace;margin-left:5px;" x-text="conn.ip + ':' + conn.port"></span>
                                                            <span x-show="conn.status !== 'ready'" class="badge-perm" style="margin-left:5px;"
                                                                  :style="'background:' + (conn.status === 'error' ? '#f8d7da' : '#fff3cd') + ';color:' + (conn.status === 'error' ? '#721c24' : '#856404')"
                                                                  x-text="conn.status === 'pending' ? 'Pendiente' : (conn.status === 'syncing' ? 'Sincronizando' : (conn.status === 'error' ? 'Error' : conn.status))">
                                                            </span>
                                                        </div>
                                                    </label>
                                                </template>
                                            </div>
                                            <div style="margin-top:8px;display:flex;gap:10px;">
                                                <button type="button" @click="selectAllPbx()" style="font-size:0.8rem;color:#007bff;background:none;border:none;cursor:pointer;padding:0;">
                                                    <i class="fas fa-check-double"></i> Todas
                                                </button>
                                                <button type="button" @click="form.allowed_pbx_ids = []" style="font-size:0.8rem;color:#dc3545;background:none;border:none;cursor:pointer;padding:0;">
                                                    <i class="fas fa-times"></i> Ninguna
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button @click="backToList()" class="btn-cancel">
                                    <i class="fas fa-arrow-left"></i> Volver
                                </button>
                                <button @click="editingUserId ? updateUser() : saveUser()" class="btn-save" :disabled="isSaving">
                                    <i class="fas" :class="isSaving ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                                    <span x-text="isSaving ? 'Guardando...' : (editingUserId ? 'Guardar Cambios' : 'Crear Usuario')"></span>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Grid de Centrales --}}
        @if($connections->isEmpty())
            <div class="empty-state">
                <i class="fas fa-server"></i>
                <h3>No hay centrales configuradas</h3>
                <p>Agrega tu primera central PBX para comenzar</p>
            </div>
        @else
            <div class="grid">
                @foreach($connections as $connection)
                    @php
                        $cardClass = session('active_pbx_id') === $connection->id ? 'active' : '';
                        if ($connection->status === 'syncing') $cardClass .= ' syncing';
                        if ($connection->status === 'pending') $cardClass .= ' pending';
                        if ($connection->status === 'error') $cardClass .= ' error';
                    @endphp
                    <div class="card {{ $cardClass }}">
                        {{-- Overlay para centrales en sincronización --}}
                        @if($connection->status === 'syncing' && !auth()->user()->isAdmin())
                            <div class="sync-overlay">
                                <i class="fas fa-sync fa-spin"></i>
                                <p>Sincronizando datos...</p>
                                <p style="font-size: 0.8rem; color: #999;">Disponible pronto</p>
                            </div>
                        @endif

                        <div class="card-header">
                            <h3>
                                {{ $connection->name ?? 'Sin nombre' }}
                                @if(session('active_pbx_id') === $connection->id)
                                    <span class="badge-active">ACTIVA</span>
                                @endif
                                @if(auth()->user()->isAdmin() && $connection->status !== 'ready')
                                    <span class="badge-status badge-{{ $connection->status }}">
                                        @switch($connection->status)
                                            @case('pending') PENDIENTE @break
                                            @case('syncing') SINCRONIZANDO @break
                                            @case('error') ERROR @break
                                            @default {{ strtoupper($connection->status) }}
                                        @endswitch
                                    </span>
                                @endif
                            </h3>
                            <div class="ip"><i class="fas fa-network-wired"></i> {{ $connection->ip }}:{{ $connection->port }}</div>
                        </div>
                        <div class="card-body">
                            <div class="info">
                                <i class="fas fa-user"></i> Usuario: <span>{{ $connection->username }}</span><br>
                                <i class="fas fa-shield-alt"></i> SSL: 
                                <span>{{ $connection->verify_ssl ? 'Verificado' : 'Sin verificar' }}</span>
                                @if(auth()->user()->isAdmin() && $connection->last_sync_at)
                                    <br><i class="fas fa-clock"></i> Última sync: 
                                    <span>{{ $connection->last_sync_at->diffForHumans() }}</span>
                                @endif
                            </div>

                            {{-- Mostrar mensaje de error si existe --}}
                            @if(auth()->user()->isAdmin() && $connection->status === 'error' && $connection->sync_message)
                                <div style="background: #f8d7da; color: #721c24; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 0.85rem;">
                                    <i class="fas fa-exclamation-triangle"></i> {{ $connection->sync_message }}
                                </div>
                            @endif

                            {{-- Botón para continuar configuración (solo admin, estado pending) --}}
                            @if(auth()->user()->isAdmin() && $connection->status === 'pending')
                                <a href="{{ route('pbx.setup', $connection) }}" class="btn-connect" style="background: #ffc107; color: #333;">
                                    <i class="fas fa-cog"></i> CONFIGURAR CENTRAL
                                </a>
                            @elseif(auth()->user()->isAdmin() && $connection->status === 'syncing')
                                <a href="{{ route('pbx.setup', $connection) }}" class="btn-connect" style="background: #17a2b8;">
                                    <i class="fas fa-sync fa-spin"></i> VER PROGRESO
                                </a>
                            @elseif(auth()->user()->isAdmin() && $connection->status === 'error')
                                <a href="{{ route('pbx.setup', $connection) }}" class="btn-connect" style="background: #dc3545;">
                                    <i class="fas fa-redo"></i> REINTENTAR SYNC
                                </a>
                            @elseif(session('active_pbx_id') === $connection->id)
                                @if(auth()->user()->isAdmin())
                                <form action="{{ route('pbx.disconnect') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn-disconnect">
                                        <i class="fas fa-power-off"></i> DESCONECTAR
                                    </button>
                                </form>
                                @else
                                <div style="padding: 10px; background: #e9ecef; border-radius: 4px; text-align: center; color: #666; margin-bottom: 10px;">
                                    <i class="fas fa-check-circle" style="color: #28a745;"></i> Central Activa
                                </div>
                                @endif
                            @elseif($connection->status === 'ready')
                                <a href="{{ route('pbx.select', $connection) }}" class="btn-connect">
                                    <i class="fas fa-plug"></i> CONECTAR
                                </a>
                            @endif

                            @if(auth()->user()->isAdmin())
                            <div class="card-actions">
                                <button @click="openEditModal({{ $connection->toJson() }})" class="btn-edit">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button @click="confirmDelete({{ $connection->id }}, '{{ $connection->name ?? $connection->ip }}')" 
                                        class="btn-delete"
                                        {{ session('active_pbx_id') === $connection->id ? 'disabled' : '' }}>
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Modal Crear/Editar --}}
    <div x-show="showModal" class="modal-backdrop" style="display: none;" x-transition>
        <div class="modal" @click.outside="closeModal()">
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="modal-header">
                    <h3><i class="fas" :class="isEditing ? 'fa-edit' : 'fa-plus-circle'"></i> 
                        <span x-text="isEditing ? 'Editar Central' : 'Nueva Central'"></span>
                    </h3>
                </div>

                <div class="modal-body">
                    <label>Nombre (opcional)</label>
                    <input type="text" name="name" x-model="form.name" placeholder="Ej: Central Principal">

                    <div class="row">
                        <div>
                            <label>Dirección IP *</label>
                            <input type="text" name="ip" x-model="form.ip" required placeholder="192.168.1.100">
                        </div>
                        <div>
                            <label>Puerto *</label>
                            <input type="number" name="port" x-model="form.port" required placeholder="443">
                        </div>
                    </div>

                    <label>Usuario *</label>
                    <input type="text" name="username" x-model="form.username" required placeholder="admin">

                    <label>Contraseña <span x-show="!isEditing">*</span></label>
                    <input type="password" name="password" x-model="form.password" :required="!isEditing" placeholder="••••••••">
                    <p x-show="isEditing" class="hint">Dejar vacío para mantener la actual</p>

                    <div class="checkbox-group">
                        <input type="checkbox" name="verify_ssl" x-model="form.verify_ssl" value="1" id="verify_ssl">
                        <label for="verify_ssl" style="margin:0;">Verificar certificado SSL</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" @click="closeModal()" class="btn-cancel">Cancelar</button>
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> <span x-text="isEditing ? 'Actualizar' : 'Guardar'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Confirmar Eliminación --}}
    <div x-show="showDeleteModal" class="modal-backdrop" style="display: none;" x-transition>
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header" style="background: #dc3545;">
                <h3><i class="fas fa-exclamation-triangle"></i> Eliminar Central</h3>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de eliminar <strong x-text="deleteConnectionName"></strong>?</p>
                <p style="color: #dc3545; font-size: 0.9rem;">
                    <i class="fas fa-warning"></i> Se eliminarán todas las llamadas y extensiones asociadas.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" @click="showDeleteModal = false" class="btn-cancel">Cancelar</button>
                <form :action="deleteAction" method="POST" style="margin:0;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-delete" style="padding: 10px 20px;">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function pbxManager() {
            return {
                showModal: false,
                showDeleteModal: false,
                isEditing: false,
                formAction: '{{ route("pbx.store") }}',
                deleteAction: '',
                deleteConnectionName: '',
                form: { name: '', ip: '', port: '', username: '', password: '', verify_ssl: false },

                openCreateModal() {
                    this.isEditing = false;
                    this.formAction = '{{ route("pbx.store") }}';
                    this.form = { name: '', ip: '', port: '', username: '', password: '', verify_ssl: false };
                    this.showModal = true;
                },

                openEditModal(connection) {
                    this.isEditing = true;
                    this.formAction = '{{ url("pbx") }}/' + connection.id;
                    this.form = {
                        name: connection.name || '',
                        ip: connection.ip,
                        port: connection.port,
                        username: connection.username,
                        password: '',
                        verify_ssl: connection.verify_ssl
                    };
                    this.showModal = true;
                },

                closeModal() {
                    this.showModal = false;
                },

                confirmDelete(id, name) {
                    this.deleteAction = '{{ url("pbx") }}/' + id;
                    this.deleteConnectionName = name;
                    this.showDeleteModal = true;
                }
            }
        }

        function userManager() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const apiBase = '{{ url("api/usuarios") }}';

            return {
                showUserModal: false,
                currentView: 'list',
                users: [],
                pbxConnections: [],
                isLoading: false,
                isSaving: false,
                editingUserId: null,
                successMessage: '',
                errorMessage: '',
                errors: {},
                form: {
                    name: '', email: '', password: '', password_confirmation: '',
                    role: 'user',
                    can_sync_calls: false, can_sync_extensions: false, can_sync_queues: false,
                    can_edit_extensions: false, can_update_ips: false,
                    can_edit_rates: false, can_manage_pbx: false,
                    can_export_pdf: true, can_export_excel: true,
                    can_view_charts: true, can_view_extensions: true, can_view_rates: true,
                    allowed_pbx_ids: [],
                },

                async openModal() {
                    this.showUserModal = true;
                    this.currentView = 'list';
                    this.successMessage = '';
                    this.errorMessage = '';
                    await this.loadUsers();
                },

                closeModal() {
                    if (this.isSaving) return;
                    this.showUserModal = false;
                    this.currentView = 'list';
                    this.successMessage = '';
                    this.errorMessage = '';
                },

                async loadUsers() {
                    this.isLoading = true;
                    try {
                        const res = await fetch(apiBase, {
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                        });
                        const data = await res.json();
                        this.users = data.users;
                        this.pbxConnections = data.pbxConnections;
                    } catch (e) {
                        this.errorMessage = 'Error cargando usuarios.';
                    }
                    this.isLoading = false;
                },

                showCreateForm() {
                    this.editingUserId = null;
                    this.form = {
                        name: '', email: '', password: '', password_confirmation: '',
                        role: 'user',
                        can_sync_calls: false, can_sync_extensions: false, can_sync_queues: false,
                        can_edit_extensions: false, can_update_ips: false,
                        can_edit_rates: false, can_manage_pbx: false,
                        can_export_pdf: true, can_export_excel: true,
                        can_view_charts: true, can_view_extensions: true, can_view_rates: true,
                        allowed_pbx_ids: [],
                    };
                    this.errors = {};
                    this.errorMessage = '';
                    this.currentView = 'form';
                },

                showEditForm(user) {
                    this.editingUserId = user.id;
                    this.form = {
                        name: user.name, email: user.email,
                        password: '', password_confirmation: '',
                        role: user.role,
                        can_sync_calls: user.can_sync_calls,
                        can_sync_extensions: user.can_sync_extensions,
                        can_sync_queues: user.can_sync_queues,
                        can_edit_extensions: user.can_edit_extensions,
                        can_update_ips: user.can_update_ips,
                        can_edit_rates: user.can_edit_rates,
                        can_manage_pbx: user.can_manage_pbx,
                        can_export_pdf: user.can_export_pdf,
                        can_export_excel: user.can_export_excel,
                        can_view_charts: user.can_view_charts,
                        can_view_extensions: user.can_view_extensions,
                        can_view_rates: user.can_view_rates,
                        allowed_pbx_ids: [...(user.allowed_pbx_ids || [])],
                    };
                    this.errors = {};
                    this.errorMessage = '';
                    this.currentView = 'form';
                },

                backToList() {
                    this.currentView = 'list';
                    this.errorMessage = '';
                    this.errors = {};
                },

                async saveUser() {
                    this.isSaving = true;
                    this.errors = {};
                    this.errorMessage = '';
                    try {
                        const res = await fetch(apiBase, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify(this.form)
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.successMessage = data.message;
                            this.currentView = 'list';
                            await this.loadUsers();
                            setTimeout(() => this.successMessage = '', 4000);
                        } else {
                            this.errors = data.errors || {};
                            this.errorMessage = data.message || 'Error al crear usuario.';
                        }
                    } catch (e) {
                        this.errorMessage = 'Error de conexión.';
                    }
                    this.isSaving = false;
                },

                async updateUser() {
                    this.isSaving = true;
                    this.errors = {};
                    this.errorMessage = '';
                    try {
                        const res = await fetch(apiBase + '/' + this.editingUserId, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify(this.form)
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.successMessage = data.message;
                            this.currentView = 'list';
                            await this.loadUsers();
                            setTimeout(() => this.successMessage = '', 4000);
                        } else {
                            this.errors = data.errors || {};
                            this.errorMessage = data.message || 'Error al actualizar usuario.';
                        }
                    } catch (e) {
                        this.errorMessage = 'Error de conexión.';
                    }
                    this.isSaving = false;
                },

                async deleteUser(user) {
                    if (!confirm('¿Estás seguro de eliminar a ' + user.name + '?')) return;
                    try {
                        const res = await fetch(apiBase + '/' + user.id, {
                            method: 'DELETE',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.successMessage = data.message;
                            await this.loadUsers();
                            setTimeout(() => this.successMessage = '', 4000);
                        } else {
                            this.errorMessage = data.message || 'Error al eliminar usuario.';
                        }
                    } catch (e) {
                        this.errorMessage = 'Error de conexión.';
                    }
                },

                updateRole() {
                    if (this.form.role === 'admin') {
                        ['can_sync_calls','can_sync_extensions','can_sync_queues',
                         'can_edit_extensions','can_update_ips','can_edit_rates',
                         'can_manage_pbx','can_export_pdf','can_export_excel',
                         'can_view_charts','can_view_extensions','can_view_rates'
                        ].forEach(p => this.form[p] = true);
                    }
                },

                togglePbx(id) {
                    const idx = this.form.allowed_pbx_ids.indexOf(id);
                    if (idx > -1) {
                        this.form.allowed_pbx_ids.splice(idx, 1);
                    } else {
                        this.form.allowed_pbx_ids.push(id);
                    }
                },

                selectAllPbx() {
                    this.form.allowed_pbx_ids = this.pbxConnections.map(c => c.id);
                },

                getPermBadges(user) {
                    if (user.is_admin) return [{text:'Todos', bg:'#d4edda', fg:'#155724'}];
                    const b = [];
                    if (user.can_sync_calls) b.push({text:'Sync', bg:'#cce5ff', fg:'#004085'});
                    if (user.can_sync_extensions) b.push({text:'SyncExt', bg:'#cce5ff', fg:'#004085'});
                    if (user.can_sync_queues) b.push({text:'SyncQ', bg:'#cce5ff', fg:'#004085'});
                    if (user.can_edit_extensions) b.push({text:'Ext', bg:'#e8daef', fg:'#6c3483'});
                    if (user.can_edit_rates) b.push({text:'Tar', bg:'#fdebd0', fg:'#e67e22'});
                    if (user.can_manage_pbx) b.push({text:'PBX', bg:'#fadbd8', fg:'#c0392b'});
                    if (user.can_export_pdf) b.push({text:'PDF', bg:'#f5b7b1', fg:'#922b21'});
                    if (user.can_export_excel) b.push({text:'XLS', bg:'#d5f5e3', fg:'#1e8449'});
                    if (user.can_view_charts) b.push({text:'Graf', bg:'#d6eaf8', fg:'#2874a6'});
                    if (!user.can_view_extensions) b.push({text:'~~Ext~~', bg:'#eee', fg:'#999'});
                    if (!user.can_view_rates) b.push({text:'~~Tar~~', bg:'#eee', fg:'#999'});
                    if (b.length === 0) b.push({text:'Solo lectura', bg:'#eee', fg:'#999'});
                    return b;
                },

                getRoleBadge(user) {
                    const c = { admin:{bg:'#ffc107',text:'#333'}, supervisor:{bg:'#007bff',text:'#fff'}, user:{bg:'#6c757d',text:'#fff'} };
                    return c[user.role] || c.user;
                }
            }
        }
    </script>
</body>
</html>
