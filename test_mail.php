<?php
require '/var/www/html/copifam_laravel/vendor/autoload.php';
$app = require '/var/www/html/copifam_laravel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    Illuminate\Support\Facades\Mail::raw('Test SMTP Copifam', function($m) {
        $m->to('sarisofia011@gmail.com')->subject('Test SMTP Copifam');
    });
    echo "CORREO ENVIADO OK\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
