<?php
// toggle_led.php

$host     = 'aws-1-us-east-1.pooler.supabase.com';
$port     = 6543;
$dbname   = 'postgres';
$user     = 'postgres.dsayylzehvehmscwkhma';
$password = 'awuebo21xdA';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// 1 = encender, 0 = apagar
$estado = isset($_POST['estado']) ? (int)$_POST['estado'] : 0;
$estado = $estado ? 1 : 0;

// Valor booleano como string que entiende PostgreSQL
$bool_str = $estado ? 'true' : 'false';

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            // ✅ Necesario para Supabase pooler (puerto 6543)
            PDO::ATTR_EMULATE_PREPARES => true,
        ]
    );

    // UPSERT: inserta si no existe, actualiza si ya existe
    // ✅ Sin parámetros nombrados repetidos — interpolamos el valor directamente
    //    (es seguro porque $bool_str solo puede ser 'true' o 'false')
    $pdo->exec("
        INSERT INTO control_dispositivos (id, led)
        VALUES (1, {$bool_str})
        ON CONFLICT (id) DO UPDATE SET led = {$bool_str}
    ");

} catch (PDOException $e) {
    header('Location: index.php?db_error=' . urlencode($e->getMessage()));
    exit;
}

header('Location: index.php');
exit;