<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Extension;
use App\Http\Controllers\IPController;
use Illuminate\Support\Facades\Http;
use App\Traits\GrandstreamTrait;

class ExtensionController extends Controller
{
    use GrandstreamTrait;
    
    public function update(Request $request)
    {
        // 1. Validacion de datos
        $request->validate([
            'extension' => 'required|string', 
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'permission' => 'required|in:Internal,Local,National,International',
            'max_contacts' => 'required|integer|min:1|max:10',
            'secret' => ['nullable', 'string', 'min:5', 'regex:/^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d!@#$%^&*()_+\-=\[\]{};:\'",.<>?\\\|`~]+$/'],
        ], [
            'secret.min' => 'La contraseña SIP debe tener al menos 5 caracteres.',
            'secret.regex' => 'La contraseña SIP debe contener al menos una letra y un número (puede incluir caracteres especiales).',
        ]);

        $extensionLocal = Extension::where('extension', $request->extension)->first();

        if (!$extensionLocal) {
            return back()->with('error', 'Anexo no encontrado en base de datos local');
        }

        // 2. FASE DE CONEXION A GRANDSTREAM (usando el trait)
        // Verificar conexión
        if (!$this->testConnection()) {
            return back()->with('error', ' Error: No se pudo conectar con la Central Telefónica. Verifique la red.');
        }

        // 3. OBTENER ID INTERNO (Necesario para updateUser)
        // Buscamos el usuario en la central para obtener su 'user_id'
        $infoUser = $this->connectApi('getUser', ['user_name' => $request->extension]);
        
        // Logica para extraer el ID sin importar como responda el JSON
        $datosRaw = $infoUser['response']['user_name'] 
                 ?? $infoUser['response'][$request->extension] 
                 ?? $infoUser['response'];
        
        $userId = $datosRaw['user_id'] ?? null;

        if (!$userId) {
            return back()->with('error', ' La extensión existe aquí, pero NO en la Central Telefónica.');
        }

        // 4. PREPARAR DATOS PARA LA API
        // A. Permisos (Traduccion de BD a la API)
        $permisoApi = 'internal'; 
        if ($request->permission == 'International') $permisoApi = 'internal-local-national-international';
        elseif ($request->permission == 'National')  $permisoApi = 'internal-local-national';
        elseif ($request->permission == 'Local')     $permisoApi = 'internal-local';

        // B. No Molestar (DND)
        // El request->boolean devuelve true/false, la API quiere 'yes'/'no'
        $dndApi = $request->boolean('do_not_disturb') ? 'yes' : 'no';

        // 5. ENVIAR CAMBIOS A LA CENTRAL
        
        // Petición 1: Datos de Identidad (Nombre, Apellido, Email, Teléfono)
        $respIdentity = $this->connectApi('updateUser', [
            'user_id' => (int)$userId,
            'user_name' => $request->extension,
            'first_name' => $request->first_name, 
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone 
        ]);

        // Petición 2: Configuración SIP (Permisos, Contactos, DND, Secret)
        $sipData = [
            'extension' => $request->extension,
            'max_contacts' => (int)$request->max_contacts,
            'dnd' => $dndApi,
            'permission' => $permisoApi
        ];

        // Solo incluir secret si se proporciona (para cambiar contraseña SIP)
        if ($request->filled('secret')) {
            $sipData['secret'] = $request->secret;
        }

        $respSip = $this->connectApi('updateSIPAccount', $sipData);

        // 6. VERIFICAR SI TODO SALIO BIEN
        if (($respIdentity['status'] ?? -1) == 0 && ($respSip['status'] ?? -1) == 0) {
            
            // Aplicar cambios (Commit en la central)
            $this->connectApi('applyChanges');

            // 7. ACTUALIZAR BASE DE DATOS LOCAL
            $updateData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'permission' => $request->permission, 
                'do_not_disturb' => $request->boolean('do_not_disturb'),
                'max_contacts' => $request->max_contacts,
            ];

            // Si se proporcionó un nuevo secret, guardarlo también en local
            if ($request->filled('secret')) {
                $updateData['secret'] = $request->secret;
            }

            $extensionLocal->update($updateData);

            return back()->with('success', " Anexo {$request->extension} actualizado en BD y Central Telefónica.");

        } else {
            // Mostrar error más detallado
            $statusIdentity = $respIdentity['status'] ?? 'N/A';
            $statusSip = $respSip['status'] ?? 'N/A';
            $msgIdentity = $respIdentity['response']['body'] ?? json_encode($respIdentity['response'] ?? []);
            $msgSip = $respSip['response']['body'] ?? json_encode($respSip['response'] ?? []);
            
            $msgError = "Identity(status:{$statusIdentity}): {$msgIdentity} | SIP(status:{$statusSip}): {$msgSip}";
            return back()->with('error', " Fallo la actualización en la Central: $msgError");
        }
    }

    public function updateName(Request $request)
    {
        $request->validate([
            'extension_id' => 'required',
            'fullname' => 'required|string|max:255'
        ]);

        Extension::updateOrCreate(
            [
                'pbx_connection_id' => session('active_pbx_id'),
                'extension' => $request->extension_id
            ],
            ['fullname' => $request->fullname]
        );

        return back()->with('success', 'Nombre actualizado correctamente');
    }

    /**
     * Crear una nueva extension en la Central Grandstream UCM6300 y en la BD local
     * Usa la API addSIPAccountAndUser
     */
    public function store(Request $request)
    {
        // 1. Validacion de datos
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

        // 2. FASE DE CONEXION A GRANDSTREAM (usando el trait)
        if (!$this->testConnection()) {
            return back()->with('error', 'Error: No se pudo conectar con la Central Telefónica. Verifique la red.');
        }

        // 3. CREAR EXTENSION EN LA CENTRAL (addSIPAccountAndUser)
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

        $respCreate = $this->connectApi('addSIPAccountAndUser', $createParams);

        // 4. VERIFICAR RESULTADO DE LA CREACION
        $statusCode = $respCreate['status'] ?? -999;
        if ($statusCode != 0) {
            $msgError = $respCreate['response']['body'] ?? $respCreate['response']['message'] ?? json_encode($respCreate);
            return back()->with('error', "Fallo la creacion en la Central (codigo: {$statusCode}): {$msgError}");
        }

        // 5. APLICAR CAMBIOS EN LA CENTRAL (Commit)
        $this->connectApi('applyChanges');

        // 6. GUARDAR EN BASE DE DATOS LOCAL (solo si la central lo creo exitosamente)
        try {
            Extension::create([
                'pbx_connection_id' => session('active_pbx_id'),
                'extension' => $request->extension,
                'permission' => 'Internal',
                'do_not_disturb' => false,
                'max_contacts' => 1,
            ]);
        } catch (\Exception $e) {
            return back()->with('error', "El anexo se creo en la Central, pero fallo el guardado local: {$e->getMessage()}");
        }

        return redirect()->route('extension.index', ['anexo' => $request->extension])
                         ->with('success', "Anexo {$request->extension} creado exitosamente. Puede editarlo para agregar mas detalles.");
    }

    public function index(Request $request)
    {
        $anexo = $request->input('anexo');
        $extensions = \App\Models\Extension::query()
            ->when($anexo, fn ($q) => $q->where('extension', 'like', "%{$anexo}%"))
            ->orderBy('extension', 'asc')
            ->paginate(50)
            ->appends($request->only('anexo'));

        // Obtener las IPs de las extensiones en tiempo real
        $ipController = new IPController();
        $ips = $ipController->getExtensionIps();

        return view('configuracion', compact('extensions', 'anexo', 'ips'));
    }
}