<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/config/bootstrap.php";

function fetch_attention(PDO $pdo, string $ref): ?array
{
    if (!preg_match("/^[a-f0-9]{32}$/i", $ref)) return null;
    $sql = "SELECT a.*, p.*, p.public_id AS paciente_public_id, a.public_id AS atencion_public_id, u.nombre AS unidad_nombre, c.nombre_descriptivo AS consultorio_nombre, t.nombre AS turno_nombre, e.nombre AS enfermera_nombre, e.matricula FROM atenciones a JOIN pacientes p ON p.paciente_id=a.paciente_id JOIN unidades u ON u.unidad_id=a.unidad_id JOIN consultorios c ON c.consultorio_id=a.consultorio_id JOIN turnos t ON t.turno_id=a.turno_id JOIN usuarios e ON e.usuario_id=a.enfermera_id WHERE a.public_id=?";
    $s = $pdo->prepare($sql);
    $s->execute([$ref]);
    return $s->fetch() ?: null;
}

$route = (string)($_GET["r"] ?? $_POST["r"] ?? "dashboard");

$redirects = [
    "dashboard" => "pacientes.php",
    "patients" => "pacientes.php",
    "config" => "configuracion.php",
    "reports" => "reportes.php",
    "patient_new" => "pacientes.php",
    "patient_edit" => "pacientes.php",
    "attention_new" => "pacientes.php",
    "patient" => "pacientes.php"
];

if (isset($redirects[$route])) {
    redirect(base_url($redirects[$route]));
}

if ($route === "logout") {
    if (Auth::user()) {
        try { audit(db(), Auth::user(), "LOGOUT", "sesion", null, null, []); } catch (Throwable $ignored) {}
    }
    Auth::logout();
    redirect(base_url("index.php?r=login"));
}

function render_minimal_header(string $title): void {
    $flashes = take_flash();
    echo "<!doctype html><html lang=\"es\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">";
    echo "<title>" . h($title) . " - SRAE</title><link rel=\"stylesheet\" href=\"" . h(base_url("assets/css/app.css")) . "\"><link rel=\"icon\" href=\"/enfermeria/public/favicon.ico\"></head><body>";
    
    $u = Auth::user();
    $identity = $u ? "<span class=\"session-user\">" . h((string)$u["nombre"]) . " - " . h((string)$u["matricula"]) . "<small>" . h(trim((string)($u["unidad_nombre"] ?? "") . " - " . (string)($u["consultorio_nombre"] ?? ""), " -")) . "</small></span><a href=\"" . h(base_url("index.php?r=logout")) . "\">Cerrar sesin</a>" : "";
    
    echo "<header class=\"topbar\"><a class=\"brand\" href=\"" . h(base_url("index.php")) . "\">SRAE <small>Sistema de Registro de Atencin de Enfermera</small></a><nav>";
    if ($u) {
        echo "<a href=\"" . h(base_url("pacientes.php")) . "\">Pacientes</a><a href=\"" . h(base_url("reportes.php")) . "\">Reportes</a>";
        if (Auth::can("ADMINISTRADOR")) {
            echo "<a href=\"" . h(base_url("configuracion.php")) . "\">Configuracin</a>";
        }
    }
    echo $identity . "</nav></header><main class=\"container\">";
    foreach ($flashes as $flash) {
        echo "<div class=\"alert " . h($flash["type"]) . "\">" . h($flash["message"]) . "</div>";
    }
}

function render_minimal_footer(): void {
    echo "</main><footer>SRAE - Zona horaria America/Mazatlan - Operacin asociada a la sesin autenticada</footer><script src=\"" . h(base_url("assets/js/app.js")) . "\"></script></body></html>";
}

