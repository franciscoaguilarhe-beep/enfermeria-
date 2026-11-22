<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$pdo = db();

echo "Iniciando verificación de flujo PACIENTES...\n";

// 1. Verificar usuario existente
$stmt = $pdo->prepare('SELECT usuario_id, matricula, nombre FROM usuarios WHERE matricula = ? LIMIT 1');
$stmt->execute(['99032894']);
$user = $stmt->fetch();
if (!$user) {
    echo "ERROR: Usuario administrador 99032894 no encontrado.\n";
    exit(1);
}
echo "OK: Usuario encontrado: {$user['nombre']} ({$user['matricula']})\n";

// 2. Probar cálculo de ordenación de vacunas (16 vacunas)
$vacunas = $pdo->query('SELECT clave, nombre, orden_visual FROM vacunas ORDER BY orden_visual, vacuna_id')->fetchAll(PDO::FETCH_ASSOC);
if (count($vacunas) < 16) {
    echo "ERROR: Menos de 16 vacunas configuradas (" . count($vacunas) . ").\n";
    exit(1);
}
echo "OK: " . count($vacunas) . " vacunas registradas en orden visual.\n";

// 3. Probar consulta de atenciones y búsqueda de pacientes
$searchQuery = '2206861799';
$stmt = $pdo->prepare('SELECT p.* FROM pacientes p WHERE p.nss = ? LIMIT 1');
$stmt->execute([$searchQuery]);
$patient = $stmt->fetch();
if ($patient) {
    echo "OK: Paciente mock encontrado: {$patient['nombres']} {$patient['apellido_paterno']}\n";
} else {
    echo "INFO: Paciente mock {$searchQuery} no existe aún en la base de datos (se probará en vivo).\n";
}

echo "Verificación de Fase 2 (Pacientes) completada exitosamente.\n";
