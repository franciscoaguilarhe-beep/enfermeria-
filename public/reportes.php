<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$user = require_fresh_user(); // Assuming this is defined or required by instructions
if ((bool)($user['debe_cambiar_password'] ?? false)) redirect(base_url('index.php?r=password_change'));
$canConfig = Auth::can('ADMINISTRADOR');
$canReports = Auth::can('ADMINISTRADOR', 'SUPERVISOR');

if (!$canReports && !Auth::can('ENFERMERIA')) { // assuming any valid role can access reportes, but specific roles check
    // Wait, let's just let the API handle the scope.
}

$apiUrl = base_url('api/reports.php');
$csrf = Security::csrfToken();
$flashes = take_flash();
$headerPrefijo = trim((string)($user['prefijo'] ?? 'L.E.'));
$headerUserDisplay = trim(($headerPrefijo !== '' ? $headerPrefijo . ' ' : '') . trim((string)($user['nombre'] ?? '')));
$headerContextParts = array_filter([
    trim((string)($user['unidad_nombre'] ?? '')),
    trim((string)($user['consultorio_nombre'] ?? $user['consultorio_clave'] ?? '')),
    trim((string)($user['turno_nombre'] ?? '')),
], static fn(string $v): bool => $v !== '');
$headerContextDisplay = $headerContextParts ? implode(' · ', $headerContextParts) : 'ÁMBITO GENERAL';

function reportes_e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= reportes_e($csrf) ?>">
    <link rel="stylesheet" href="<?= reportes_e(base_url('assets/css/patients.css?v=' . filemtime(__DIR__ . '/assets/css/patients.css'))) ?>">
    <script src="<?= reportes_e(base_url('assets/js/chart.umd.js')) ?>"></script>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#13322B">
    <link rel="apple-touch-icon" href="assets/images/icon-192.png">
    <link rel="icon" href="/enfermeria/public/favicon.ico">
    <title>Reportes · SRAE</title>
