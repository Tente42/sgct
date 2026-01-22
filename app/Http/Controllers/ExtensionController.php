<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Extension;
use Illuminate\Support\Facades\Http; // Importante para las peticiones

class ExtensionController extends Controller
{
    
    public function update(Request $request)
    {
        // 1. Validación de datos
        $request->validate([
            'extension' => 'required|string', 
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'permission' => 'required|in:Internal,Local,National,International',
            'max_contacts' => 'required|integer|min:1|max:10',
        ]);

        $extensionLocal = Extension::where('extension', $request->extension)->first();

        if (!$extensionLocal) {
            return back()->with('error', 'Anexo no encontrado en base de datos local');
        }

        // 2. FASE DE CONEXIÓN A GRANDSTREAM
        // Cookie de sesión
        $ip = config('services.grandstream.host');
        $port = config('services.grandstream.port', '7110');
        $user = config('services.grandstream.user');
        $pass = config('services.grandstream.pass');
        $apiUrl = "https://{$ip}:{$port}/api";
        $cookie = $this->getCookie($apiUrl, $user, $pass);

        if (!$cookie) {
            return back()->with('error', ' Error: No se pudo conectar con la Central Telefónica. Verifique la red.');
        }

        // 3. OBTENER ID INTERNO (Necesario para updateUser)
        // Buscamos el usuario en la central para obtener su 'user_id'
        $infoUser = $this->connectApi($apiUrl, 'getUser', ['user_name' => $request->extension], $cookie);
        
        // Lógica para extraer el ID sin importar cómo responda el JSON
        $datosRaw = $infoUser['response']['user_name'] 
                 ?? $infoUser['response'][$request->extension] 
                 ?? $infoUser['response'];
        
        $userId = $datosRaw['user_id'] ?? null;

        if (!$userId) {
            return back()->with('error', ' La extensión existe aquí, pero NO en la Central Telefónica.');
        }

        // 4. PREPARAR DATOS PARA LA API
        // A. Permisos (Traducción de BD a la API)
        $permisoApi = 'internal'; 
        if ($request->permission == 'International') $permisoApi = 'internal-local-national-international';
        elseif ($request->permission == 'National')  $permisoApi = 'internal-local-national';
        elseif ($request->permission == 'Local')     $permisoApi = 'internal-local';

        // B. No Molestar (DND)
        // El request->boolean devuelve true/false, la API quiere 'yes'/'no'
        $dndApi = $request->boolean('do_not_disturb') ? 'yes' : 'no';

        // 5. ENVIAR CAMBIOS A LA CENTRAL
        
        // Petición 1: Datos de Identidad (Nombre, Apellido, Email, Teléfono)
        $respIdentity = $this->connectApi($apiUrl, 'updateUser', [
            'user_id' => (int)$userId,
            'user_name' => $request->extension,
            'first_name' => $request->first_name, 
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone 
        ], $cookie);

        // Petición 2: Configuración SIP (Permisos, Contactos, DND)
        $respSip = $this->connectApi($apiUrl, 'updateSIPAccount', [
            'extension' => $request->extension,
            'max_contacts' => (int)$request->max_contacts,
            'dnd' => $dndApi,
            'permission' => $permisoApi
        ], $cookie);

        // 6. VERIFICAR SI TODO SALIÓ BIEN
        if (($respIdentity['status'] ?? -1) == 0 && ($respSip['status'] ?? -1) == 0) {
            
            // Aplicar cambios (Commit en la central)
            $this->connectApi($apiUrl, 'applyChanges', [], $cookie);

            // 7. ACTUALIZAR BASE DE DATOS LOCAL
            $extensionLocal->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'permission' => $request->permission, 
                'do_not_disturb' => $request->boolean('do_not_disturb'),
                'max_contacts' => $request->max_contacts,
            ]);

