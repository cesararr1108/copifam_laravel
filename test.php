<?php

try {
    $conn = new PDO(
        "sqlsrv:Server=192.168.1.49,1433;Database=copiweb_sin_lote",
        "TU_USUARIO",
        "TU_PASSWORD"
    );
    echo "OK CONECTADO";
} catch (Exception $e) {
    echo $e->getMessage();
}