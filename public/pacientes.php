<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';

$user = require_fresh_user();
if ((bool)($user['debe_cambiar_password'] ?? false)) redirect(base_url('index.php?r=password_change'));
$canConfig = Auth::can('ADMINISTRADOR');
$canReports = Auth::can('ADMINISTRADOR', 'SUPERVISOR');
$apiUrl = base_url('api/patients.php');
$csrf = Security::csrfToken();
$flashes = take_flash();
$headerPrefijo = trim((string)($user['prefijo'] ?? 'L.E.'));
$headerUserDisplay = trim(($headerPrefijo !== '' ? $headerPrefijo . ' ' : '') . trim((string)($user['nombre'] ?? '')));
$headerContextParts = array_filter([
    trim((string)($user['unidad_nombre'] ?? '')),
    trim((string)($user['consultorio_clave'] ?? $user['consultorio_nombre'] ?? '')),
    trim((string)($user['turno_nombre'] ?? '')),
], static fn(string $v): bool => $v !== '');
$headerContextDisplay = $headerContextParts ? implode(' · ', $headerContextParts) : 'ÁMBITO GENERAL';

function patients_e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function tri_select_options(): string {
    return '<option value="">NO ESPECIFICADO</option><option value="1">SI</option><option value="0">NO</option>';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= patients_e($csrf) ?>">
    <link rel="stylesheet" href="<?= patients_e(base_url('assets/css/patients.css?v=' . filemtime(__DIR__ . '/assets/css/patients.css'))) ?>">
<link rel="icon" href="/enfermeria/public/favicon.ico"></head>
<body class="patients-page" data-patients-app data-api-url="<?= patients_e($apiUrl) ?>">
    <!-- Topbar Institucional -->
    <header class="patients-topbar">
        <a class="brand" href="<?= patients_e(base_url('pacientes.php')) ?>">
            <span class="brand-title">SRAE</span>
            <span class="brand-sub">Sistema de Registro de Atención de Enfermería</span>
        </a>
        <nav aria-label="Navegación principal">
            <a class="active" href="<?= patients_e(base_url('pacientes.php')) ?>"><strong>Pacientes</strong></a>
            <a href="<?= patients_e(base_url('index.php?r=reports')) ?>">Reportes</a>
            <?php if ($canConfig): ?>
                <a href="<?= patients_e(base_url('configuracion.php')) ?>">Configuración</a>
            <?php endif; ?>
            <div class="nav-user" data-profile-api-url="<?= patients_e(base_url('api/patients.php')) ?>">
                <button type="button" class="nav-user-name nav-user-trigger" data-header-user-name title="Clic para editar mi prefijo y nombre"><?= patients_e($headerUserDisplay) ?></button>
                <button type="button" class="nav-user-unit nav-user-trigger" data-header-user-context title="Clic para cambiar consultorio y turno"><?= patients_e($headerContextDisplay) ?></button>
            </div>
            <a class="nav-logout" href="<?= patients_e(base_url('index.php?r=logout')) ?>">Cerrar Sesión</a>
        </nav>
    </header>

    <main class="patients-shell">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert <?= patients_e((string)$flash['type']) ?>"><?= patients_e((string)$flash['message']) ?></div>
        <?php endforeach; ?>

        <!-- Barra de Filtros (Página 1 PDF) -->
        <section class="patients-filter-card" aria-label="Filtros de pacientes">
            <form id="patient-filters" class="patient-filters" autocomplete="off">
                <div class="filter-group">
                    <label for="filter-date">FECHA DE CONSULTA</label>
                    <input type="date" id="filter-date" name="fecha" class="filter-input filter-input-date" data-filter-date>
                </div>
                <div class="filter-group">
                    <label for="filter-query">PACIENTE</label>
                    <input type="search" id="filter-query" name="q" placeholder="2206861799 o NOMBRE" class="filter-input filter-input-query" data-filter-query>
                </div>
                <button type="submit" class="button-buscar">BUSCAR</button>
                <div class="filter-actions">
                    <button type="button" class="button-registrar-paciente" data-open-register>Registrar paciente</button>
                </div>
            </form>
        </section>

        <!-- Tabla de Consultas/Atenciones (Página 1 PDF) -->
        <section class="patients-table-card" aria-label="Listado de atenciones">
            <div class="patients-table-scroll">
                <table class="patients-table">
                    <thead>
                        <tr>
                            <th class="col-date">FECHA / HORA</th>
                            <th class="col-patient">PACIENTE</th>
                            <th class="col-nss">NSS</th>
                            <th class="col-clinic">CONS./TURNO</th>
                            <th class="col-staff">ATENDIO</th>
                            <th class="col-actions">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody data-attention-rows>
                        <tr><td colspan="6" style="text-align:center; padding: 24px; color: var(--srae-text-muted);">Cargando registros...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="table-status-bar">
                <span data-table-status>Consultando información...</span>
                <span>Sistema diseñado para la captura de formato de Censo AMMATES Y Hoja atención integral. V. 1.0</span>
            </div>
        </section>
    </main>

    <footer class="patients-footer">
        Sistema diseñado para la captura de formato de Censo AMMATES Y Hoja atención integral. V. 1.0
    </footer>

    <div class="toast-region" aria-live="polite" data-toast-region></div>

    <!-- ==========================================================================
         MODAL 1: REGISTRO DE PACIENTE (Página 2 PDF)
         ========================================================================== -->
    <div class="modal-layer" data-modal="patient" hidden>
        <div class="modal-card modal-patient-form" role="dialog" aria-modal="true" aria-labelledby="patient-modal-title" tabindex="-1">
            <div class="modal-header-line">
                <h2 id="patient-modal-title" class="modal-title">REGISTRO DE PACIENTE</h2>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>
            <form id="patient-form" data-modal-form>
                <input type="hidden" name="public_id">
                <div class="form-error" data-form-error style="color: #c62828; margin-bottom: 12px; font-weight: 600;" hidden></div>

                <div class="modal-section-title">DATO GENERALES:</div>
                <div class="field-grid three">
                    <div class="field-item">
                        <label>NOMBRE DEL PACIENTE (PATERNO)</label>
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
                        <label>NSS * (10 DÍGITOS)</label>
                        <input name="nss" placeholder="10 DÍGITOS" required maxlength="10" inputmode="numeric" pattern="[0-9]{10}">
                    </div>
                    <div class="field-item">
                        <label>DV *</label>
                        <input name="dv" placeholder="DV" required maxlength="1" inputmode="numeric" pattern="[0-9]">
                    </div>
                    <div class="field-item">
                        <label>AGREGADO * (8 CARACTERES)</label>
                        <input name="agregado" placeholder="AGREGADO" required maxlength="8" pattern="[A-Za-z0-9]{8}" data-uppercase>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>CURP (18 CARACTERES)</label>
                        <input name="curp" placeholder="18 CARACTERES" maxlength="18" data-uppercase pattern="[A-Za-z0-9]{18}">
                    </div>
                    <div class="field-item">
                        <label>ESCOLARIDAD</label>
                        <input name="escolaridad" list="lista-escolaridad" placeholder="SELECCIONA O ESCRIBE" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>OCUPACIÓN</label>
                        <input name="ocupacion" list="lista-ocupacion" placeholder="SELECCIONA O ESCRIBE" data-uppercase>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>ESTADO CIVIL</label>
                        <input name="estado_civil" list="lista-estado-civil" placeholder="SELECCIONA O ESCRIBE" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>FECHA NAC:</label>
                        <input type="date" name="fecha_nacimiento">
                    </div>
                    <div class="field-item">
                        <label>LUGAR DE NACIMIENTO</label>
                        <input name="lugar_nacimiento" data-uppercase>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>GENERO</label>
                        <select name="sexo">
                            <option value="">NO ESPECIFICADO</option>
                            <option value="F">Femenino</option>
                            <option value="M">Masculino</option>
                        </select>
                    </div>
                    <div class="field-item">
                        <label>INDIGENA:</label>
                        <select name="indigena"><?= tri_select_options() ?></select>
                    </div>
                    <div class="field-item">
                        <label>AFROAMERICANO:</label>
                        <select name="afromexicano"><?= tri_select_options() ?></select>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>DISCAPACIDAD:</label>
                        <select name="discapacidad"><?= tri_select_options() ?></select>
                    </div>
                    <div class="field-item">
                        <label>CONSULTORIO:</label>
                        <select name="consultorio_id" data-patient-consultorio-select>
                            <option value="">SIN ASIGNAR</option>
                        </select>
                    </div>
                    <div class="field-item">
                        <label>TURNO:</label>
                        <select name="turno_id" data-patient-turno-select>
                            <option value="">SIN ASIGNAR</option>
                        </select>
                    </div>
                </div>

                <div class="modal-section-title">LUGAR DE RESIDENCIA:</div>
                <div class="field-grid three">
                    <div class="field-item">
                        <label>PAIS:</label>
                        <input name="pais_residencia" value="MÉXICO" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>ENTIDAD:</label>
                        <input name="entidad_residencia" placeholder="B.C.S." data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>MUNICIPIO:</label>
                        <input name="municipio" placeholder="La Paz" data-uppercase>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>COLONIA:</label>
                        <input name="colonia" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>CALLE Y NUMERO:</label>
                        <input name="calle" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>C.P.:</label>
                        <input name="codigo_postal" maxlength="5" inputmode="numeric">
                    </div>
                </div>

                <div class="modal-section-title">CONTACTO:</div>
                <div class="field-grid two">
                    <div class="field-item">
                        <label>TELEFONO/CEL:</label>
                        <input name="telefono">
                    </div>
                    <div class="field-item">
                        <label>OTRO CONTACTO:</label>
                        <input name="otro_contacto">
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
         MODAL 2: TARJETA DE PACIENTE (Páginas 3 y 4 PDF: Lectura y Edición In Situ)
         ========================================================================== -->
    <div class="modal-layer" data-modal="card" hidden>
        <div class="modal-card modal-patient-card" role="dialog" aria-modal="true" aria-labelledby="card-modal-title" tabindex="-1">
            <div class="modal-header-line">
                <h2 id="card-modal-title" class="modal-title" data-card-title>TARJETA DE PACIENTE</h2>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>

            <!-- Modo Lectura (Página 3 PDF) -->
            <div data-card-view-mode>
                <div data-card-content>
                    <div style="text-align: center; padding: 20px;">Cargando datos del paciente...</div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn" data-card-edit-btn>EDITAR</button>
                </div>
            </div>

            <!-- Modo Edición In-Situ (Página 4 PDF: "EDITAR DATOS DEL PACIENTE") -->
            <form id="card-edit-form" data-card-edit-mode hidden autocomplete="off">
                <input type="hidden" name="public_id">
                <div class="form-error" data-card-form-error style="color: #c62828; margin-bottom: 12px; font-weight: 600;" hidden></div>

                <div class="modal-section-title">DATO GENERALES:</div>
                <div class="field-grid three">
                    <div class="field-item">
                        <label>APELLIDO PATERNO</label>
                        <input name="apellido_paterno" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>APELLIDO MATERNO</label>
                        <input name="apellido_materno" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>NOMBRE(S) *</label>
                        <input name="nombres" required data-uppercase>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>NSS (10 DÍGITOS)</label>
                        <input name="nss" readonly>
                    </div>
                    <div class="field-item">
                        <label>DV *</label>
                        <input name="dv" required maxlength="1" inputmode="numeric">
                    </div>
                    <div class="field-item">
                        <label>AGREGADO</label>
                        <input name="agregado" readonly data-uppercase>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>CURP (18 CARACTERES)</label>
                        <input name="curp" placeholder="18 CARACTERES" maxlength="18" data-uppercase pattern="[A-Za-z0-9]{18}">
                    </div>
                    <div class="field-item">
                        <label>ESCOLARIDAD</label>
                        <input name="escolaridad" list="lista-escolaridad" placeholder="SELECCIONA O ESCRIBE" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>OCUPACIÓN</label>
                        <input name="ocupacion" list="lista-ocupacion" placeholder="SELECCIONA O ESCRIBE" data-uppercase>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>ESTADO CIVIL</label>
                        <input name="estado_civil" list="lista-estado-civil" placeholder="SELECCIONA O ESCRIBE" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>FECHA NAC:</label>
                        <input type="date" name="fecha_nacimiento">
                    </div>
                    <div class="field-item">
                        <label>LUGAR DE NACIMIENTO</label>
                        <input name="lugar_nacimiento" data-uppercase>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>GENERO</label>
                        <select name="sexo">
                            <option value="">NO ESPECIFICADO</option>
                            <option value="F">Femenino</option>
                            <option value="M">Masculino</option>
                        </select>
                    </div>
                    <div class="field-item">
                        <label>INDIGENA:</label>
                        <select name="indigena"><?= tri_select_options() ?></select>
                    </div>
                    <div class="field-item">
                        <label>AFROAMERICANO:</label>
                        <select name="afromexicano"><?= tri_select_options() ?></select>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>DISCAPACIDAD:</label>
                        <select name="discapacidad"><?= tri_select_options() ?></select>
                    </div>
                    <div class="field-item">
                        <label>CONSULTORIO:</label>
                        <select name="consultorio_id" data-patient-consultorio-select>
                            <option value="">SIN ASIGNAR</option>
                        </select>
                    </div>
                    <div class="field-item">
                        <label>TURNO:</label>
                        <select name="turno_id" data-patient-turno-select>
                            <option value="">SIN ASIGNAR</option>
                        </select>
                    </div>
                </div>

                <div class="modal-section-title">LUGAR DE RESIDENCIA:</div>
                <div class="field-grid three">
                    <div class="field-item">
                        <label>PAIS:</label>
                        <input name="pais_residencia" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>ENTIDAD:</label>
                        <input name="entidad_residencia" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>MUNICIPIO:</label>
                        <input name="municipio" data-uppercase>
                    </div>
                </div>

                <div class="field-grid three">
                    <div class="field-item">
                        <label>COLONIA:</label>
                        <input name="colonia" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>CALLE Y NUMERO:</label>
                        <input name="calle" data-uppercase>
                    </div>
                    <div class="field-item">
                        <label>C.P.:</label>
                        <input name="codigo_postal" maxlength="5" inputmode="numeric">
                    </div>
                </div>

                <div class="modal-section-title">CONTACTO:</div>
                <div class="field-grid two">
                    <div class="field-item">
                        <label>TELEFONO/CEL:</label>
                        <input name="telefono">
                    </div>
                    <div class="field-item">
                        <label>OTRO CONTACTO:</label>
                        <input name="otro_contacto">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="modal-btn">GUARDAR</button>
                    <button type="button" class="modal-btn secondary" data-card-cancel-edit-btn>CANCELAR</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 3: NUEVA ATENCIÓN (Página 5 PDF - ➕)
         ========================================================================== -->
    <div class="modal-layer" data-modal="attention" hidden>
        <div class="modal-card modal-attention-form" role="dialog" aria-modal="true" aria-labelledby="attention-modal-title" tabindex="-1">
            <div class="modal-header-line">
                <div class="attention-top-info">
                    <span class="attention-patient-name" data-attention-patient-name>ROMERO HIGUERA GRECIA MAYLETH</span>
                    <span class="attention-patient-nss" data-attention-patient-nss>2206861799-6 3F2026OR</span>
                </div>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>

            <div class="modal-section-title" data-attention-modal-title style="text-align: center; font-size: 15px; margin: 0 0 12px; font-weight: 800;">REGISTRAR ATENCIÓN</div>

            <form id="attention-form" data-modal-form autocomplete="off">
                <input type="hidden" name="id_atencion" value="">
                <input type="hidden" name="attention_ref" value="">
                <input type="hidden" name="patient_ref">
                <div class="form-error" data-form-error style="color: #c62828; margin-bottom: 8px; font-weight: 600; font-size: 12px;" hidden></div>
                <div data-attention-context hidden></div>

                <div class="attention-layout">
                    <!-- Columna Izquierda -->
                    <div class="attention-column-left">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <div class="modal-section-title" style="margin: 0;">ACOMPAÑANTE</div>
                            <label class="check-inline">
                                <span>SIN ACOMPAÑANTE:</span>
                                <input type="checkbox" name="sin_acompanante" value="1" data-no-companion>
                            </label>
                        </div>

                        <fieldset data-companion-fields class="companion-fieldset">
                            <!-- Fila 1 -->
                            <div class="field-item">
                                <label>APELLIDO PATERNO</label>
                                <input name="acompanante_apellido_paterno" placeholder="PATERNO" data-uppercase>
                            </div>
                            <div class="field-item">
                                <label>APELLIDO MATERNO</label>
                                <input name="acompanante_apellido_materno" placeholder="MATERNO" data-uppercase>
                            </div>
                            <div class="field-item">
                                <label>NOMBRE(S)</label>
                                <input name="acompanante_nombres" placeholder="NOMBRE(S)" data-uppercase>
                            </div>

                            <!-- Fila 2 -->
                            <div class="field-item">
                                <label>FECHA NAC:</label>
                                <input type="date" name="acompanante_fecha_nacimiento">
                            </div>
                            <div class="field-item">
                                <label>LUGAR DE NACIMIENTO</label>
                                <input name="acompanante_lugar_nacimiento" data-uppercase>
                            </div>
                            <div class="field-item">
                                <label>GÉNERO</label>
                                <select name="acompanante_sexo">
                                    <option value="">NO ESPECIFICADO</option>
                                    <option value="F">Femenino</option>
                                    <option value="M">Masculino</option>
                                </select>
                            </div>

                            <!-- Fila 3 -->
                            <div class="field-item">
                                <label>PARENTESCO:</label>
                                <input name="acompanante_parentesco" placeholder="MADRE / PADRE" data-uppercase>
                            </div>
                            <div class="field-item companion-obs-span">
                                <label>OBSERVACIONES:</label>
                                <textarea name="acompanante_observaciones" rows="2" data-uppercase placeholder="OBSERVACIONES..."></textarea>
                            </div>
                        </fieldset>

                        <div class="modal-section-title">SIGNOS VITALES</div>
                        <div class="field-grid three">
                            <div class="field-item">
                                <label>TEMPERATURA:</label>
                                <input type="number" step="0.1" name="temperatura" placeholder="°C">
                            </div>
                            <div class="field-item">
                                <label>FREC. CARDIACA:</label>
                                <input type="number" name="frecuencia_cardiaca" placeholder="LPM">
                            </div>
                            <div class="field-item">
                                <label>FREC. RESP.:</label>
                                <input type="number" name="frecuencia_respiratoria" placeholder="RPM">
                            </div>
                        </div>

                        <div class="modal-section-title">SOMATOMETRÍA</div>
                        <div class="field-grid five">
                            <div class="field-item">
                                <label>PESO:</label>
                                <input type="number" step="0.01" name="peso" placeholder="kg">
                            </div>
                            <div class="field-item">
                                <label>TALLA:</label>
                                <input type="number" step="0.01" name="talla" placeholder="m">
                            </div>
                            <div class="field-item">
                                <label>PC:</label>
                                <input type="number" step="0.01" name="perimetro_cefalico" placeholder="m">
                            </div>
                            <div class="field-item">
                                <label>PT:</label>
                                <input type="number" step="0.01" name="perimetro_toracico" placeholder="m">
                            </div>
                            <div class="field-item">
                                <label>PA:</label>
                                <input type="number" step="0.01" name="perimetro_abdominal" placeholder="m">
                            </div>
                        </div>

                        <div class="field-grid three">
                            <div class="field-item">
                                <label>VITAMINA A:</label>
                                <input type="date" name="vitamina_a_fecha">
                            </div>
                            <div class="field-item">
                                <label>DESPARACITACIÓN:</label>
                                <input type="date" name="desparasitacion_fecha">
                            </div>
                            <div class="field-item">
                                <label>CEPILLADO DENTAL:</label>
                                <input type="date" name="cepillado_dental_fecha">
                            </div>
                        </div>

                        <div class="agudeza-visual-inline">
                            <span class="agudeza-title">AGUDEZA VISUAL</span>
                            <div class="agudeza-box">
                                <label>O.D.</label>
                                <input name="agudeza_visual_od" placeholder="20/20" data-uppercase>
                            </div>
                            <div class="agudeza-box">
                                <label>O.I.</label>
                                <input name="agudeza_visual_oi" placeholder="20/20" data-uppercase>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha -->
                    <div class="attention-column-right">
                        <div class="field-item">
                            <label class="modal-section-title">EXPLORACIÓN FÍSICA.</label>
                            <textarea name="exploracion_fisica" data-uppercase></textarea>
                        </div>

                        <div class="field-item">
                            <label class="modal-section-title">NOTA DE ENFERMERÍA.</label>
                            <textarea name="nota_enfermeria" data-uppercase></textarea>
                        </div>

                        <div class="field-item">
                            <label class="modal-section-title">EDUCACIÓN PARA LA SALUD</label>
                            <div data-education-topics style="margin-bottom: 2px;"></div>
                            <textarea name="educacion_salud_texto" data-uppercase></textarea>
                        </div>

                        <div class="field-item">
                            <label class="modal-section-title">OBSERVACIONES.</label>
                            <textarea name="observaciones_atencion" data-uppercase></textarea>
                        </div>

                        <div class="attention-actions-bar">
                            <button type="button" class="modal-btn prostate-btn-editar" data-attention-edit-btn hidden>EDITAR</button>
                            <button type="submit" class="modal-btn" data-attention-save-btn>GUARDAR</button>
                            <button type="button" class="modal-btn secondary" data-close-modal>CANCELAR</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 4: ESQUEMA DE VACUNACION (Página 6 PDF - 🦠)
         ========================================================================== -->
    <div class="modal-layer" data-modal="vaccines" hidden>
        <div class="modal-card modal-vaccines-form" role="dialog" aria-modal="true" aria-labelledby="vaccines-modal-title" tabindex="-1">
            <div class="modal-header-line">
                <div class="attention-top-info">
                    <span class="attention-patient-name" data-vaccine-patient-name>ROMERO HIGUERA GRECIA MAYLETH</span>
                    <span class="attention-patient-nss" data-vaccine-patient-nss>2206861799-6 3F2026OR</span>
                </div>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>

            <div class="vaccine-toolbar">
                <div class="vaccine-toolbar-left">
                    <div id="vaccines-modal-title" class="modal-section-title vaccine-modal-heading">ESQUEMA DE VACUNACION</div>
                    <span class="vaccine-toolbar-hint" data-vaccine-mode-hint>Haz clic o doble clic sobre una fecha en verde o activa "Editar Registro" para corregir dosis aplicadas.</span>
                </div>
                <div class="vaccine-toolbar-right">
                    <span class="vaccine-unsaved-pill" data-vaccine-unsaved-badge hidden>● Cambios pendientes</span>
                    <button type="button" class="btn-vaccine-edit-toggle" data-toggle-vaccine-edit aria-pressed="false" title="Activar o desactivar Modo Edición para corregir dosis registradas">
                        <span class="btn-vaccine-edit-icon" aria-hidden="true">✏️</span>
                        <span data-vaccine-edit-label>Editar Registro</span>
                    </button>
                </div>
            </div>

            <div class="vaccine-feedback-alert" data-vaccine-feedback role="status" aria-live="polite" hidden></div>

            <div class="vaccine-modal-table-wrap">
                <table class="vaccine-grid-table">
                    <thead>
                        <tr>
                            <th>VACUNA</th>
                            <th>1ER</th>
                            <th>2DA</th>
                            <th>3ER</th>
                            <th>4TA</th>
                            <th>REF</th>
                            <th>ADIC</th>
                            <th>ADIC</th>
                            <th>LOTE</th>
                            <th>CAD</th>
                        </tr>
                    </thead>
                    <tbody data-vaccine-grid>
                        <tr><td colspan="10" style="padding: 20px;">Cargando esquema de vacunación...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-btn" data-save-vaccines>GUARDAR</button>
                <button type="button" class="modal-btn secondary" data-close-modal>CANCELAR</button>
            </div>
            <div class="vaccine-confirm-overlay" data-vaccine-confirm-dialog role="alertdialog" aria-modal="true" aria-labelledby="vaccine-confirm-title" hidden>
                <div class="vaccine-confirm-box">
                    <div class="vaccine-confirm-icon" aria-hidden="true">⚠️</div>
                    <h3 id="vaccine-confirm-title" class="vaccine-confirm-title">¿Desea salir sin guardar los cambios del esquema?</h3>
                    <p class="vaccine-confirm-desc">Existen fechas, lotes o caducidades capturadas o modificadas que aún no se han guardado.</p>
                    <div class="vaccine-confirm-actions">
                        <button type="button" class="modal-btn vaccine-btn-keep" data-vaccine-confirm-stay>SEGUIR EDITANDO</button>
                        <button type="button" class="modal-btn secondary vaccine-btn-discard" data-vaccine-confirm-discard>SALIR SIN GUARDAR</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 5: CUESTIONARIO DE DETECCIÓN DE CÁNCER DE PRÓSTATA (PREVENIMSS / CHKT)
         ========================================================================== -->
    <div class="modal-layer" data-modal="prostate" hidden>
        <div class="modal-card modal-prostate-form" role="dialog" aria-modal="true" aria-labelledby="prostate-modal-title" tabindex="-1">
            <div class="modal-header-line">
                <div class="attention-top-info">
                    <span class="attention-patient-name" data-prostate-patient-name>PACIENTE</span>
                    <span class="attention-patient-nss" data-prostate-patient-nss>0000000000-0</span>
                </div>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>

            <div class="prostate-meta-bar">
                <div class="field-item prostate-meta-field">
                    <label for="prostate-fecha">FECHA DE EVALUACIÓN</label>
                    <input type="date" id="prostate-fecha" name="fecha" data-prostate-date>
                </div>
                <div class="field-item prostate-meta-field prostate-meta-group">
                    <label for="prostate-grupo-riesgo">GRUPO DE RIESGO OBJETIVO</label>
                    <select id="prostate-grupo-riesgo" name="grupo_riesgo" data-prostate-risk-group>
                        <option value="40_44_AHF">Hombres 40 a 44 años con antecedente heredofamiliar</option>
                        <option value="45_74" selected>Hombres 45 a 74 años</option>
                    </select>
                </div>
            </div>

            <div class="vaccine-feedback-alert" data-prostate-feedback role="status" aria-live="polite" hidden></div>

            <div class="prostate-table-wrap">
                <table class="prostate-grid-table">
                    <thead>
                        <tr>
                            <th class="col-prostate-q">PREGUNTAS</th>
                            <th>Nunca</th>
                            <th>Una vez cada cinco</th>
                            <th>Menos de la mitad de veces</th>
                            <th>La mitad de las veces</th>
                            <th>Más de la mitad de las veces</th>
                            <th>Casi siempre</th>
                        </tr>
                    </thead>
                    <tbody data-prostate-grid>
                        <tr><td colspan="7" style="padding: 20px; text-align: center;">Cargando cuestionario...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Pie del cuestionario: Autocalculadora clínica en vivo -->
            <div class="prostate-summary-panel">
                <div class="prostate-score-row">
                    <div class="prostate-score-instruction">
                        Sume los números correspondientes a cada una de sus respuestas y valide con la valoración:
                    </div>
                    <div class="prostate-score-total-box">
                        <span class="prostate-score-total-label">PUNTAJE TOTAL</span>
                        <span class="prostate-score-total-number" data-prostate-total-score>0</span>
                    </div>
                    <div class="prostate-valoracion-current">
                        <span class="prostate-valoracion-label">VALORACIÓN CLÍNICA:</span>
                        <span class="prostate-valoracion-badge level-normal" data-prostate-valoracion-badge>Normal</span>
                    </div>
                </div>

                <div class="prostate-scale-grid" data-prostate-scale-cards>
                    <div class="prostate-scale-item active" data-scale-level="Normal">
                        <span class="scale-pts">0 puntos</span>
                        <strong class="scale-name">Normal</strong>
                    </div>
                    <div class="prostate-scale-item" data-scale-level="Sintomatología LEVE">
                        <span class="scale-pts">1 - 7 puntos</span>
                        <strong class="scale-name">Sintomatología LEVE</strong>
                    </div>
                    <div class="prostate-scale-item" data-scale-level="Sintomatología MODERADA">
                        <span class="scale-pts">8 - 19 puntos</span>
                        <strong class="scale-name">Sintomatología MODERADA</strong>
                    </div>
                    <div class="prostate-scale-item" data-scale-level="Sintomatología SEVERA">
                        <span class="scale-pts">20 o más puntos</span>
                        <strong class="scale-name">Sintomatología SEVERA</strong>
                    </div>
                </div>
            </div>

            <div class="modal-actions prostate-actions-bar">
                <button type="button" class="modal-btn prostate-btn-nuevo" data-prostate-new hidden>NUEVO</button>
                <button type="button" class="modal-btn prostate-btn-editar" data-prostate-edit hidden>EDITAR</button>
                <button type="button" class="modal-btn btn-print-prostate" data-prostate-print hidden>IMPRIMIR FORMATO</button>
                <button type="button" class="modal-btn prostate-save-btn" data-save-prostate>GUARDAR</button>
                <button type="button" class="modal-btn secondary" data-close-modal>CANCELAR</button>
            </div>

            <div class="vaccine-confirm-overlay" data-prostate-confirm-dialog role="alertdialog" aria-modal="true" aria-labelledby="prostate-confirm-title" hidden>
                <div class="vaccine-confirm-box">
                    <div class="vaccine-confirm-icon" aria-hidden="true">⚠️</div>
                    <h3 id="prostate-confirm-title" class="vaccine-confirm-title">¿Desea salir sin guardar los cambios del cuestionario?</h3>
                    <p class="vaccine-confirm-desc">Existen respuestas o datos modificados en el cuestionario de próstata que aún no se han guardado.</p>
                    <div class="vaccine-confirm-actions">
                        <button type="button" class="modal-btn vaccine-btn-keep" data-prostate-confirm-stay>SEGUIR EDITANDO</button>
                        <button type="button" class="modal-btn secondary vaccine-btn-discard" data-prostate-confirm-discard>SALIR SIN GUARDAR</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 6: IMPRESIÓN DE FORMATOS (📄)
         ========================================================================== -->
    <div class="modal-layer" data-modal="print" hidden>
        <div class="modal-card modal-print-dialog" role="dialog" aria-modal="true" aria-labelledby="print-modal-title" tabindex="-1">
            <div class="modal-header-line">
                <h2 id="print-modal-title" class="modal-title">FORMATOS DE ENFERMERÍA</h2>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>
            <p style="font-size: 13px; color: var(--srae-text-muted); margin-bottom: 20px;">Selecciona el formato institucional que deseas generar e imprimir:</p>
            <div class="print-options-grid">
                <button type="button" class="btn-print-option" data-print="censo">CENSO AMMATES</button>
                <button type="button" class="btn-print-option" data-print="integral">HOJA DE ATENCIÓN INTEGRAL</button>
                <button type="button" class="btn-print-option" data-print="both">IMPRIMIR AMBOS</button>
                <button type="button" class="btn-print-option btn-print-prostate" data-print="prostata">DETECCIÓN DE CÁNCER DE PRÓSTATA</button>
                <button type="button" class="btn-print-option btn-print-colon" data-print="colon">DETECCIÓN DE CÁNCER DE COLON Y RECTO</button>
                <button type="button" class="btn-print-option btn-print-mental-health" data-print="salud_mental">CUESTIONARIO DE SALUD MENTAL</button>
            </div>
            <div class="modal-actions" style="margin-top: 10px;">
                <button type="button" class="modal-btn secondary" data-close-modal>CANCELAR</button>
            </div>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 7: SELECTOR COMPACTO DE HISTORIAL POR FECHAS
         ========================================================================== -->
    <div class="modal-layer" data-modal="history-selector" hidden>
        <div class="modal-card modal-history-selector" role="dialog" aria-modal="true" aria-labelledby="history-selector-title" tabindex="-1">
            <div class="history-selector-header">
                <div class="history-selector-titles">
                    <h2 id="history-selector-title" class="history-selector-module" data-history-module-title>HISTORIAL DE REGISTROS</h2>
                    <span class="history-selector-patient" data-history-patient-name>PACIENTE</span>
                </div>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>
            <p class="history-selector-hint">Selecciona una fecha para consultar o editar su registro:</p>
            <div class="history-dates-list" data-history-dates-list></div>
            <div class="history-selector-footer">
                <button type="button" class="btn-history-new" data-history-new-btn>+ Nueva Atención</button>
            </div>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 8: DETECCIÓN DE CÁNCER DE COLON Y RECTO (CAPTURA DIRECTA E IMPRESIÓN)
         ========================================================================== -->
    <div class="modal-layer" data-modal="colon" hidden>
        <div class="modal-card modal-colon-form" role="dialog" aria-modal="true" aria-labelledby="colon-modal-title" tabindex="-1">
            <div class="modal-header-line">
                <div class="attention-top-info">
                    <span class="attention-patient-name" data-colon-patient-name>PACIENTE</span>
                    <span class="attention-patient-nss" data-colon-patient-nss>0000000000-0</span>
                </div>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>

            <div class="modal-section-title" id="colon-modal-title" style="text-align: center; font-size: 15px; margin: 6px 0 14px; font-weight: 800; color: var(--srae-imss-green);">
                DETECCIÓN DE CÁNCER DE COLON Y RECTO
            </div>

            <form id="colon-form" data-modal-form autocomplete="off">
                <input type="hidden" name="colon_patient_ref" data-colon-patient-ref>

                <div class="colon-meta-bar">
                    <div class="field-item colon-meta-field">
                        <label for="colon-up">UP</label>
                        <input type="text" id="colon-up" name="up" data-colon-up placeholder="" data-uppercase>
                    </div>
                    <div class="field-item colon-meta-field">
                        <label for="colon-cita-lab">CITA LABORATORIO</label>
                        <input type="date" id="colon-cita-lab" name="cita_laboratorio" data-colon-cita-lab>
                    </div>
                    <div class="field-item colon-meta-field">
                        <label for="colon-cita-eemf">CITA CON EEMF</label>
                        <input type="date" id="colon-cita-eemf" name="cita_eemf" data-colon-cita-eemf>
                    </div>
                </div>

                <div class="vaccine-feedback-alert" data-colon-feedback role="status" aria-live="polite" hidden></div>

                <div class="prostate-table-wrap colon-table-wrap">
                    <table class="prostate-grid-table colon-grid-table">
                        <thead>
                            <tr>
                                <th class="col-colon-q">CUESTIONARIO DE RIESGO</th>
                                <th class="col-colon-opt">SÍ</th>
                                <th class="col-colon-opt">NO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="prostate-question-cell">
                                    <span class="prostate-q-number">1.</span>
                                    <span>¿Alguno de tus familiares directos (padres, hijos, hermanos) padece o padeció cáncer?</span>
                                </td>
                                <td class="prostate-choice-cell">
                                    <label class="prostate-choice-label">
                                        <input type="radio" class="prostate-choice-radio" name="colon_q1" value="SI">
                                    </label>
                                </td>
                                <td class="prostate-choice-cell">
                                    <label class="prostate-choice-label">
                                        <input type="radio" class="prostate-choice-radio" name="colon_q1" value="NO" checked>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="prostate-question-cell">
                                    <span class="prostate-q-number">2.</span>
                                    <span>¿Algún médico le ha diagnosticado Enfermedad Inflamatoria del Intestino, como Enfermedad de Crohn, Colitis Ulcerosa o CUCI?</span>
                                </td>
                                <td class="prostate-choice-cell">
                                    <label class="prostate-choice-label">
                                        <input type="radio" class="prostate-choice-radio" name="colon_q2" value="SI">
                                    </label>
                                </td>
                                <td class="prostate-choice-cell">
                                    <label class="prostate-choice-label">
                                        <input type="radio" class="prostate-choice-radio" name="colon_q2" value="NO" checked>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="prostate-question-cell">
                                    <span class="prostate-q-number">3.</span>
                                    <span>¿Consumes verduras, 5 o más porciones por día?</span>
                                </td>
                                <td class="prostate-choice-cell">
                                    <label class="prostate-choice-label">
                                        <input type="radio" class="prostate-choice-radio" name="colon_q3" value="SI">
                                    </label>
                                </td>
                                <td class="prostate-choice-cell">
                                    <label class="prostate-choice-label">
                                        <input type="radio" class="prostate-choice-radio" name="colon_q3" value="NO" checked>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="prostate-question-cell">
                                    <span class="prostate-q-number">4.</span>
                                    <span>¿Consumes carnes rojas, 5 o más veces por semana? (res, cerdo, chivo, borrego, etcétera)</span>
                                </td>
                                <td class="prostate-choice-cell">
                                    <label class="prostate-choice-label">
                                        <input type="radio" class="prostate-choice-radio" name="colon_q4" value="SI">
                                    </label>
                                </td>
                                <td class="prostate-choice-cell">
                                    <label class="prostate-choice-label">
                                        <input type="radio" class="prostate-choice-radio" name="colon_q4" value="NO" checked>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="prostate-question-cell">
                                    <span class="prostate-q-number">5.</span>
                                    <span>¿Consumes bebidas alcohólicas?</span>
                                </td>
                                <td class="prostate-choice-cell">
                                    <label class="prostate-choice-label">
                                        <input type="radio" class="prostate-choice-radio" name="colon_q5" value="SI">
                                    </label>
                                </td>
                                <td class="prostate-choice-cell">
                                    <label class="prostate-choice-label">
                                        <input type="radio" class="prostate-choice-radio" name="colon_q5" value="NO" checked>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="colon-note-footer">
                    <em>Población de 50 a 74 años, sin diagnóstico previo de Cáncer de Colon y Recto</em>
                </div>

                <div class="modal-actions" style="margin-top: 16px;">
                    <button type="submit" class="modal-btn prostate-save-btn" data-colon-print-btn>🖨️ IMPRIMIR</button>
                    <button type="button" class="modal-btn secondary" data-close-modal>CANCELAR</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 9: CUESTIONARIO DE SALUD MENTAL (PHQ-4 + PHQ-TP)
         ========================================================================== -->
    <div class="modal-layer" data-modal="mental-health" hidden>
        <div class="modal-card modal-prostate-form modal-mental-health-form" role="dialog" aria-modal="true" aria-labelledby="mh-modal-title" tabindex="-1">
            <div class="modal-header-line">
                <div class="attention-top-info">
                    <span class="attention-patient-name" id="mh-modal-title" data-mh-patient-name>PACIENTE</span>
                    <span class="attention-patient-nss" data-mh-patient-nss>0000000000-0</span>
                </div>
                <button type="button" class="modal-close-btn" data-close-modal aria-label="Cerrar">×</button>
            </div>

            <div class="prostate-meta-bar mh-meta-bar">
                <div class="field-item prostate-meta-field">
                    <label for="mh-fecha">FECHA DE EVALUACIÓN</label>
                    <input type="date" id="mh-fecha" name="fecha" data-mh-date>
                </div>
            </div>

            <div class="vaccine-feedback-alert" data-mh-feedback role="status" aria-live="polite" hidden></div>

            <!-- Sección 1: PHQ-4 -->
            <div class="prostate-table-wrap mh-table-wrap">
                <table class="prostate-grid-table mh-grid-table">
                    <thead>
                        <tr>
                            <th class="col-prostate-q">En los últimos catorce días, ¿con qué frecuencia le ha molestado alguno de los siguientes problemas?</th>
                            <th>Nunca</th>
                            <th>Varios días</th>
                            <th>Más de la mitad de los días</th>
                            <th>Casi cada día</th>
                        </tr>
                    </thead>
                    <tbody data-mh-phq4-grid>
                        <tr><td colspan="5" style="padding: 20px; text-align: center;">Cargando cuestionario...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Sección 2: PHQ-TP (Pregunta 5) -->
            <div class="mh-phqtp-panel" data-mh-phqtp-box>
                <div class="mh-phqtp-question">
                    <span class="mh-phqtp-tag">PHQ-TP</span>
                    <span class="mh-phqtp-text" data-mh-q5-text>5. En las últimas 4 semanas, ¿ha tenido algún ataque de ansiedad o sensación repentina de pánico o miedo?</span>
                </div>
                <div class="mh-phqtp-options" data-mh-q5-options>
                    <label class="mh-phqtp-choice">
                        <input type="radio" class="prostate-choice-radio" name="mh_q5" value="1" data-mh-q5-radio>
                        <span>Sí</span>
                    </label>
                    <label class="mh-phqtp-choice">
                        <input type="radio" class="prostate-choice-radio" name="mh_q5" value="0" data-mh-q5-radio>
                        <span>No</span>
                    </label>
                </div>
            </div>

            <!-- Pie del cuestionario: Tabla Oficial RESULTADO DE SALUD MENTAL -->
            <div class="prostate-summary-panel mh-summary-panel">
                <div class="mh-results-header-bar">
                    <span class="mh-results-title">RESULTADO DE SALUD MENTAL</span>
                    <div class="mh-live-badges">
                        <span class="mh-live-pill">
                            <strong>INTERPRETACIÓN:</strong>
                            <span class="prostate-valoracion-badge level-normal" data-mh-valoracion-badge>SIN RIESGO DETECTADO / NORMAL</span>
                        </span>
                        <span class="mh-live-pill">
                            <strong>UP:</strong>
                            <span class="mh-up-badge" data-mh-up-badge>—</span>
                        </span>
                        <span class="mh-live-pill">
                            <strong>SITIO DE ENVIO:</strong>
                            <span class="mh-envio-badge" data-mh-envio-badge>—</span>
                        </span>
                    </div>
                </div>

                <div class="mh-results-table-wrap">
                    <table class="mh-results-table" data-mh-results-table>
                        <thead>
                            <tr>
                                <th>PREGUNTAS</th>
                                <th>RESULTADO</th>
                                <th>INTERPRETACIÓN</th>
                                <th>UP</th>
                                <th>SITIO DE ENVIO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr data-mh-result-row="ansiedad">
                                <td class="mh-col-center"><strong>1 Y 2</strong></td>
                                <td class="mh-col-center">&gt;3 <span class="mh-current-score" data-mh-score-p12>(Actual: 0 pts)</span></td>
                                <td><strong>PB TRASTORNO DE ANSIEDAD</strong></td>
                                <td class="mh-col-center"><span class="mh-up-tag">UP307</span></td>
                                <td class="mh-col-center"><strong>ENVIO MF</strong></td>
                            </tr>
                            <tr data-mh-result-row="depresion">
                                <td class="mh-col-center"><strong>3 Y 4</strong></td>
                                <td class="mh-col-center">3 O MAS <span class="mh-current-score" data-mh-score-p34>(Actual: 0 pts)</span></td>
                                <td><strong>PB TRASTORNO DEPRESIVO</strong></td>
                                <td class="mh-col-center"><span class="mh-up-tag">UP306</span></td>
                                <td class="mh-col-center"><strong>ENVIO MF</strong></td>
                            </tr>
                            <tr data-mh-result-row="panico">
                                <td class="mh-col-center"><strong>5</strong></td>
                                <td class="mh-col-center">SI <span class="mh-current-score" data-mh-score-p5>(Actual: No)</span></td>
                                <td><strong>PB TRASTORNO DE PANICO, MIEDO</strong></td>
                                <td class="mh-col-center"><span class="mh-up-tag">UP308</span></td>
                                <td class="mh-col-center"><strong>ENVIO MF</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-actions prostate-actions-bar">
                <button type="button" class="modal-btn prostate-btn-editar" data-mh-edit hidden>EDITAR</button>
                <button type="button" class="modal-btn btn-print-mental-health" data-mh-print hidden>IMPRIMIR FORMATO</button>
                <button type="button" class="modal-btn prostate-save-btn" data-save-mh>GUARDAR</button>
                <button type="button" class="modal-btn secondary" data-close-modal>CANCELAR</button>
            </div>

            <div class="vaccine-confirm-overlay" data-mh-confirm-dialog role="alertdialog" aria-modal="true" aria-labelledby="mh-confirm-title" hidden>
                <div class="vaccine-confirm-box">
                    <div class="vaccine-confirm-icon" aria-hidden="true">⚠️</div>
                    <h3 id="mh-confirm-title" class="vaccine-confirm-title">¿Desea salir sin guardar los cambios del cuestionario?</h3>
                    <p class="vaccine-confirm-desc">Existen respuestas o datos modificados en el cuestionario de salud mental que aún no se han guardado.</p>
                    <div class="vaccine-confirm-actions">
                        <button type="button" class="modal-btn vaccine-btn-keep" data-mh-confirm-stay>SEGUIR EDITANDO</button>
                        <button type="button" class="modal-btn secondary vaccine-btn-discard" data-mh-confirm-discard>SALIR SIN GUARDAR</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <datalist id="lista-escolaridad">
        <option value="NINGUNA">
        <option value="PRIMARIA INCOMPLETA">
        <option value="PRIMARIA COMPLETA">
        <option value="SECUNDARIA INCOMPLETA">
        <option value="SECUNDARIA COMPLETA">
        <option value="BACHILLERATO / PREPARATORIA">
        <option value="CARRERA TÉCNICA / COMERCIAL">
        <option value="LICENCIATURA / PROFESIONAL">
        <option value="POSGRADO (MAESTRÍA / DOCTORADO)">
    </datalist>

    <datalist id="lista-ocupacion">
        <option value="ESTUDIANTE">
        <option value="HOGAR / AMA DE CASA">
        <option value="EMPLEADO(A)">
        <option value="COMERCIANTE">
        <option value="OBRERO(A)">
        <option value="CAMPESINO / AGRICULTOR">
        <option value="JUBILADO(A) / PENSIONADO(A)">
        <option value="PROFESIONISTA INDEPENDIENTE">
        <option value="DESEMPLEADO(A)">
        <option value="OTRO">
    </datalist>

    <datalist id="lista-estado-civil">
        <option value="SOLTERO(A)">
        <option value="CASADO(A)">
        <option value="UNIÓN LIBRE">
        <option value="DIVORCIADO(A)">
        <option value="VIUDO(A)">
        <option value="CONCUBINATO">
    </datalist>

    <script src="<?= patients_e(base_url('assets/js/app.js?v=' . filemtime(__DIR__ . '/assets/js/app.js'))) ?>"></script>
    <script src="<?= patients_e(base_url('assets/js/patients.js?v=' . filemtime(__DIR__ . '/assets/js/patients.js'))) ?>"></script>
</body>
</html>