</head>
<body class="patients-page" data-reports-app data-api-url="<?= reportes_e($apiUrl) ?>">
    <header class="patients-topbar">
        <a class="brand" href="<?= reportes_e(base_url('pacientes.php')) ?>">
            <span class="brand-title">SRAE</span>
            <span class="brand-sub">Sistema de Registro de Atención de Enfermería</span>
        </a>
        <nav aria-label="Navegación principal">
            <a href="<?= reportes_e(base_url('pacientes.php')) ?>">Pacientes</a>
            <a class="active" href="<?= reportes_e(base_url('reportes.php')) ?>"><strong>Reportes</strong></a>
            <?php if ($canConfig): ?>
                <a href="<?= reportes_e(base_url('configuracion.php')) ?>">Configuración</a>
            <?php endif; ?>
            <div class="nav-user" data-profile-api-url="<?= reportes_e(base_url('api/patients.php')) ?>">
                <button type="button" class="nav-user-name nav-user-trigger" data-header-user-name title="Clic para editar mi prefijo y nombre"><?= reportes_e($headerUserDisplay) ?></button>
                <button type="button" class="nav-user-unit nav-user-trigger" data-header-user-context title="Clic para cambiar consultorio y turno"><?= reportes_e($headerContextDisplay) ?></button>
            </div>
            <a class="nav-logout" href="<?= reportes_e(base_url('index.php?r=logout')) ?>">Cerrar Sesión</a>
        </nav>
    </header>

    <main class="patients-shell">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert <?= reportes_e((string)$flash['type']) ?>"><?= reportes_e((string)$flash['message']) ?></div>
        <?php endforeach; ?>

        <section class="patients-filter-card" aria-label="Filtros de reportes">
            <form id="reports-filters" class="patient-filters" autocomplete="off">
                <div class="filter-group">
                    <label for="f-desde">DESDE</label>
                    <input type="date" id="f-desde" name="fecha_inicio" class="filter-input filter-input-date" data-filter-date required value="<?= date('Y-m-01') ?>">
                </div>
                <div class="filter-group">
                    <label for="f-hasta">HASTA</label>
                    <input type="date" id="f-hasta" name="fecha_fin" class="filter-input filter-input-date" data-filter-date required value="<?= date('Y-m-t') ?>">
                </div>
                <div class="filter-group">
                    <label for="f-unidad">UNIDAD</label>
                    <select id="f-unidad" name="unidad_id" class="filter-input">
                        <option value="">TODAS</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="f-consultorio">CONSULTORIO</label>
                    <select id="f-consultorio" name="consultorio_id" class="filter-input">
                        <option value="">TODOS</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="f-turno">TURNO</label>
                    <select id="f-turno" name="turno_id" class="filter-input">
                        <option value="">TODOS</option>
                    </select>
                </div>
                <?php if (Auth::can('ADMINISTRADOR', 'SUPERVISOR')): ?>
                <div class="filter-group">
                    <label for="f-enfermera">ENFERMERA(O)</label>
                    <select id="f-enfermera" name="enfermera_id" class="filter-input">
                        <option value="">TODOS</option>
                    </select>
                </div>
                <?php endif; ?>
                <button type="submit" class="button-buscar" title="Actualizar" style="padding: 8px 16px; font-size: 16px; min-width: 44px; display: inline-flex; align-items: center; justify-content: center;">🔄</button>
            </form>
        </section>
        
        <section class="reports-stats" style="display: flex; gap: 16px; margin: 20px 0; padding: 0 20px; flex-wrap: wrap;">
            <div class="stat-card" style="background:#fff; padding:16px; border-radius:4px; flex:1; text-align:center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-width: 140px;">
                <div style="font-size:12px; color:#666;">PACIENTES ÚNICOS</div>
                <div data-stat-pacientes style="font-size:24px; font-weight:bold; color:#0288d1;">-</div>
            </div>
            <div class="stat-card" style="background:#fff; padding:16px; border-radius:4px; flex:1; text-align:center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-width: 140px;">
                <div style="font-size:12px; color:#666;">ATENCIONES</div>
                <div data-stat-atenciones style="font-size:24px; font-weight:bold; color:#0288d1;">-</div>
            </div>
            <div class="stat-card" style="background:#fff; padding:16px; border-radius:4px; flex:1; text-align:center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-width: 140px;">
                <div style="font-size:12px; color:#666;">VITAMINA A</div>
                <div data-stat-vitamina style="font-size:24px; font-weight:bold; color:#0288d1;">-</div>
            </div>
            <div class="stat-card" style="background:#fff; padding:16px; border-radius:4px; flex:1; text-align:center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-width: 140px;">
                <div style="font-size:12px; color:#666;">DESPARASITACIÓN</div>
                <div data-stat-desparasitacion style="font-size:24px; font-weight:bold; color:#0288d1;">-</div>
            </div>
            <div class="stat-card" style="background:#fff; padding:16px; border-radius:4px; flex:1; text-align:center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-width: 140px;">
                <div style="font-size:12px; color:#666;">VACUNAS</div>
                <div data-stat-vacunas style="font-size:24px; font-weight:bold; color:#0288d1;">-</div>
            </div>
        </section>

        <section class="reports-charts" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin: 20px 20px; padding: 0;">
            <div class="chart-card" style="background:#fff; padding:16px; border-radius:4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin-top:0; font-size:14px; color:#333; text-align:center;">Productividad por Turno</h3>
                <div style="position: relative; height: 250px;">
                    <canvas id="chart-turnos"></canvas>
                </div>
            </div>
            <div class="chart-card" style="background:#fff; padding:16px; border-radius:4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin-top:0; font-size:14px; color:#333; text-align:center;">Atenciones por Consultorio</h3>
                <div style="position: relative; height: 250px;">
                    <canvas id="chart-consultorios"></canvas>
                </div>
            </div>
            <div class="chart-card" style="background:#fff; padding:16px; border-radius:4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin-top:0; font-size:14px; color:#333; text-align:center;">Acciones Preventivas Generales</h3>
                <div style="position: relative; height: 250px;">
                    <canvas id="chart-preventivas"></canvas>
                </div>
            </div>
            <?php if (Auth::can('ADMINISTRADOR', 'SUPERVISOR')): ?>
            <div class="chart-card" style="background:#fff; padding:16px; border-radius:4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin-top:0; font-size:14px; color:#333; text-align:center;">Top Enfermeras(os) (Atenciones)</h3>
                <div style="position: relative; height: 250px;">
                    <canvas id="chart-enfermeras"></canvas>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <section class="patients-table-card" aria-label="Detalle de atenciones">
            <div class="patients-table-scroll">
                <table class="patients-table">
                    <thead>
                        <tr>
                            <th>FECHA / HORA</th>
                            <th>NSS</th>
                            <th>PACIENTE</th>
                            <th>UNIDAD / CONSULTORIO</th>
                            <th>TURNO</th>
                            <th>ENFERMERA(O)</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody data-report-rows>
                        <tr><td colspan="7" style="text-align:center; padding: 24px; color: var(--srae-text-muted);">Cargando reporte...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <div class="toast-region" aria-live="polite" data-toast-region></div>
    <script src="<?= reportes_e(base_url('assets/js/app.js?v=' . filemtime(__DIR__ . '/assets/js/app.js'))) ?>"></script>
    <script src="<?= reportes_e(base_url('assets/js/reports.js?v=' . time())) ?>"></script>
</body>
</html>
