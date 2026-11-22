<?php
require_once dirname(__DIR__) . "/config/bootstrap.php";
if (php_sapi_name() !== "cli") {
    exit("Este script solo puede ejecutarse desde la lnea de comandos.\n");
}
echo "CREACIN DE PRIMER ADMINISTRADOR\n";
echo "-------------------------------\n";
$matricula = trim(readline("Matrcula (ej. ADMIN-01): "));
$nombre = trim(readline("Nombre completo: "));
$password = trim(readline("Contrasea (mnimo 8 caracteres): "));
if (strlen($password) < 8) {
    exit("Error: La contrasea debe tener al menos 8 caracteres.\n");
}
$pdo = db();
$hash = password_hash($password, defined("PASSWORD_ARGON2ID") ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO usuarios (matricula, nombre, password_hash, rol_id, es_personal_clinico, activo, debe_cambiar_password) VALUES (?, ?, ?, 1, 0, 1, 1)");
try {
    $stmt->execute([$matricula, $nombre, $hash]);
    echo "Administrador creado correctamente.\n";
} catch (Exception $e) {
    echo "Error al crear el administrador: " . $e->getMessage() . "\n";
}

