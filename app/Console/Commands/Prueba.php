<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Prueba extends Command
{
    protected $signature = 'test:prueba';
    protected $description = 'Comando de prueba';

    public function handle()
    {
        $this->info('✅ COMANDO DE PRUEBA FUNCIONANDO');
        file_put_contents('/var/log/prueba-comando.log', "PRUEBA: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        return 0;
    }
}
