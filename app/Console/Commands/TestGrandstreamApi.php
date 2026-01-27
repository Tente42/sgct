<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Traits\GrandstreamTrait;

class TestGrandstreamApi extends Command
{
    use GrandstreamTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grandstream:test 
                            {action=status : Acción a ejecutar (status, cdrapi, listAccount, getSystemStatus)}
                            {--records=5 : Número de registros para cdrapi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar conexión y acciones a la API Grandstream usando el método cookie (NO Digest Auth)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        
        $this->info('===========================================');
        $this->info('  PRUEBA DE API GRANDSTREAM (Cookie Auth)  ');
        $this->info('===========================================');
        $this->newLine();

        // Mostrar configuración actual
        $this->line('📡 Configuración:');
        $this->line('   Host: ' . config('services.grandstream.host'));
        $this->line('   Puerto: ' . config('services.grandstream.port', '7110'));
        $this->line('   Usuario: ' . config('services.grandstream.user'));
        $this->newLine();

        // Test de conexión básico
        $this->line('🔐 Probando autenticación challenge/login/cookie...');
        
        if ($this->testConnection()) {
            $this->info('✅ Conexión exitosa!');
        } else {
            $this->error('❌ Fallo la conexión. Verifica IP, puerto, usuario y contraseña.');
            return 1;
        }
        $this->newLine();

        // Ejecutar acción solicitada
        $params = [];
        $timeout = 30;

        switch ($action) {
            case 'status':
                $action = 'getSystemStatus';
                $this->line('📊 Obteniendo estado del sistema...');
                break;
                
            case 'cdrapi':
                $numRecords = $this->option('records');
                $params = ['format' => 'json', 'numRecords' => (int)$numRecords];
                $timeout = 60;
                $this->line("📞 Obteniendo últimos {$numRecords} CDRs...");
                break;
                
            case 'listAccount':
                $params = ['options' => 'extension,status,addr', 'item_num' => 10];
                $this->line('👥 Listando extensiones (máx 10)...');
                break;
                
            default:
                $this->line("🔧 Ejecutando acción: {$action}");
        }

        $result = $this->connectApi($action, $params, $timeout);
        
        $this->newLine();
        $this->line('📋 Resultado:');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Verificar status
        $status = $result['status'] ?? -999;
        $this->newLine();
        
        if ($status == 0 || isset($result['cdr_root'])) {
            $this->info('✅ Petición exitosa (status: ' . $status . ')');
            return 0;
        } else {
            $this->warn('⚠️  Status: ' . $status);
            return 1;
        }
    }
}
