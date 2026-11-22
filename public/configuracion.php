<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';

$user = Auth::requireRole('ADMINISTRADOR');
if ((bool)($user['debe_cambiar_password'] ?? false)) redirect(base_url('index.php?r=password_change'));
$apiUrl = base_url('api/config.php');
$csrf = Security::csrfToken();
$flashes = take_flash();

function config_e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= config_e($csrf) ?>">
    <title>Configuración · SRAE</title>
    <link rel="stylesheet" href="<?= config_e(base_url('assets/css/patients.css')) ?>">
    <link rel="stylesheet" href="<?= config_e(base_url('assets/css/configuracion.css')) ?>">
<link rel="icon" href="/enfermeria/public/favicon.ico"></head>
<body class="config-page" data-config-app data-api-url="<?= config_e($apiUrl) ?>">
    <!-- Topbar Institucional -->
    <header class="patients-topbar">
        <a class="brand" href="<?= config_e(base_url('pacientes.php')) ?>">
            <span class="brand-title">SRAE</span>
            <span class="brand-sub">Sistema de Registro de Atención de Enfermería</span>
        </a>
        <nav aria-label="Navegación principal">
            <a href="<?= config_e(base_url('pacientes.php')) ?>">Pacientes</a>
            <a href="<?= config_e(base_url('index.php?r=reports')) ?>">Reportes</a>
            <a class="active" href="<?= config_e(base_url('configuracion.php')) ?>"><strong>Configuración</strong></a>
            <div class="nav-user">
                <span class="nav-user-name"><?= config_e((string)$user['nombre']) ?></span>
                <span class="nav-user-unit"><?= config_e(trim((string)($user['unidad_nombre'] ?? '') . ' · ' . (string)($user['consultorio_nombre'] ?? ''), ' ·') ?: 'ÁMBITO GENERAL') ?></span>
            </div>
            <a class="nav-logout" href="<?= config_e(base_url('index.php?r=logout')) ?>">Cerrar Sesión</a>
        </nav>
    </header>

    <main class="config-shell">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert <?= config_e((string)$flash['type']) ?>"><?= config_e((string)$flash['message']) ?></div>
        <?php endforeach; ?>

        <!-- SECCIÓN 1: Búsqueda y Tabla de Usuarios -->
        <section class="config-filter-card" aria-label="Búsqueda de usuarios">
            <form id="user-search-form" class="config-search-form" autocomplete="off">
                <div class="search-group">
                    <label for="search-user-input">BUSQUEDA POR NOMBRE O MATRICULA</label>
                    <input type="search" id="search-user-input" name="q" placeholder="Buscar por matrícula o nombre..." data-user-search-input>
                </div>
                <button type="submit" class="btn-teal-pill">BUSCAR</button>
                <div style="margin-left: auto;">
                    <button type="button" class="btn-teal-pill" data-open-register-user>Registrar Usuario</button>
                </div>
            </form>
        </section>

        <section class="config-users-table-card" aria-label="Tabla de usuarios">
            <table class="config-table">
                <thead>
                    <tr>
                        <th>MATRICULA</th>
                        <th>NOMBRE</th>
                        <th>UNIDAD</th>
                        <th>CONS./TURNO</th>
                        <th style="text-align: center; width: 140px;">ESTADO</th>
                    </tr>
                </thead>
                <tbody data-users-rows>
                    <tr><td colspan="5" style="text-align: center; padding: 24px;">Cargando usuarios...</td></tr>
                </tbody>
            </table>
        </section>

        <!-- SECCIONES 2 Y 3: Paneles de UNIDADES y CONSULTORIOS -->
        <div class="config-panels-row">
            <!-- Panel Izquierdo: UNIDADES -->
            <section class="config-panel-teal" aria-labelledby="unidades-heading">
                <h2 id="unidades-heading" class="panel-header-title">UNIDADES</h2>
                <div class="units-tiles-list" data-units-container>
                    <div style="opacity: 0.8;">Cargando unidades...</div>
                </div>
                <div class="panel-bottom-actions">
                    <button type="button" class="panel-action-btn" data-edit-selected-unit>EDITAR</button>
                    <button type="button" class="panel-action-btn" data-add-unit>AGREGAR</button>
                </div>
            </section>

            <!-- Panel Derecho: CONSULTORIOS -->
            <section class="config-panel-teal" aria-labelledby="consultorios-heading">
                <h2 id="consultorios-heading" class="panel-header-title">CONSULTORIOS</h2>
                <div class="consultorios-grid-container" data-consultorios-container>
                    <div style="opacity: 0.8;">Selecciona una unidad para ver sus consultorios.</div>
                </div>
                <div class="panel-bottom-actions">
                    <button type="button" class="panel-action-btn" data-add-consultorio>AGREGAR</button>
                </div>
            </section>
        </div>
    </main>

    <footer class="patients-footer">
        Sistema diseñado para la captura de formato de Censo AMMATES Y Hoja atención integral. V. 1.0
    </footer>

    <div class="toast-region" aria-live="polite" data-toast-region></div>

    <!-- ==========================================================================
         MODAL: REGISTRO DE USUARIO (Página 2 PDF)
         ========================================================================== -->
    <div class="modal-layer" data-modal="register-user" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="reg-user-title" tabindex="-1">
            <div class="modal-header-line">
                <h2 id="reg-user-title" class="modal-title">REGISTRO DE USUARIO</h2>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>
            <form id="reg-user-form" autocomplete="off">
                <div class="form-error" data-user-error style="color: #c62828; margin-bottom: 12px; font-weight: 600;" hidden></div>
                <div class="modal-section-title">DATO GENERALES:</div>
                <div class="field-grid three">
                    <div class="field-item">
                        <label>NOMBRE (PATERNO)</label>
                        <input name="apellido_paterno" placeholder="PATERNO" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>MATERNO</label>
                        <input name="apellido_materno" placeholder="MATERNO" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>NOMBRE(S) *</label>
                        <input name="nombres" placeholder="NOMBRE" required data-uppercase>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>MATRICULA: *</label>
                        <input name="matricula" placeholder="12345678" required>
                    </div>
                    <div class="field-item">
                        <label>UNIDAD:</label>
                        <select name="unidad_id" data-user-unit-select>
                            <option value="0">SIN ASIGNACIÓN</option>
                        </select>
                    </div>
                    <div class="field-item">
                        <label>CONSULTORIO:</label>
                        <select name="consultorio_id" data-user-consultorio-select>
                            <option value="0">SIN ASIGNACIÓN</option>
                        </select>
                    </div>
                </div>

                <div class="field-grid two">
                    <div class="field-item">
                        <label>TÍTULO / PREFIJO: *</label>
                        <select name="prefijo" required data-user-prefix-select>
                            <option value="L.E.">L.E. (LIC. EN ENFERMERÍA)</option>
                            <option value="M.E.">M.E. (MAESTRO/A EN ENFERMERÍA)</option>
                            <option value="E.G.">E.G. (ENFERMERO/A GENERAL)</option>
                            <option value="E.E.">E.E. (ENFERMERO/A ESPECIALISTA)</option>
                            <option value="AUX. ENF.">AUX. ENF. (AUXILIAR DE ENFERMERÍA)</option>
                            <option value="DR.">DR. (DOCTOR)</option>
                            <option value="DRA.">DRA. (DOCTORA)</option>
                            <option value="C.">C. (CIUDADANO/A)</option>
                        </select>
                    </div>
                    <div class="field-item">
                        <label>ROL: *</label>
                        <select name="rol_id" required data-user-role-select></select>
                    </div>
                </div>

                <div class="field-grid two">
                    <div class="field-item">
                        <label>CONTRASEÑA: * (MÍNIMO 8 CARACTERES)</label>
                        <input type="password" name="password" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="field-item">
                        <label>REPETIR CONTRASEÑA: *</label>
                        <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="modal-btn">GUARDAR</button>
                    <button type="button" class="modal-btn secondary" data-close-modal>CANCELAR</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL: TARJETA DE USUARIO & EDICIÓN (Página 3 PDF)
         ========================================================================== -->
    <div class="modal-layer" data-modal="card-user" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="card-user-title" tabindex="-1">
            <div class="modal-header-line">
                <h2 id="card-user-title" class="modal-title" data-card-user-title>TARJETA DE USUARIO</h2>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>

            <!-- Vista Lectura -->
            <div data-user-card-view>
                <div class="modal-section-title">DATO GENERALES:</div>
                <div class="card-data-row" style="margin-bottom: 16px;">
                    <div class="card-data-item">
                        <span class="card-data-label">TÍTULO / PREFIJO:</span>
                        <span class="card-data-value" data-card-user-prefix style="font-size: 14px; font-weight: 700;">—</span>
                    </div>
                    <div class="card-data-item" style="grid-column: span 2;">
                        <span class="card-data-label">NOMBRE:</span>
                        <span class="card-data-value" data-card-user-name style="font-size: 14px; font-weight: 700;">—</span>
                    </div>
                </div>
                <div class="card-data-row">
                    <div class="card-data-item">
                        <span class="card-data-label">MATRICULA:</span>
                        <span class="card-data-value" data-card-user-mat>—</span>
                    </div>
                    <div class="card-data-item">
                        <span class="card-data-label">UNIDAD:</span>
                        <span class="card-data-value" data-card-user-unit>—</span>
                    </div>
                    <div class="card-data-item">
                        <span class="card-data-label">CONSULTORIO:</span>
                        <span class="card-data-value" data-card-user-clinic>—</span>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn" data-btn-edit-user>EDITAR</button>
                    <button type="button" class="modal-btn secondary" data-close-modal>CANCELAR</button>
                </div>
            </div>

            <!-- Vista Edición In-Situ -->
            <form id="edit-user-form" data-user-card-edit hidden autocomplete="off">
                <input type="hidden" name="usuario_id">
                <div class="form-error" data-edit-user-error style="color: #c62828; margin-bottom: 12px; font-weight: 600;" hidden></div>
                <div class="modal-section-title">DATO GENERALES:</div>
                <div class="field-grid three">
                    <div class="field-item">
                        <label>TÍTULO / PREFIJO: *</label>
                        <select name="prefijo" required data-edit-user-prefix-select>
                            <option value="L.E.">L.E. (LIC. EN ENFERMERÍA)</option>
                            <option value="M.E.">M.E. (MAESTRO/A EN ENFERMERÍA)</option>
                            <option value="E.G.">E.G. (ENFERMERO/A GENERAL)</option>
                            <option value="E.E.">E.E. (ENFERMERO/A ESPECIALISTA)</option>
                            <option value="AUX. ENF.">AUX. ENF. (AUXILIAR DE ENFERMERÍA)</option>
                            <option value="DR.">DR. (DOCTOR)</option>
                            <option value="DRA.">DRA. (DOCTORA)</option>
                            <option value="C.">C. (CIUDADANO/A)</option>
                        </select>
                    </div>
                    <div class="field-item">
                        <label>NOMBRE COMPLETO *</label>
                        <input name="nombre" required data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>MATRICULA: *</label>
                        <input name="matricula" required>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>UNIDAD:</label>
                        <select name="unidad_id" data-edit-user-unit-select></select>
                    </div>
                    <div class="field-item">
                        <label>CONSULTORIO:</label>
                        <select name="consultorio_id" data-edit-user-consultorio-select></select>
                    </div>
                    <div class="field-item">
                        <label>ROL: *</label>
                        <select name="rol_id" required data-edit-user-role-select></select>
                    </div>
                </div>

                <div class="modal-section-title">ACTUALIZAR CONTRASEÑA (OPCIONAL):</div>
                <div class="field-grid two">
                    <div class="field-item">
                        <label>NUEVA CONTRASEÑA (MÍNIMO 8 CARACTERES)</label>
                        <input type="password" name="password" minlength="8" autocomplete="new-password">
                    </div>
                    <div class="field-item">
                        <label>REPETIR CONTRASEÑA</label>
                        <input type="password" name="password_confirm" minlength="8" autocomplete="new-password">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="modal-btn">GUARDAR</button>
                    <button type="button" class="modal-btn secondary" data-cancel-edit-user>CANCELAR</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL: REGISTRO / DATOS DE UNIDAD (Página 4 PDF)
         ========================================================================== -->
    <div class="modal-layer" data-modal="unit" hidden>
        <div class="modal-card" style="max-width: 520px;" role="dialog" aria-modal="true" aria-labelledby="unit-modal-title" tabindex="-1">
            <div class="modal-header-line">
                <h2 id="unit-modal-title" class="modal-title" data-unit-modal-title>REGISTRO DE UNIDAD</h2>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>
            <form id="unit-form" autocomplete="off">
                <input type="hidden" name="unidad_id">
                <div class="form-error" data-unit-form-error style="color: #c62828; margin-bottom: 12px; font-weight: 600;" hidden></div>
                <div class="modal-section-title">DATOS GENERALES:</div>
                <div class="field-item" style="margin-bottom: 14px;">
                    <label>NOMBRE DE LA UNIDAD: *</label>
                    <input name="nombre" placeholder="UNIDAD DE MEDICINA FAMILIAR N° 40" required data-uppercase>
                </div>
                <div class="field-item" style="margin-bottom: 20px;">
                    <label>SIGLAS: *</label>
                    <input name="siglas" placeholder="U.M.F. N° 40" required data-uppercase>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="modal-btn">GUARDAR</button>
                    <button type="button" class="modal-btn secondary" data-close-modal>CANCELAR</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL: REGISTRO DE CONSULTORIO (Página 4 PDF)
         ========================================================================== -->
    <div class="modal-layer" data-modal="consultorio" hidden>
        <div class="modal-card" style="max-width: 540px;" role="dialog" aria-modal="true" aria-labelledby="cons-modal-title" tabindex="-1">
            <div class="modal-header-line">
                <h2 id="cons-modal-title" class="modal-title">REGISTRO DE CONSULTORIO</h2>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>
            <form id="consultorio-form" autocomplete="off">
                <div class="form-error" data-consult-form-error style="color: #c62828; margin-bottom: 12px; font-weight: 600;" hidden></div>
                <div class="field-grid three">
                    <div class="field-item">
                        <label>UNIDAD: *</label>
                        <select name="unidad_id" required data-consult-unit-select></select>
                    </div>
                    <div class="field-item">
                        <label>NUMERO: *</label>
                        <input name="numero" placeholder="Ej. 1" required>
                    </div>
                    <div class="field-item">
                        <label>TURNO: *</label>
                        <select name="turno_id" required data-consult-shift-select></select>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="modal-btn">GUARDAR</button>
                    <button type="button" class="modal-btn secondary" data-close-modal>CANCELAR</button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?= config_e(base_url('assets/js/configuracion.js')) ?>"></script>
</body>
</html>