            return back()->with('success', " Anexo {$request->extension} actualizado en BD y Central Telefónica.");

        } else {
            // Si falló la API, devolvemos el error y NO guardamos en local para evitar desincronización
            $msgError = $respSip['response']['body'] ?? 'Error desconocido en la central';
            return back()->with('error', " Falló la actualización en la Central: $msgError");
        }
    }

    public function updateName(Request $request)
    {
        $request->validate([
            'extension_id' => 'required',
            'fullname' => 'required|string|max:255'
        ]);

        Extension::updateOrCreate(
            ['extension' => $request->extension_id],
            ['fullname' => $request->fullname]
        );

        return back()->with('success', 'Nombre actualizado correctamente');
    }

    /**
     * Crear una nueva extensión en la Central Grandstream UCM6300 y en la BD local
     * Usa la API addSIPAccountAndUser
     */
    public function store(Request $request)
    {
        // 1. Validación de datos
        $request->validate([
            'extension' => 'required|string|regex:/^[0-9]+$/|unique:extensions,extension',
            'password' => 'required|string',
            'password_confirmation' => 'required|string|same:password',
        ], [
            'extension.required' => 'El número de anexo es obligatorio.',
            'extension.regex' => 'El número de anexo solo puede contener dígitos.',
            'extension.unique' => 'Este número de anexo ya existe en la base de datos local.',
            'password.required' => 'La contraseña es obligatoria.',
            'password_confirmation.required' => 'Debe confirmar la contraseña.',
            'password_confirmation.same' => 'Las contraseñas no coinciden.',
        ]);

        // 2. FASE DE CONEXIÓN A GRANDSTREAM
        $ip = config('services.grandstream.host');
        $port = config('services.grandstream.port', '7110');
        $user = config('services.grandstream.user');
        $pass = config('services.grandstream.pass');
        $apiUrl = "https://{$ip}:{$port}/api";
        $cookie = $this->getCookie($apiUrl, $user, $pass);

        if (!$cookie) {
            return back()->with('error', 'Error: No se pudo conectar con la Central Telefónica. Verifique la red.');
        }

        // 3. CREAR EXTENSIÓN EN LA CENTRAL (addSIPAccountAndUser)
        // Basado en la documentación oficial del UCM6300
        $createParams = [
            'extension' => $request->extension,
            'secret' => $request->password,               // Contraseña SIP
            'vmsecret' => $request->password,             // Contraseña Voicemail
            'user_password' => $request->password,        // Contraseña de usuario
            'max_contacts' => '1',                        // Concurrent Registration
            'permission' => 'internal',                   // Permisos de llamada
            'language' => 'es',                           // Idioma
            'wave_privilege_id' => '0',                   // Privilegio Wave (0 = ninguno)
            'dtmf_mode' => 'rfc4733',                     // DTMF Mode
            'tel_uri' => 'disabled',                      // TEL URI
            'direct_media' => 'no',                       // Enable Direct Media
            'fax_mode' => 'none',                         // Fax Mode
            'skip_trunk_auth' => 'no',                    // Skip Trunk Auth
            'moh' => 'default',                           // Music on Hold
        ];

        $respCreate = $this->connectApi($apiUrl, 'addSIPAccountAndUser', $createParams, $cookie);

        // 4. VERIFICAR RESULTADO DE LA CREACIÓN
        $statusCode = $respCreate['status'] ?? -999;
        if ($statusCode != 0) {
            $msgError = $respCreate['response']['body'] ?? $respCreate['response']['message'] ?? json_encode($respCreate);
            return back()->with('error', "Falló la creación en la Central (código: {$statusCode}): {$msgError}");
        }

        // 5. APLICAR CAMBIOS EN LA CENTRAL (Commit)
        $this->connectApi($apiUrl, 'applyChanges', [], $cookie);

        // 6. GUARDAR EN BASE DE DATOS LOCAL (solo si la central lo creó exitosamente)
        try {
            Extension::create([
                'extension' => $request->extension,
                'permission' => 'Internal',
                'do_not_disturb' => false,
                'max_contacts' => 1,
            ]);
        } catch (\Exception $e) {
            return back()->with('error', "El anexo se creó en la Central, pero falló el guardado local: {$e->getMessage()}");
        }

        return redirect()->route('extension.index', ['anexo' => $request->extension])
                         ->with('success', "Anexo {$request->extension} creado exitosamente. Puede editarlo para agregar más detalles.");
    }

    public function index(Request $request)
    {
        $anexo = $request->input('anexo');
        $extensions = \App\Models\Extension::query()
            ->when($anexo, fn ($q) => $q->where('extension', 'like', "%{$anexo}%"))
            ->orderBy('extension', 'asc')
            ->paginate(50)
            ->appends($request->only('anexo'));
        return view('configuracion', compact('extensions', 'anexo'));
    }

    // ==========================================
    //  MÉTODOS PRIVADOS DE CONEXIÓN 
    // ==========================================

    private function connectApi($url, $action, $params = [], $cookie = null)
    {
        try {
            return Http::withoutVerifying()
                ->timeout(5)
                ->post($url, [
                    'request' => array_merge(['action' => $action, 'cookie' => $cookie], $params)
                ])->json();
        } catch (\Exception $e) {
            return ['status' => -500, 'response' => ['body' => $e->getMessage()]];
        }
    }

    private function getCookie($url, $user, $pass)
    {
        try {
            // 1. Challenge
            $ch = Http::withoutVerifying()->post($url, [
                'request' => ['action' => 'challenge', 'user' => $user, 'version' => '1.0']
            ])->json();
            
            $challenge = $ch['response']['challenge'] ?? '';
            if (!$challenge) return null;

            // 2. Login
            $token = md5($challenge . $pass);
            $login = Http::withoutVerifying()->post($url, [
                'request' => ['action' => 'login', 'user' => $user, 'token' => $token]
            ])->json();

            return $login['response']['cookie'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}