if ($route === "login") {
    $error = null;
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        Security::verifyCsrf();
        try {
            $pdo = db();
            $logged = Auth::login($pdo, trim(post_string("matricula")), (string)($_POST["password"] ?? ""));
            audit($pdo, $logged, "LOGIN", "sesion", null, null, []);
            redirect(base_url("pacientes.php"));
        } catch (Throwable $e) {
            $error = "Credenciales no vlidas o cuenta bloqueada.";
            try { audit(db(), null, "LOGIN FALLIDO", "sesion", null, null, [], $e->getMessage()); } catch (Throwable $ignored) {}
        }
    }
    render_minimal_header("Iniciar sesin");
    echo "<div class=\"page-heading\"><div><p class=\"eyebrow\">SISTEMA INSTITUCIONAL</p><h1>Iniciar sesin</h1></div></div>";
    echo "<section class=\"card narrow\"><p class=\"muted\">Usa tu matrcula y contrasea institucional.</p>";
    if ($error) echo "<div class=\"alert error\">".h($error)."</div>";
    echo "<form method=\"post\" action=\"".h(base_url("index.php?r=login"))."\" class=\"form-stack\">".Security::csrfInput()."<input type=\"hidden\" name=\"r\" value=\"login\"><label>Matrcula<input name=\"matricula\" required maxlength=\"40\" autocomplete=\"username\"></label><label>Contrasea<input type=\"password\" name=\"password\" required autocomplete=\"current-password\"></label><button class=\"button primary\">Iniciar sesin</button></form></section>";
    render_minimal_footer();
    exit;
}

if ($route === "password_change") {
    $user = require_fresh_user();
    $error = null;
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        Security::verifyCsrf();
        $new = (string)($_POST["password"] ?? "");
        $confirm = (string)($_POST["password_confirm"] ?? "");
        if (strlen($new) < 8 || !hash_equals($new, $confirm)) {
            $error = "La contrasea debe tener al menos 8 caracteres y coincidir en ambos campos.";
        } else {
            $hash = password_hash($new, defined("PASSWORD_ARGON2ID") ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
            $pdo = db();
            $pdo->prepare("UPDATE usuarios SET password_hash=?, debe_cambiar_password=0, intentos_fallidos=0, bloqueado_hasta=NULL WHERE usuario_id=?")->execute([$hash, (int)$user["usuario_id"]]);
            audit($pdo, $user, "CAMBIO DE CONTRASEA", "usuarios", (int)$user["usuario_id"], null, ["password_hash" => "actualizado"]);
            $_SESSION["srae_user"]["debe_cambiar_password"] = 0;
            flash("success", "Contrasea actualizada.");
            redirect(base_url("pacientes.php"));
        }
    }
    render_minimal_header("Cambiar contrasea");
    echo "<div class=\"page-heading\"><div><p class=\"eyebrow\">SEGURIDAD</p><h1>Cambiar contrasea</h1></div></div>";
    echo "<section class=\"card narrow\"><p>Por seguridad debes establecer una contrasea propia antes de continuar (mnimo 8 caracteres).</p>";
    if ($error) echo "<div class=\"alert error\">".h($error)."</div>";
    echo "<form method=\"post\" action=\"".h(base_url("index.php?r=password_change"))."\" class=\"form-stack\">".Security::csrfInput()."<input type=\"hidden\" name=\"r\" value=\"password_change\"><label>Nueva contrasea<input type=\"password\" name=\"password\" minlength=\"8\" required autocomplete=\"new-password\"></label><label>Repite la contrasea<input type=\"password\" name=\"password_confirm\" minlength=\"8\" required autocomplete=\"new-password\"></label><button class=\"button primary\">Guardar contrasea</button></form></section>";
    render_minimal_footer();
    exit;
}

