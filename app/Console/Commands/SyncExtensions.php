<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Extension;
use App\Traits\GrandstreamTrait;

class SyncExtensions extends Command
{
    use GrandstreamTrait;

    protected $signature = 'extensions:sync';
    protected $description = 'Sincroniza usuarios usando Cookie Auth (Trait centralizado)';

    public function handle()
    {
        $this->info("============================================");
        $this->info("  SINCRONIZADOR DE EXTENSIONES");
        $this->info("  (Método: Cookie Auth - Trait)");
        $this->info("============================================");

        $this->comment("1. Probando conexión...");
        
        if (!$this->testConnection()) {
            $this->error(" Error de conexión. Verifica IP, puerto, usuario y contraseña.");
            return 1;
        }
        
        $this->info("   ✅ Conexión exitosa!");

        // Obtener lista de cuentas
        $this->comment("2. Descargando lista de cuentas...");

        $response = $this->connectApi('listAccount', [
            'item_num' => '1000',
            'sidx' => 'extension',
            'sord' => 'asc',
            'page' => 1
        ]);

        if (($response['status'] ?? -1) != 0) {
            $this->error(" Error al obtener cuentas. Status: " . ($response['status'] ?? 'N/A'));
            return 1;
        }

        // Buscamos en 'account' o 'user'
        $cuentas = $response['response']['account'] ?? $response['response']['user'] ?? [];
        $count = count($cuentas);

        if ($count > 0) {
            $this->info("   ¡Encontrados {$count} usuarios!");
            $bar = $this->output->createProgressBar($count);
            $bar->start();

            foreach ($cuentas as $cuenta) {
                Extension::updateOrCreate(
                    ['extension' => $cuenta['extension']], 
                    [
                        'fullname' => $cuenta['fullname'] ?? $cuenta['members_name'] ?? 'Sin Nombre',
                        'email'    => $cuenta['email'] ?? null,
                    ]
                );
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(" ✅ Base de datos actualizada correctamente.");
        } else {
            $this->warn(" Login OK, pero la lista está vacía. Verifica permisos del usuario.");
            $this->line(json_encode($response, JSON_PRETTY_PRINT));
        }

        return 0;
    }
}