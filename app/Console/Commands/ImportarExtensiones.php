<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Extension;
use App\Console\Commands\Concerns\ConfiguresPbx;

class ImportarExtensiones extends Command
{
    use ConfiguresPbx;

    protected $signature = 'extensions:import 
                            {target? : Extensión específica a sincronizar}
                            {--pbx= : ID de la central PBX a usar}
                            {--quick : Modo rápido sin detalles SIP}';
    
    protected $description = 'Sincroniza extensiones desde la central Grandstream';

    private int $pbxId;
    private bool $quickMode;

    public function handle(): int
    {
        ini_set('memory_limit', '1028M');

        // Configurar central
        $pbxId = $this->setupPbxConnection();
        if (!$pbxId) return 1;
        $this->pbxId = $pbxId;
        $this->quickMode = $this->option('quick');

        $this->info("============================================");
        $this->info("  SINCRONIZADOR DE EXTENSIONES");
        $this->info("  Modo: " . ($this->quickMode ? "RÁPIDO" : "COMPLETO"));
        $this->info("============================================");

        // Verificar conexión
        if (!$this->verifyConnection()) return 1;
        $this->info(" ✓ Conexión exitosa!");

        // Si es target específico
        if ($target = $this->argument('target')) {
            return $this->syncSingle($target);
        }

        return $this->syncAll();
    }

    /**
     * Sincronizar todas las extensiones
     */
    private function syncAll(): int
    {
        $this->line(" Descargando lista de usuarios...");
        
        $users = $this->fetchUserList();
        if (empty($users)) {
            $this->error(" Lista vacía.");
            return 1;
        }

        $total = count($users);
        $this->info(" Procesando {$total} extensiones...");

        $stats = ['sin_cambios' => 0, 'actualizados' => 0, 'nuevos' => 0];
        $processed = 0;

        foreach (array_chunk($users, 50) as $chunk) {
            foreach ($chunk as $userData) {
                $result = $this->processExtension($userData);
                $stats[$result]++;
                $processed++;
            }
            $this->line("   Procesados: {$processed}/{$total}");
            gc_collect_cycles();
        }

        $this->newLine();
        $this->info(" ✓ COMPLETADO:");
        $this->table(['Estado', 'Cantidad'], [
            ['Sin Cambios', $stats['sin_cambios']],
            ['Actualizados', $stats['actualizados']],
            ['Nuevos', $stats['nuevos']],
            ['TOTAL', $total]
        ]);

        return 0;
    }

    /**
     * Obtener lista de usuarios de la API
     */
    private function fetchUserList(): array
    {
        $response = $this->connectApi('listUser', [], 60);
        $responseBlock = $response['response'] ?? [];

        $users = $responseBlock['user'] ?? [];
        if (empty($users)) {
            foreach ($responseBlock as $value) {
                if (is_array($value) && !empty($value) && isset($value[0]['user_name'])) {
                    return $value;
                }
            }
        }

        return $users;
    }

    /**
     * Procesar una extensión
     * @return string 'nuevos'|'actualizados'|'sin_cambios'
     */
    private function processExtension(array $userData): string
    {
        $extension = $userData['user_name'] ?? null;
        if (!$extension) return 'sin_cambios';

        $data = $this->buildExtensionData($userData);

        $existing = Extension::withoutGlobalScope('current_pbx')
            ->where('extension', $extension)
            ->where('pbx_connection_id', $this->pbxId)
            ->first();

        if ($existing) {
            if ($this->hasChanges($existing, $data)) {
                $existing->update($data);
                return 'actualizados';
            }
            return 'sin_cambios';
        }

        Extension::withoutGlobalScope('current_pbx')->create(
            array_merge(['extension' => $extension, 'pbx_connection_id' => $this->pbxId], $data)
        );
        return 'nuevos';
    }

    /**
     * Construir datos de extensión desde la respuesta de la API
     */
    private function buildExtensionData(array $userData): array
    {
        $extension = $userData['user_name'];
        
        $data = [
            'fullname' => $userData['fullname'] ?? $extension,
            'email' => $userData['email'] ?? null,
            'first_name' => $userData['first_name'] ?? null,
            'last_name' => $userData['last_name'] ?? null,
            'phone' => $userData['phone_number'] ?? null,
            'do_not_disturb' => false,
            'permission' => 'Internal',
            'max_contacts' => 1,
        ];

        // En modo completo, obtener detalles SIP
        if (!$this->quickMode) {
            $sipData = $this->connectApi('getSIPAccount', ['extension' => $extension], 10);
            if (($sipData['status'] ?? -1) == 0) {
                $details = $sipData['response']['extension'] 
                    ?? $sipData['response']['sip_account'][0] 
                    ?? $sipData['response']['sip_account'] 
                    ?? [];

                $data['do_not_disturb'] = ($details['dnd'] ?? 'no') === 'yes';
                $data['max_contacts'] = (int)($details['max_contacts'] ?? 1);
                $data['secret'] = $details['secret'] ?? null;
                $data['permission'] = $this->parsePermission($details['permission'] ?? 'internal');
            }
            usleep(10000);
        }

        return $data;
    }

    /**
     * Parsear permiso de la API al formato local
     */
    private function parsePermission(string $raw): string
    {
        if (str_contains($raw, 'international')) return 'International';
        if (str_contains($raw, 'national')) return 'National';
        if (str_contains($raw, 'local')) return 'Local';
        return 'Internal';
    }

    /**
     * Verificar si hay cambios entre el registro existente y los nuevos datos
     */
    private function hasChanges(Extension $existing, array $new): bool
    {
        $fieldsToCheck = ['fullname', 'email', 'permission', 'do_not_disturb', 'max_contacts'];
        
        foreach ($fieldsToCheck as $field) {
            if ($existing->$field != ($new[$field] ?? null)) {
                return true;
            }
        }

        if (!$this->quickMode && isset($new['secret']) && $existing->secret != $new['secret']) {
            return true;
        }

        return false;
    }

    /**
     * Sincronizar una sola extensión
     */
    private function syncSingle(string $target): int
    {
        $this->line(" Obteniendo datos de extensión {$target}...");

        $userInfo = $this->connectApi('getUser', ['user_name' => $target]);
        if (($userInfo['status'] ?? -1) != 0) {
            $this->error(" Extensión no encontrada en la central.");
            return 1;
        }

        $userData = $userInfo['response']['user_name'] ?? $userInfo['response'][$target] ?? $userInfo['response'];
        $userData['user_name'] = $target;

        // Forzar modo completo para sincronización individual
        $this->quickMode = false;
        $data = $this->buildExtensionData($userData);

        Extension::withoutGlobalScope('current_pbx')->updateOrCreate(
            ['extension' => $target, 'pbx_connection_id' => $this->pbxId],
            $data
        );

        $this->info(" ✓ Extensión {$target} sincronizada correctamente.");
        return 0;
    }
}
