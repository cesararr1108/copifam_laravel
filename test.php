<?php 
$serverName = "75.76.77.73";

$connectionOptions = array(
    "Database" => "copifam_sin_lote",
    "Uid" => "copifam",
    "PWD" => "C0p1f4m$%",
    "CharacterSet" => "UTF-8",
    "Encrypt" => "yes",
    "TrustServerCertificate" => "yes"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

echo "✅ Conexion exitosa";