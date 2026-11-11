<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Connectors\SqlServerConnector;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Sobrescribir el conector sqlsrv para omitir ATTR_STRINGIFY_FETCHES,
        // el cual causa el error SQLSTATE[IMSSP] con el driver pdo_sqlsrv
        $this->app->bind('db.connector.sqlsrv', function () {
            return new class extends SqlServerConnector {
                protected $options = [
                    PDO::ATTR_CASE => PDO::CASE_NATURAL,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
                ];
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