if ($route === "pdf_prostata") {
    $user = require_fresh_user();
    if ((bool)($user["debe_cambiar_password"] ?? false)) {
        redirect(base_url("index.php?r=password_change"));
    }

    try {
        $pdoPdf = db();
        $ref = trim((string)($_GET["ref"] ?? $_GET["patient_ref"] ?? ""));
        if (!preg_match("/^[a-f0-9]{32}$/i", $ref)) {
            http_response_code(404);
            exit("Referencia no válida.");
        }

        // Soportar ref como public_id de paciente o public_id de atención
        $stmtPat = $pdoPdf->prepare("SELECT * FROM pacientes WHERE public_id = ? LIMIT 1");
        $stmtPat->execute([$ref]);
        $patient = $stmtPat->fetch(PDO::FETCH_ASSOC) ?: null;

        $attention = null;
        if (!$patient) {
            $attention = fetch_attention($pdoPdf, $ref);
            if ($attention) {
                $stmtPat = $pdoPdf->prepare("SELECT * FROM pacientes WHERE paciente_id = ? LIMIT 1");
                $stmtPat->execute([(int)$attention["paciente_id"]]);
                $patient = $stmtPat->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }

        if (!$patient) {
            http_response_code(404);
            exit("Paciente no encontrado.");
        }

        $questions = $pdoPdf->query("SELECT id, orden, pregunta FROM cat_preguntas_prostata WHERE activo = 1 ORDER BY orden ASC")->fetchAll(PDO::FETCH_ASSOC);

        $stmtRec = $pdoPdf->prepare(
            "SELECT dcp.*, u.nombre AS enfermera_nombre, u.matricula AS enfermera_matricula,
                    un.clave AS unidad_clave, un.nombre AS unidad_nombre,
                    c.clave AS consultorio_clave, c.nombre_descriptivo AS consultorio_nombre
             FROM dc_prostata dcp
             LEFT JOIN usuarios u ON u.usuario_id = dcp.id_usuario
             LEFT JOIN unidades un ON un.unidad_id = u.unidad_id
             LEFT JOIN consultorios c ON c.consultorio_id = u.consultorio_id
             WHERE dcp.id_paciente = ?
             ORDER BY dcp.fecha DESC, dcp.id DESC
             LIMIT 1"
        );
        $stmtRec->execute([(int)$patient["paciente_id"]]);
        $record = $stmtRec->fetch(PDO::FETCH_ASSOC) ?: null;

        $answers = [];
        if ($record) {
            $stmtAns = $pdoPdf->prepare("SELECT id_cp, valor FROM dc_prostata_respuestas WHERE id_dc_prostata = ?");
            $stmtAns->execute([(int)$record["id"]]);
            foreach ($stmtAns->fetchAll(PDO::FETCH_ASSOC) as $ansRow) {
                $answers[(int)$ansRow["id_cp"]] = (int)$ansRow["valor"];
            }
        }

        $data = array_merge($patient, [
            "fecha" => $record["fecha"] ?? ($attention["fecha"] ?? date("Y-m-d")),
            "grupo_riesgo" => $record["grupo_riesgo"] ?? "45_74",
            "puntaje_total" => isset($record["puntaje_total"]) ? (int)$record["puntaje_total"] : 0,
            "valoracion" => $record["valoracion"] ?? "Normal",
            "enfermera_nombre" => $record["enfermera_nombre"] ?? ($attention["enfermera_nombre"] ?? ($user["nombre"] ?? "")),
            "matricula" => $record["enfermera_matricula"] ?? ($attention["matricula"] ?? ($user["matricula"] ?? "")),
            "unidad_clave" => $record["unidad_clave"] ?? ($user["unidad_clave"] ?? ""),
            "unidad_nombre" => $record["unidad_nombre"] ?? ($attention["unidad_nombre"] ?? ($user["unidad_nombre"] ?? "")),
            "consultorio_clave" => $record["consultorio_clave"] ?? ($user["consultorio_clave"] ?? ""),
            "consultorio_nombre" => $record["consultorio_nombre"] ?? ($attention["consultorio_nombre"] ?? ($user["consultorio_nombre"] ?? "")),
        ]);

        $pdf = new PdfTemplate();
        $pdf->prostata($data, $questions, $answers);

        audit($pdoPdf, $user, "REIMPRESION PDF", "dc_prostata", $record ? (int)$record["id"] : (int)$patient["paciente_id"], null, ["formato" => "pdf_prostata"]);

        header("Content-Type: application/pdf");
        header("Content-Disposition: inline; filename=\"cuestionario_prostata_" . ($patient["nss"] ?? "imss") . ".pdf\"");
        header("Cache-Control: no-store");
        echo $pdf->Output("cuestionario_prostata.pdf", "S");
    } catch (Throwable $e) {
        http_response_code(500);
        echo env_value("APP_ENV", "production") === "development" ? "No fue posible generar el documento: " . h($e->getMessage()) : "No fue posible generar el documento.";
    }
    exit;
}

if ($route === "pdf_colon") {
    $user = require_fresh_user();
    if ((bool)($user["debe_cambiar_password"] ?? false)) {
        redirect(base_url("index.php?r=password_change"));
    }

    try {
        $pdoPdf = db();
        $ref = trim((string)($_GET["ref"] ?? $_GET["patient_ref"] ?? ""));
        $pacienteId = (int)($_GET["paciente_id"] ?? 0);

        $patient = null;
        if ($pacienteId > 0) {
            $stmtPat = $pdoPdf->prepare("SELECT * FROM pacientes WHERE paciente_id = ? LIMIT 1");
            $stmtPat->execute([$pacienteId]);
            $patient = $stmtPat->fetch(PDO::FETCH_ASSOC) ?: null;
        } elseif (preg_match("/^[a-f0-9]{32}$/i", $ref)) {
            $stmtPat = $pdoPdf->prepare("SELECT * FROM pacientes WHERE public_id = ? LIMIT 1");
            $stmtPat->execute([$ref]);
            $patient = $stmtPat->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$patient) {
                $attention = fetch_attention($pdoPdf, $ref);
                if ($attention) {
                    $stmtPat = $pdoPdf->prepare("SELECT * FROM pacientes WHERE paciente_id = ? LIMIT 1");
                    $stmtPat->execute([(int)$attention["paciente_id"]]);
                    $patient = $stmtPat->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }
        }

        if (!$patient) {
            http_response_code(404);
            exit("Paciente no encontrado.");
        }

        // Obtener última atención o datos de adscripción/consultorio
        $stmtLastAtt = $pdoPdf->prepare(
            "SELECT a.*, c.clave consultorio_clave, c.nombre_descriptivo consultorio_nombre,
                    t.clave turno_clave, t.nombre turno_nombre,
                    u.clave unidad_clave, u.nombre unidad_nombre
             FROM atenciones a
             LEFT JOIN consultorios c ON c.consultorio_id = a.consultorio_id
             LEFT JOIN turnos t ON t.turno_id = a.turno_id
             LEFT JOIN unidades u ON u.unidad_id = a.unidad_id
             WHERE a.paciente_id = ? AND a.estado = 'ACTIVA'
             ORDER BY a.fecha DESC, a.hora DESC
             LIMIT 1"
        );
        $stmtLastAtt->execute([(int)$patient["paciente_id"]]);
        $lastAtt = $stmtLastAtt->fetch(PDO::FETCH_ASSOC) ?: [];

        $nombreCompleto = trim(implode(" ", array_filter([
            $patient["apellido_paterno"],
            $patient["apellido_materno"],
            $patient["nombres"],
        ], static fn($v): bool => trim((string)$v) !== "")));

        $fechaHoy = (new DateTimeImmutable("today"))->format("Y-m-d");
        $edadAnios = "";
        if (!empty($patient["fecha_nacimiento"]) && valid_date($patient["fecha_nacimiento"])) {
            $ageParts = PatientDomain::ageParts((string)$patient["fecha_nacimiento"], $fechaHoy);
            $edadAnios = (string)$ageParts[0];
        }

        // Preparar payload para el PDF
        $upParam = trim((string)($_GET["up"] ?? ""));

        $consParam = trim((string)($lastAtt["consultorio_clave"] ?? $lastAtt["consultorio_nombre"] ?? $user["consultorio_nombre"] ?? ""));
        $turnoParam = trim((string)($lastAtt["turno_nombre"] ?? $lastAtt["turno_clave"] ?? ""));

        $pdfData = [
            "paciente_nombre" => $nombreCompleto,
            "nombre_completo" => $nombreCompleto,
            "nss" => (string)$patient["nss"],
            "agregado" => (string)$patient["agregado"],
            "fecha" => $fechaHoy,
            "consultorio" => $consParam,
            "turno" => $turnoParam,
            "edad" => $edadAnios,
            "edad_anios" => $edadAnios,
            "up" => $upParam,
            "cita_laboratorio" => trim((string)($_GET["cita_laboratorio"] ?? "")),
            "cita_eemf" => trim((string)($_GET["cita_eemf"] ?? "")),
            "prefijo" => (string)($user["prefijo"] ?? "L.E."),
            "enfermera_prefijo" => (string)($user["prefijo"] ?? "L.E."),
            "enfermera_nombre" => (string)($user["nombre"] ?? ""),
            "matricula" => (string)($user["matricula"] ?? ""),
        ];

        $answers = [
            1 => (string)($_GET["q1"] ?? "NO"),
            2 => (string)($_GET["q2"] ?? "NO"),
            3 => (string)($_GET["q3"] ?? "NO"),
            4 => (string)($_GET["q4"] ?? "NO"),
            5 => (string)($_GET["q5"] ?? "NO"),
        ];

        audit($pdoPdf, $user, "IMPRESIÓN DETECCIÓN CÁNCER COLON", "pacientes", (int)$patient["paciente_id"], null, [
            "paciente_id" => $patient["paciente_id"],
            "respuestas" => $answers,
        ]);

        $pdf = new PdfTemplate();
        $pdf->colon($pdfData, $answers);
        $pdfOutput = $pdf->Output("deteccion_cancer_colon.pdf", "S");

        while (ob_get_level()) { ob_end_clean(); }
        header("Content-Type: application/pdf");
        header("Content-Disposition: inline; filename=\"deteccion_cancer_colon_" . ($patient["nss"] ?? "imss") . ".pdf\"");
        header("Content-Length: " . strlen($pdfOutput));
        header("Cache-Control: private, max-age=0, must-revalidate");
        header("Pragma: public");
        echo $pdfOutput;
    } catch (Throwable $e) {
        http_response_code(500);
        echo env_value("APP_ENV", "production") === "development" ? "No fue posible generar el documento: " . h($e->getMessage()) : "No fue posible generar el documento.";
    }
    exit;
}

if ($route === "pdf_salud_mental") {
    $user = require_fresh_user();
    if ((bool)($user["debe_cambiar_password"] ?? false)) {
        redirect(base_url("index.php?r=password_change"));
    }

    try {
        $pdoPdf = db();
        $ref = trim((string)($_GET["ref"] ?? $_GET["patient_ref"] ?? ""));
        if (!preg_match("/^[a-f0-9]{32}$/i", $ref)) {
            http_response_code(404);
            exit("Referencia no válida.");
        }

        // Soportar ref como public_id de paciente o public_id de atención
        $stmtPat = $pdoPdf->prepare("SELECT * FROM pacientes WHERE public_id = ? LIMIT 1");
        $stmtPat->execute([$ref]);
        $patient = $stmtPat->fetch(PDO::FETCH_ASSOC) ?: null;

        $attention = null;
        if (!$patient) {
            $attention = fetch_attention($pdoPdf, $ref);
            if ($attention) {
                $stmtPat = $pdoPdf->prepare("SELECT * FROM pacientes WHERE paciente_id = ? LIMIT 1");
                $stmtPat->execute([(int)$attention["paciente_id"]]);
                $patient = $stmtPat->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }

        if (!$patient) {
            http_response_code(404);
            exit("Paciente no encontrado.");
        }

        // Si no venía por ref de atención, consultar la última atención activa para consultorio/unidad
        if (!$attention) {
            $stmtLastAtt = $pdoPdf->prepare(
                "SELECT a.*, c.clave AS consultorio_clave, c.nombre_descriptivo AS consultorio_nombre,
                        un.clave AS unidad_clave, un.nombre AS unidad_nombre
                 FROM atenciones a
                 LEFT JOIN consultorios c ON c.consultorio_id = a.consultorio_id
                 LEFT JOIN unidades un ON un.unidad_id = a.unidad_id
                 WHERE a.paciente_id = ? AND a.estado = 'ACTIVA'
                 ORDER BY a.fecha DESC, a.hora DESC
                 LIMIT 1"
            );
            $stmtLastAtt->execute([(int)$patient["paciente_id"]]);
            $attention = $stmtLastAtt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $questions = $pdoPdf->query("SELECT id, orden, seccion, pregunta FROM cat_preguntas_salud_mental WHERE activo = 1 ORDER BY orden ASC")->fetchAll(PDO::FETCH_ASSOC);

        $requestedDcId = (int)($_GET["id_dc_salud_mental"] ?? $_GET["id_dc_sm"] ?? $_GET["id"] ?? 0);
        if ($requestedDcId > 0) {
            $stmtRec = $pdoPdf->prepare(
                "SELECT dcsm.*, u.prefijo AS enfermera_prefijo, u.nombre AS enfermera_nombre, u.matricula AS enfermera_matricula,
                        un.clave AS unidad_clave, un.nombre AS unidad_nombre,
                        c.clave AS consultorio_clave, c.nombre_descriptivo AS consultorio_nombre
                 FROM dc_salud_mental dcsm
                 LEFT JOIN usuarios u ON u.usuario_id = dcsm.id_usuario
                 LEFT JOIN unidades un ON un.unidad_id = u.unidad_id
                 LEFT JOIN consultorios c ON c.consultorio_id = u.consultorio_id
                 WHERE dcsm.id = ? AND dcsm.id_paciente = ?
                 LIMIT 1"
            );
            $stmtRec->execute([$requestedDcId, (int)$patient["paciente_id"]]);
        } else {
            $stmtRec = $pdoPdf->prepare(
                "SELECT dcsm.*, u.prefijo AS enfermera_prefijo, u.nombre AS enfermera_nombre, u.matricula AS enfermera_matricula,
                        un.clave AS unidad_clave, un.nombre AS unidad_nombre,
                        c.clave AS consultorio_clave, c.nombre_descriptivo AS consultorio_nombre
                 FROM dc_salud_mental dcsm
                 LEFT JOIN usuarios u ON u.usuario_id = dcsm.id_usuario
                 LEFT JOIN unidades un ON un.unidad_id = u.unidad_id
                 LEFT JOIN consultorios c ON c.consultorio_id = u.consultorio_id
                 WHERE dcsm.id_paciente = ?
                 ORDER BY dcsm.fecha DESC, dcsm.id DESC
                 LIMIT 1"
            );
            $stmtRec->execute([(int)$patient["paciente_id"]]);
        }
        $record = $stmtRec->fetch(PDO::FETCH_ASSOC) ?: null;

        $answers = [];
        if ($record) {
            $stmtAns = $pdoPdf->prepare("SELECT id_pregunta, valor FROM dc_salud_mental_respuestas WHERE id_dc_sm = ?");
            $stmtAns->execute([(int)$record["id"]]);
            foreach ($stmtAns->fetchAll(PDO::FETCH_ASSOC) as $ansRow) {
                $answers[(int)$ansRow["id_pregunta"]] = (int)$ansRow["valor"];
            }
        }

        $fechaEval = $record["fecha"] ?? ($attention["fecha"] ?? date("Y-m-d"));
        $edadAnios = "";
        if (!empty($patient["fecha_nacimiento"]) && valid_date($patient["fecha_nacimiento"])) {
            $ageParts = PatientDomain::ageParts((string)$patient["fecha_nacimiento"], valid_date($fechaEval) ? $fechaEval : date("Y-m-d"));
            $edadAnios = (string)$ageParts[0];
        }

        $data = array_merge($patient, [
            "fecha" => $fechaEval,
            "edad" => $edadAnios,
            "edad_anios" => $edadAnios,
            "puntaje_ansiedad" => isset($record["puntaje_ansiedad"]) ? (int)$record["puntaje_ansiedad"] : 0,
            "puntaje_depresion" => isset($record["puntaje_depresion"]) ? (int)$record["puntaje_depresion"] : 0,
            "puntaje_phq4" => isset($record["puntaje_phq4"]) ? (int)$record["puntaje_phq4"] : 0,
            "ataque_panico" => isset($record["ataque_panico"]) ? (int)$record["ataque_panico"] : 0,
            "valoracion" => $record["valoracion"] ?? "SIN RIESGO DETECTADO / NORMAL",
            "clave_up" => $record["clave_up"] ?? "",
            "sitio_envio" => $record["sitio_envio"] ?? "",
            "prefijo" => $record["enfermera_prefijo"] ?? ($user["prefijo"] ?? "L.E."),
            "enfermera_prefijo" => $record["enfermera_prefijo"] ?? ($user["prefijo"] ?? "L.E."),
            "enfermera_nombre" => $record["enfermera_nombre"] ?? ($attention["enfermera_nombre"] ?? ($user["nombre"] ?? "")),
            "matricula" => $record["enfermera_matricula"] ?? ($attention["matricula"] ?? ($user["matricula"] ?? "")),
            "unidad_clave" => $record["unidad_clave"] ?? ($attention["unidad_clave"] ?? ($user["unidad_clave"] ?? "UMF 40")),
            "unidad_nombre" => $record["unidad_nombre"] ?? ($attention["unidad_nombre"] ?? ($user["unidad_nombre"] ?? "UMF 40")),
            "consultorio_clave" => $record["consultorio_clave"] ?? ($attention["consultorio_clave"] ?? ($user["consultorio_clave"] ?? "")),
            "consultorio_nombre" => $record["consultorio_nombre"] ?? ($attention["consultorio_nombre"] ?? ($user["consultorio_nombre"] ?? "")),
        ]);

        $pdf = new PdfTemplate();
        $pdf->saludMental($data, $questions, $answers);
        $pdfOutput = $pdf->Output("cuestionario_salud_mental.pdf", "S");

        audit($pdoPdf, $user, "REIMPRESION PDF", "dc_salud_mental", $record ? (int)$record["id"] : (int)$patient["paciente_id"], null, ["formato" => "pdf_salud_mental"]);

        while (ob_get_level()) { ob_end_clean(); }
        header("Content-Type: application/pdf");
        header("Content-Disposition: inline; filename=\"cuestionario_salud_mental_" . ($patient["nss"] ?? "imss") . ".pdf\"");
        header("Content-Length: " . strlen($pdfOutput));
        header("Cache-Control: private, max-age=0, must-revalidate");
        header("Pragma: public");
        echo $pdfOutput;
    } catch (Throwable $e) {
        http_response_code(500);
        echo env_value("APP_ENV", "production") === "development" ? "No fue posible generar el documento: " . h($e->getMessage()) : "No fue posible generar el documento.";
    }
    exit;
}

if (in_array($route, ["pdf_censo", "pdf_integral", "pdf_both"], true)) {
    $user = require_fresh_user();
    if ((bool)($user["debe_cambiar_password"] ?? false)) {
        redirect(base_url("index.php?r=password_change"));
    }
    
    try {
        $pdoPdf = db();
        $attention = fetch_attention($pdoPdf, (string)($_GET["ref"] ?? ""));
        if (!$attention) {
            http_response_code(404);
            exit("Atencin no encontrada.");
        }
        
        if (!Auth::can("ADMINISTRADOR", "SUPERVISOR") && (int)$user["unidad_id"] !== (int)$attention["unidad_id"]) {
            http_response_code(403);
            exit("No tienes permiso para ver atenciones de esta unidad.");
        }

        $patientSnapshot = json_decode((string)($attention["patient_snapshot"] ?? ""), true) ?: [];
        $contextSnapshot = json_decode((string)($attention["attention_context_snapshot"] ?? ""), true) ?: [];
        
        $data = array_merge($attention, $patientSnapshot, $contextSnapshot, [
            "fecha" => $attention["fecha"],
            "hora" => $attention["hora"],
            "enfermera_nombre" => $contextSnapshot["enfermera_nombre"] ?? $attention["enfermera_nombre"],
            "matricula" => $contextSnapshot["matricula"] ?? $attention["matricula"],
            "unidad_nombre" => $contextSnapshot["unidad_nombre"] ?? $attention["unidad_nombre"],
            "consultorio_nombre" => $contextSnapshot["consultorio_nombre"] ?? $attention["consultorio_nombre"],
            "turno_nombre" => $contextSnapshot["turno_nombre"] ?? $attention["turno_nombre"]
        ]);

        $vacc = $pdoPdf->prepare("SELECT va.*, v.nombre vacuna_nombre, d.clave dosis_clave, d.nombre dosis_nombre FROM vacunas_aplicadas va LEFT JOIN vacunas v ON v.vacuna_id=va.vacuna_id LEFT JOIN dosis d ON d.dosis_id=va.dosis_id WHERE va.paciente_id=? AND va.estado=\"ACTIVA\" AND va.fecha_aplicacion<=? ORDER BY va.fecha_aplicacion, va.vacuna_id");
        $vacc->execute([(int)$attention["paciente_id"], $attention["fecha"]]);
        $vaccines = $vacc->fetchAll();

        $comp = json_decode((string)($attention["companion_snapshot"] ?? ""), true) ?: null;
        $topicStmt = $pdoPdf->prepare("SELECT t.nombre FROM atencion_educacion_salud ae JOIN temas_educacion_salud t ON t.tema_id=ae.tema_id WHERE ae.atencion_id=? ORDER BY t.nombre");
        $topicStmt->execute([(int)$attention["atencion_id"]]);
        $topics = array_column($topicStmt->fetchAll(), "nombre");

        $pdf = new PdfTemplate();
        if ($route === "pdf_censo" || $route === "pdf_both") $pdf->censo($data, $vaccines, $comp);
        if ($route === "pdf_integral" || $route === "pdf_both") $pdf->integral($data, $vaccines, $topics);

        audit($pdoPdf, $user, "REIMPRESION PDF", "atenciones", (int)$attention["atencion_id"], null, ["formato" => $route]);
        
        header("Content-Type: application/pdf");
        header("Content-Disposition: inline; filename=\"srae_" . $route . ".pdf\"");
        header("Cache-Control: no-store");
        echo $pdf->Output("srae_" . $route . ".pdf", "S");
    } catch (Throwable $e) {
        http_response_code(500);
        echo env_value("APP_ENV", "production") === "development" ? "No fue posible generar el documento: " . h($e->getMessage()) : "No fue posible generar el documento.";
    }
    exit;
}

http_response_code(404);
echo "Pgina no encontrada.";
exit;

