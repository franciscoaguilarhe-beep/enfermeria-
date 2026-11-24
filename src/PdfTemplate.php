<?php
declare(strict_types=1);

require_once ENFERMERIA_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php';

final class PdfTemplate extends TCPDF
{
    private float $pageWidth = 210.0;
    private float $left = 10.0;
    private float $right = 200.0;

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->SetCreator('SRAE');
        $this->SetAuthor('SRAE');
        $this->SetTitle('Registro de Atención de Enfermería');
        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(false, 10);
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->tcpdflink = false;
        $this->SetFont('dejavusans', '', 7);
        $this->SetLineWidth(0.2);
    }

    public function start(): void { $this->AddPage(); $this->SetFillColor(255, 255, 255); }

    private function label(float $x, float $y, float $w, string $text, float $size = 6): void
    {
        $this->SetFont('dejavusans', 'B', $size);
        $this->SetXY($x, $y);
        $this->Cell($w, 4, $text, 0, 0, 'L');
        $this->SetFont('dejavusans', '', 7);
    }

    private function box(float $x, float $y, float $w, float $h, string $title, string $value = '', float $size = 7): void
    {
        $this->Rect($x, $y, $w, $h);
        $this->label($x + 1.5, $y + 1, $w - 3, $title, 5.5);
        if ($value !== '') $this->textBox($x + 1.5, $y + 5, $w - 3, $h - 6, $value, $size);
    }

    private function textBox(float $x, float $y, float $w, float $h, string $text, float $size = 7): void
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') return;
        for ($font = $size; $font >= 5; $font -= 0.5) {
            $this->SetFont('dejavusans', '', $font);
            if ($this->getStringHeight($w, $text) <= $h) break;
        }
        $this->SetXY($x, $y);
        $this->MultiCell($w, $h, $text, 0, 'L', false, 0, '', '', true, 0, false, true, $h, 'T', false);
        $this->SetFont('dejavusans', '', 7);
    }

    private function title(string $title, string $subtitle): void
    {
        $this->SetFont('dejavusans', 'B', 11);
        $this->Cell(0, 6, $title, 0, 1, 'C');
        $this->SetFont('dejavusans', '', 6.5);
        $this->Cell(0, 4, $subtitle, 0, 1, 'C');
        $this->Ln(2);
    }

    private function section(float $y, string $text): void
    {
        $this->SetFillColor(225, 232, 238);
        $this->SetFont('dejavusans', 'B', 7);
        $this->SetXY($this->left, $y);
        $this->Cell($this->right - $this->left, 5, $text, 1, 1, 'L', true);
        $this->SetFont('dejavusans', '', 7);
    }

    private function check(float $x, float $y, string $text, bool $checked = false): void
    {
        $this->Rect($x, $y, 3, 3);
        if ($checked) { $this->SetFont('dejavusans', 'B', 8); $this->Text($x + 0.4, $y - 0.3, 'X'); }
        $this->SetFont('dejavusans', '', 6.5); $this->Text($x + 4, $y - 0.2, $text);
    }

    private function formHeader(array $a, string $title): void
    {
        $this->title($title, 'Formato institucional · Sistema de Registro de Atención de Enfermería');
        $this->box(10, 27, 55, 12, 'UNIDAD / ADSCRIPCIÓN', (string)($a['unidad_nombre'] ?? $a['unidad'] ?? ''));
        $this->box(67, 27, 45, 12, 'FECHA', self::dateVisual((string)($a['fecha'] ?? '')));
        $this->box(114, 27, 38, 12, 'HORA', substr((string)($a['hora'] ?? ''), 0, 5));
        $this->box(154, 27, 46, 12, 'DOCUMENTO', $title);
    }

    private static function dateVisual(string $date): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) return $date;
        return $m[3] . ' / ' . $m[2] . ' / ' . $m[1];
    }

    private function patientIdentity(array $a, float $y): void
    {
        $this->section($y, 'DATOS DEL PACIENTE'); $y += 5;
        $this->box(10, $y, 65, 13, 'NOMBRE Y APELLIDOS', (string)($a['nombre_presentacion'] ?? patient_name($a)));
        $this->box(77, $y, 30, 13, 'NSS', (string)($a['nss'] ?? ''));
        $this->box(109, $y, 14, 13, 'DV', (string)($a['dv'] ?? ''));
        $this->box(125, $y, 32, 13, 'AGREGADO', (string)($a['agregado'] ?? ''));
        $this->box(159, $y, 41, 13, 'CURP', (string)($a['curp'] ?? ''));
        $y += 15;
        $this->box(10, $y, 32, 13, 'FECHA NACIMIENTO', self::dateVisual((string)($a['fecha_nacimiento'] ?? '')));
        $this->box(44, $y, 53, 13, 'LUGAR NACIMIENTO', (string)($a['lugar_nacimiento'] ?? ''));
        $this->box(99, $y, 35, 13, 'SEXO', (string)($a['sexo'] ?? ''));
        $this->box(136, $y, 64, 13, 'UNIDAD ADSCRIPCIÓN', (string)($a['unidad_nombre'] ?? ''));
        $y += 15;
        $this->box(10, $y, 63, 13, 'CALLE Y NÚMERO', trim((string)($a['calle'] ?? '') . ' ' . (string)($a['numero_exterior'] ?? '') . ' ' . (string)($a['numero_interior'] ?? '')));
        $this->box(75, $y, 42, 13, 'COLONIA', (string)($a['colonia'] ?? ''));
        $this->box(119, $y, 25, 13, 'C.P.', (string)($a['codigo_postal'] ?? ''));
        $this->box(146, $y, 54, 13, 'TELÉFONO', (string)($a['telefono'] ?? ''));
        $y += 15;
        $this->box(10, $y, 63, 13, 'MUNICIPIO / ENTIDAD', trim((string)($a['municipio'] ?? '') . ' / ' . (string)($a['entidad_residencia'] ?? '')));
        $this->box(75, $y, 42, 13, 'PAÍS RESIDENCIA', (string)($a['pais_residencia'] ?? ''));
        $this->box(119, $y, 25, 13, 'INDÍGENA', self::yesNo($a['indigena'] ?? null));
        $this->box(146, $y, 54, 13, 'AFROMEXICANO / DISCAPACIDAD', self::yesNo($a['afromexicano'] ?? null) . ' / ' . self::yesNo($a['discapacidad'] ?? null));
    }

    private static function yesNo($value): string { return $value === null || $value === '' ? 'NO ESPEC.' : ((int)$value === 1 ? 'SÍ' : 'NO'); }

    private static function dateSlash(string $date): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return (int)$m[3] . '/' . (int)$m[2] . '/' . $m[1];
        }
        return $date;
    }

    public function censo(array $a, array $vaccines, ?array $companion = null): void
    {
        $this->AddPage('P', 'LETTER');
        $this->SetAutoPageBreak(false, 0);
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);

        // Logo IMSS
        $logoPath = ENFERMERIA_ROOT . '/imss.jpg';
        if (!file_exists($logoPath)) {
            $logoPath = ENFERMERIA_ROOT . '/public/assets/images/imss.jpg';
        }
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 6.86, 22.0, 13.0, 12.5, 'JPG');
        }

        // Left Header Titles
        $this->SetFont('helvetica', 'B', 8.5);
        $this->SetXY(21.5, 23.0);
        $this->Cell(85.0, 4.5, 'INSTITUTO MEXICANO DEL SEGURO SOCIAL', 0, 1, 'L');
        $this->SetFont('helvetica', '', 7.5);
        $this->SetX(21.5);
        $this->Cell(85.0, 4.0, 'SEGURIDAD Y SOLIDARIDAD SOCIAL', 0, 1, 'L');

        // Gray horizontal line under institution title
        $this->SetDrawColor(128, 128, 128);
        $this->SetLineWidth(0.25);
        $this->Line(6.86, 36.5, 106.5, 36.5);
        $this->SetDrawColor(0, 0, 0);

        // Below line: Subtitles
        $this->SetFont('helvetica', '', 8);
        $this->SetXY(6.86, 39.0);
        $this->Cell(99.64, 4.0, 'Formato de Censos AMMATES', 0, 1, 'C');

        $this->SetXY(6.86, 44.0);
        $this->SetFont('helvetica', 'B', 7);
        $this->Write(4, 'ADSCRIPCION: ');
        $this->SetFont('helvetica', '', 7);
        $unitName = (string)($a['unidad_nombre'] ?? $a['unidad_clave'] ?? 'UNIDAD DE MEDICINA FAMILIAR N° 40');
        $this->Write(4, $unitName);

        $this->SetXY(6.86, 48.5);
        $this->SetFont('helvetica', 'B', 7);
        $this->Write(4, 'CONSULTORIO: ');
        $this->SetFont('helvetica', '', 7);
        $consClave = (string)($a['consultorio_nombre'] ?? $a['consultorio_clave'] ?? '7');
        $turnoNombre = (string)($a['turno_nombre'] ?? $a['turno_clave'] ?? 'MATUTINO');
        $consultShift = trim($consClave . ' - ' . $turnoNombre);
        $this->Write(4, $consultShift);

        // Top-Right Patient Box
        $boxX = 115.40;
        $boxY = 17.50;
        $boxW = 91.69;
        $boxH = 30.59;
        $this->SetLineWidth(0.4);
        $this->Rect($boxX, $boxY, $boxW, $boxH);
        $this->SetLineWidth(0.25);

        // NSS formatting: ####-##-####-#
        $rawNss = (string)($a['nss'] ?? '');
        $dv = (string)($a['dv'] ?? '');
        $nssFormatted = preg_match('/^(\d{4})(\d{2})(\d{4})$/', $rawNss, $nm)
            ? ($nm[1] . '-' . $nm[2] . '-' . $nm[3] . ($dv !== '' ? '-' . $dv : ''))
            : ($rawNss . ($dv !== '' ? '-' . $dv : ''));

        // Line 1: NSS and A. MED.
        $this->SetXY($boxX + 2.0, $boxY + 2.0);
        $this->SetFont('helvetica', 'B', 7.5);
        $this->Write(4, 'NSS:  ');
        $this->SetFont('helvetica', '', 7.5);
        $this->Write(4, $nssFormatted);

        $this->SetXY($boxX + 53.0, $boxY + 2.0);
        $this->SetFont('helvetica', 'B', 7.5);
        $this->Write(4, 'A. MED.:  ');
        $this->SetFont('helvetica', '', 7.5);
        $this->Write(4, (string)($a['agregado'] ?? ''));

        // Line 2: NOMBRE DEL PACIENTE
        $this->SetXY($boxX, $boxY + 6.8);
        $this->SetFont('helvetica', 'B', 7.5);
        $this->Cell($boxW, 4, 'NOMBRE DEL PACIENTE', 0, 1, 'C');

        // Line 3: Patient Name (Nombres Apellidos)
        $this->SetXY($boxX, $boxY + 11.2);
        $this->SetFont('helvetica', '', 8);
        $patName = trim(implode(' ', array_filter([$a['nombres'] ?? null, $a['apellido_paterno'] ?? null, $a['apellido_materno'] ?? null])));
        if ($patName === '' && !empty($a['nombre_completo'])) $patName = (string)$a['nombre_completo'];
        if ($patName === '') $patName = trim((string)($a['nombre_presentacion'] ?? ''));
        $this->Cell($boxW, 4, $patName, 0, 1, 'C');

        // Line 4: F. NAC. and EDAD
        $this->SetXY($boxX + 2.0, $boxY + 16.0);
        $this->SetFont('helvetica', 'B', 7);
        $this->Write(4, 'F. NAC.:  ');
        $this->SetFont('helvetica', '', 7);
        $this->Write(4, self::dateSlash((string)($a['fecha_nacimiento'] ?? '')));

        $this->SetXY($boxX + 53.0, $boxY + 16.0);
        $this->SetFont('helvetica', 'B', 7);
        $this->Write(4, 'EDAD:  ');
        $this->SetFont('helvetica', '', 7);
        $age = '';
        if (isset($a['edad_anios']) && $a['edad_anios'] !== '') {
            $age = (string)$a['edad_anios'] . ' AÑOS';
        } elseif (!empty($a['fecha_nacimiento'])) {
            [$ageY] = PatientDomain::ageParts((string)$a['fecha_nacimiento'], (string)($a['fecha'] ?? date('Y-m-d')));
            $age = $ageY . ' AÑOS';
        }
        $this->Write(4, $age);

        // Line 5: LUGAR DE NAC.
        $this->SetXY($boxX + 2.0, $boxY + 20.8);
        $this->SetFont('helvetica', 'B', 7);
        $this->Write(4, 'LUGAR DE NAC.:  ');
        $this->SetFont('helvetica', '', 7);
        $this->Write(4, (string)($a['lugar_nacimiento'] ?? ''));

        // Line 6: GENERO and C.U.R.P.
        $genLabel = ($a['sexo'] ?? '') === 'M' ? 'MASCULINO' : (($a['sexo'] ?? '') === 'F' ? 'FEMENINO' : (string)($a['sexo'] ?? ''));
        $this->SetXY($boxX + 2.0, $boxY + 25.5);
        $this->SetFont('helvetica', 'B', 7);
        $this->Write(4, 'GENERO:  ');
        $this->SetFont('helvetica', '', 7);
        $this->Write(4, $genLabel);

        $this->SetXY($boxX + 41.0, $boxY + 25.5);
        $this->SetFont('helvetica', 'B', 7);
        $this->Write(4, 'C.U.R.P.:  ');
        $this->SetFont('helvetica', '', 7);
        $this->Write(4, (string)($a['curp'] ?? ''));

        // Fecha de Elaboración
        $this->SetXY(115.40, 54.0);
        $this->SetFont('helvetica', 'B', 7.5);
        $this->Cell(60.0, 4.5, 'Fecha de Elaboración', 0, 0, 'R');

        $dateElab = self::dateSlash((string)($a['fecha'] ?? date('Y-m-d')));
        $this->SetFont('helvetica', '', 8);
        $this->SetXY(176.0, 53.5);
        $this->Cell(31.09, 4.5, $dateElab, 0, 0, 'C');
        $this->SetLineWidth(0.3);
        $this->Line(176.0, 58.5, 207.09, 58.5);

        // Section: LUGAR DE RESIDENCIA
        $resY = 62.70;
        $this->SetFillColor(191, 191, 191);
        $this->SetLineWidth(0.3);
        $this->Rect(6.86, $resY, 200.23, 4.86, 'DF');
        $this->SetFont('helvetica', 'B', 7.5);
        $this->SetXY(6.86, $resY);
        $this->Cell(200.23, 4.86, 'LUGAR DE RESIDENCIA', 0, 0, 'C');

        // Row 1
        $r1Y = 67.56;
        $this->Rect(6.86, $r1Y, 44.83, 9.74);
        $this->SetFont('helvetica', 'B', 6.5); $this->Text(7.86, $r1Y + 1.2, 'PAIS:');
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(6.86, $r1Y + 4.2); $this->Cell(44.83, 4.5, (string)($a['pais_residencia'] ?? ''), 0, 0, 'C');

        $this->Rect(51.69, $r1Y, 72.81, 9.74);
        $this->SetFont('helvetica', 'B', 6.5); $this->Text(52.69, $r1Y + 1.2, 'ENTIDAD:');
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(51.69, $r1Y + 4.2); $this->Cell(72.81, 4.5, (string)($a['entidad_residencia'] ?? ''), 0, 0, 'C');

        $this->Rect(124.50, $r1Y, 54.61, 9.74);
        $this->SetFont('helvetica', 'B', 6.5); $this->Text(125.50, $r1Y + 1.2, 'MUNICIPIO:');
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(124.50, $r1Y + 4.2); $this->Cell(54.61, 4.5, (string)($a['municipio'] ?? ''), 0, 0, 'C');

        $this->Rect(179.11, $r1Y, 27.98, 9.74);
        $this->SetFont('helvetica', 'B', 6.5); $this->Text(180.11, $r1Y + 1.2, 'C.P.:');
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(179.11, $r1Y + 4.2); $this->Cell(27.98, 4.5, (string)($a['codigo_postal'] ?? ''), 0, 0, 'C');

        // Row 2
        $r2Y = 77.30;
        $this->Rect(6.86, $r2Y, 72.13, 9.74);
        $this->SetFont('helvetica', 'B', 6.5); $this->Text(7.86, $r2Y + 1.2, 'COLONIA:');
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(6.86, $r2Y + 4.2); $this->Cell(72.13, 4.5, (string)($a['colonia'] ?? ''), 0, 0, 'C');

        $this->Rect(78.99, $r2Y, 128.10, 9.74);
        $this->SetFont('helvetica', 'B', 6.5); $this->Text(79.99, $r2Y + 1.2, 'CALLE Y NUMERO:');
        $addr = trim((string)($a['calle'] ?? '') . ' ' . (string)($a['numero_exterior'] ?? '') . ' ' . (string)($a['numero_interior'] ?? ''));
        if ($addr === '' && !empty($a['domicilio'])) $addr = (string)$a['domicilio'];
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(78.99, $r2Y + 4.2); $this->Cell(128.10, 4.5, $addr, 0, 0, 'C');

        // Row 3
        $r3Y = 87.04;
        $this->Rect(6.86, $r3Y, 35.73, 9.73);
        $this->SetFont('helvetica', 'B', 6.5); $this->Text(7.86, $r3Y + 1.2, 'TELEFONO:');
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(6.86, $r3Y + 4.2); $this->Cell(35.73, 4.5, (string)($a['telefono'] ?? ''), 0, 0, 'C');

        $this->Rect(42.59, $r3Y, 36.40, 9.73);
        $this->SetFont('helvetica', 'B', 6.5); $this->Text(43.59, $r3Y + 1.2, 'OTRO CONTACTO:');
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(42.59, $r3Y + 4.2); $this->Cell(36.40, 4.5, (string)($a['otro_contacto'] ?? ''), 0, 0, 'C');

        $this->Rect(78.99, $r3Y, 36.41, 9.73);
        $this->SetFont('helvetica', 'B', 6.5); $this->SetXY(78.99, $r3Y + 1.2); $this->Cell(36.41, 3.5, 'INDIGENA', 0, 0, 'C');
        $valInd = (int)($a['indigena'] ?? 0) === 1 ? 'SI' : 'NO';
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(78.99, $r3Y + 4.2); $this->Cell(36.41, 4.5, $valInd, 0, 0, 'C');

        $this->Rect(115.40, $r3Y, 45.51, 9.73);
        $this->SetFont('helvetica', 'B', 6.5); $this->SetXY(115.40, $r3Y + 1.2); $this->Cell(45.51, 3.5, 'AFROAMERICANO', 0, 0, 'C');
        $valAfro = (int)($a['afromexicano'] ?? 0) === 1 ? 'SI' : 'NO';
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(115.40, $r3Y + 4.2); $this->Cell(45.51, 4.5, $valAfro, 0, 0, 'C');

        $this->Rect(160.91, $r3Y, 46.18, 9.73);
        $this->SetFont('helvetica', 'B', 6.5); $this->SetXY(160.91, $r3Y + 1.2); $this->Cell(46.18, 3.5, 'DISCAPACIDAD', 0, 0, 'C');
        $valDisc = (int)($a['discapacidad'] ?? 0) === 1 ? 'SI' : 'NO';
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(160.91, $r3Y + 4.2); $this->Cell(46.18, 4.5, $valDisc, 0, 0, 'C');

        // Section: ACOMPAÑANTE
        $compY = 96.77;
        $this->SetFillColor(191, 191, 191);
        $this->Rect(6.86, $compY, 200.23, 4.87, 'DF');
        $this->SetFont('helvetica', 'B', 7.5);
        $this->SetXY(6.86, $compY);
        $this->Cell(200.23, 4.87, 'ACOMPAÑANTE', 0, 0, 'C');

        $c = $companion ?? [];
        $compName = trim(implode(' ', array_filter([$c['nombres'] ?? null, $c['apellido_paterno'] ?? null, $c['apellido_materno'] ?? null])));
        if ($compName === '' && !empty($c['nombre_completo'])) $compName = (string)$c['nombre_completo'];

        // Companion Row 1
        $cr1Y = 101.64;
        $this->Rect(6.86, $cr1Y, 200.23, 9.74);
        $this->SetFont('helvetica', 'B', 6.5); $this->SetXY(6.86, $cr1Y + 1.2); $this->Cell(200.23, 3.5, 'NOMBRE:', 0, 0, 'C');
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(6.86, $cr1Y + 4.5); $this->Cell(200.23, 4.5, $compName, 0, 0, 'C');

        // Companion Row 2
        $cr2Y = 111.38;
        $this->Rect(6.86, $cr2Y, 44.83, 9.74);
        $this->SetFont('helvetica', 'B', 6.5); $this->SetXY(6.86, $cr2Y + 1.2); $this->Cell(44.83, 3.5, 'FECHA DE NACIMIENTO', 0, 0, 'C');
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(6.86, $cr2Y + 4.5); $this->Cell(44.83, 4.5, self::dateSlash((string)($c['fecha_nacimiento'] ?? '')), 0, 0, 'C');

        $this->Rect(51.69, $cr2Y, 100.12, 9.74);
        $this->SetFont('helvetica', 'B', 6.5); $this->SetXY(51.69, $cr2Y + 1.2); $this->Cell(100.12, 3.5, 'LUGAR DE NACIMIENTO', 0, 0, 'C');
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(51.69, $cr2Y + 4.5); $this->Cell(100.12, 4.5, (string)($c['lugar_nacimiento'] ?? ''), 0, 0, 'C');

        $this->Rect(151.81, $cr2Y, 27.30, 9.74);
        $this->SetFont('helvetica', 'B', 6.5); $this->SetXY(151.81, $cr2Y + 1.2); $this->Cell(27.30, 3.5, 'GENERO', 0, 0, 'C');
        $cGen = (string)($c['sexo'] ?? '');
        if ($cGen === 'M') $cGen = 'MASCULINO';
        elseif ($cGen === 'F') $cGen = 'FEMENINO';
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(151.81, $cr2Y + 4.5); $this->Cell(27.30, 4.5, $cGen, 0, 0, 'C');

        $this->Rect(179.11, $cr2Y, 27.98, 9.74);
        $this->SetFont('helvetica', 'B', 6.5); $this->SetXY(179.11, $cr2Y + 1.2); $this->Cell(27.98, 3.5, 'PARENTESCO', 0, 0, 'C');
        $this->SetFont('helvetica', '', 7.5); $this->SetXY(179.11, $cr2Y + 4.5); $this->Cell(27.98, 4.5, (string)($c['parentesco'] ?? ''), 0, 0, 'C');

        // Section: OBSERVACIONES
        $obsY = 121.12;
        $this->Rect(6.86, $obsY, 200.23, 19.47);
        $this->SetFont('helvetica', 'B', 7.5);
        $this->SetXY(6.86, $obsY + 1.5);
        $this->Cell(200.23, 3.5, 'OBSERVACIONES', 0, 0, 'C');
        $obsText = trim((string)($a['observaciones_generales'] ?? $a['observaciones'] ?? ''));
        if ($obsText !== '') {
            $this->SetFont('helvetica', '', 7);
            $this->SetXY(8.86, $obsY + 5.5);
            $this->MultiCell(196.23, 12.5, $obsText, 0, 'L');
        }

        // Section: ESQUEMA DE VACUNACION AMMATES
        $vacY = 140.59;
        $this->SetFillColor(191, 191, 191);
        $this->Rect(6.86, $vacY, 200.23, 4.87, 'DF');
        $this->SetFont('helvetica', 'B', 7.5);
        $this->SetXY(6.86, $vacY);
        $this->Cell(200.23, 4.87, 'ESQUEMA DE VACUNACION AMMATES', 0, 0, 'C');

        // Subheader row
        $subH = 145.46;
        $this->SetFillColor(242, 242, 242);
        $cols = [
            ['w' => 35.73, 'label' => 'VACUNA'],
            ['w' => 18.20, 'label' => '1ER'],
            ['w' => 18.20, 'label' => '2do'],
            ['w' => 18.21, 'label' => '3er'],
            ['w' => 18.20, 'label' => '4to'],
            ['w' => 18.20, 'label' => 'REF'],
            ['w' => 18.21, 'label' => 'ADIC'],
            ['w' => 18.20, 'label' => 'ADIC'],
            ['w' => 18.20, 'label' => 'LITE'],
            ['w' => 18.88, 'label' => 'CAD'],
        ];

        $cx = 6.86;
        $this->SetFont('helvetica', '', 7);
        foreach ($cols as $col) {
            $this->Rect($cx, $subH, $col['w'], 4.87, 'DF');
            $this->SetXY($cx, $subH);
            $this->Cell($col['w'], 4.87, $col['label'], 0, 0, 'C');
            $cx += $col['w'];
        }

        // Group applied vaccines by name
        $group = [];
        foreach ($vaccines as $v) {
            $k = mb_strtoupper(trim((string)($v['vacuna_nombre'] ?? $v['vacuna'] ?? '')), 'UTF-8');
            $group[$k][] = $v;
        }

        $scheme = [
            'BCG',
            'HEPATITIS B',
            'HEXAVALENTE',
            'DPT',
            'ROTAVIRUS',
            'NEUMOCOCCICA 13',
            'ANTIINFLUENZA',
            'SRP',
            'VARICELA',
            'HEPATITIS A',
            'VPH',
            'SR',
            'TD.',
            'TDPA',
            'NEUMOCOCCICA 20',
            'COVID 19',
        ];

        $rowY = 150.33;
        $rowH = 4.87;

        $doseMap = [
            1 => ['1ER', '1A', '1'],
            2 => ['2DO', '2DA', '2A', '2'],
            3 => ['3ER', '3RO', '3RA', '3A', '3'],
            4 => ['4TO', '4TA', '4A', '4'],
            5 => ['REF', 'R1', 'REFUERZO'],
            6 => ['ADIC', 'ADIC1', 'ADICIONAL'],
            7 => ['ADIC2', 'ADICIONAL2'],
        ];

        foreach ($scheme as $vName) {
            $this->SetFont('helvetica', '', 6.5);
            $cx = 6.86;

            $vKeyNorm = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($vName, 'UTF-8'));
            $matchedEvents = [];
            foreach ($group as $gk => $gList) {
                $gkNorm = preg_replace('/[^A-Z0-9]/', '', $gk);
                $isNeumoAlias = ($vKeyNorm === 'NEUMOCOCCICA20' && in_array($gkNorm, ['NEUMOCOCCICA23', 'NEUMO23', 'NEUMO20'], true));
                if ($isNeumoAlias || $gkNorm === $vKeyNorm || strpos($gkNorm, $vKeyNorm) !== false || strpos($vKeyNorm, $gkNorm) !== false) {
                    $matchedEvents = array_merge($matchedEvents, $gList);
                }
            }

            // Col 0: VACUNA
            $this->Rect($cx, $rowY, $cols[0]['w'], $rowH);
            $this->SetXY($cx + 1.2, $rowY);
            $this->Cell($cols[0]['w'] - 1.2, $rowH, $vName, 0, 0, 'L');
            $cx += $cols[0]['w'];

            // Cols 1 to 7: Doses
            $latestLote = '';
            $latestCad = '';

            for ($d = 1; $d <= 7; $d++) {
                $cw = $cols[$d]['w'];
                $this->Rect($cx, $rowY, $cw, $rowH);

                $foundEv = null;
                foreach ($matchedEvents as $ev) {
                    $evDose = mb_strtoupper(trim((string)($ev['dosis_clave'] ?? '')), 'UTF-8');
                    $evOrd = (int)($ev['orden'] ?? 0);
                    if (in_array($evDose, $doseMap[$d], true) || $evOrd === $d) {
                        $foundEv = $ev;
                        break;
                    }
                }

                if ($foundEv) {
                    $dateTxt = self::dateSlash((string)($foundEv['fecha_aplicacion'] ?? ''));
                    $this->SetXY($cx, $rowY);
                    $this->Cell($cw, $rowH, $dateTxt, 0, 0, 'C');
                    if (!empty($foundEv['lote'])) $latestLote = (string)$foundEv['lote'];
                    if (!empty($foundEv['fecha_caducidad'])) $latestCad = self::dateSlash((string)$foundEv['fecha_caducidad']);
                }
                $cx += $cw;
            }

            // Col 8: LITE / LOTE
            $this->Rect($cx, $rowY, $cols[8]['w'], $rowH);
            if ($latestLote !== '') {
                $this->SetXY($cx, $rowY);
                $this->Cell($cols[8]['w'], $rowH, $latestLote, 0, 0, 'C');
            }
            $cx += $cols[8]['w'];

            // Col 9: CAD
            $this->Rect($cx, $rowY, $cols[9]['w'], $rowH);
            if ($latestCad !== '') {
                $this->SetXY($cx, $rowY);
                $this->Cell($cols[9]['w'], $rowH, $latestCad, 0, 0, 'C');
            }
            $cx += $cols[9]['w'];

            $rowY += $rowH;
        }

        // Bottom Signature Block: Line is ABOVE the nurse's name
        $sigLineY = 247.86;
        $this->SetLineWidth(0.3);
        $this->Line(42.93, $sigLineY, 170.39, $sigLineY);

        $nurseName = (string)($a['enfermera_nombre'] ?? '');
        $this->SetFont('helvetica', 'B', 8.5);
        $this->SetXY(42.93, $sigLineY + 2.0);
        $this->Cell(127.46, 4.5, $nurseName, 0, 0, 'C');

        $this->SetFont('helvetica', 'B', 8);
        $this->SetXY(42.93, $sigLineY + 6.8);
        $this->Cell(127.46, 4.5, 'ELABORÓ', 0, 0, 'C');
    }

    public function integral(array $a, array $vaccines, array $topics = []): void
    {
        $this->AddPage('P', 'A4');
        $this->SetAutoPageBreak(false, 0);
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
        
        // Logo IMSS
        $logoPath = ENFERMERIA_ROOT . '/imss.jpg';
        if (!file_exists($logoPath)) {
            $logoPath = ENFERMERIA_ROOT . '/public/assets/images/imss.jpg';
        }
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 10, 18, 18, 'JPG');
        }
        
        // Left text
        $this->SetFont('helvetica', 'B', 7.5);
        $this->SetXY(30, 10);
        $this->Cell(65, 4, 'INSTITUTO MEXICANO DEL SEGURO SOCIAL', 0, 1, 'C');
        $this->SetXY(30, 14);
        $this->Cell(65, 4, 'DIRECCIÓN DE PRESTACIONES MÉDICAS', 0, 1, 'C');
        $this->SetXY(30, 18);
        $this->Cell(65, 4, 'UNIDAD DE ATENCIÓN PRIMARIA A LA SALUD', 0, 1, 'C');
        $this->SetXY(30, 22);
        $this->Cell(65, 4, 'EN EL PRIMER NIVEL', 0, 1, 'C');
        
        $this->SetXY(30, 29);
        $this->SetFont('helvetica', 'B', 8);
        $this->Cell(65, 4, 'Hoja de atención Integral', 0, 1, 'C');
        
        $this->SetXY(30, 34);
        $this->SetFont('helvetica', 'B', 7.5);
        $this->Cell(65, 4, 'Enfermera Especialista en Medicina Familiar', 0, 1, 'C');

        // Right box
        $boxX = 95; $boxY = 10; $boxW = 105; $boxH = 30;
        $this->SetLineWidth(0.4);
        $this->Rect($boxX, $boxY, $boxW, $boxH);
        
        $this->SetLineWidth(0.2);
        
        // Row 1: Nombre
        $yBase = $boxY + 1.5;
        $this->SetFont('helvetica', 'B', 8);
        $this->SetXY($boxX + 2, $yBase);
        $this->Cell(15, 4, 'Nombre:', 0, 0, 'L');
        $this->SetFont('helvetica', '', 8);
        $patName = trim(implode(' ', array_filter([$a['nombres'] ?? null, $a['apellido_paterno'] ?? null, $a['apellido_materno'] ?? null])));
        if ($patName === '' && !empty($a['nombre_completo'])) $patName = (string)$a['nombre_completo'];
        if ($patName === '') $patName = trim((string)($a['nombre_presentacion'] ?? ''));
        $this->SetXY($boxX + 17, $yBase);
        $this->Cell(86, 4, $patName, 0, 1, 'C');
        
        // Row 2: No. Seguro Social
        $yBase += 4.7;
        $this->SetFont('helvetica', 'B', 8);
        $this->SetXY($boxX + 2, $yBase);
        $this->Cell(28, 4, 'No. Seguro Social:', 0, 0, 'L');
        $this->SetFont('helvetica', '', 8);
        $nss = (string)($a['nss'] ?? '');
        $dv = (string)($a['dv'] ?? '');
        $agregado = (string)($a['agregado'] ?? '');
        $nssFull = $nss . ($dv !== '' ? '-' . $dv : '') . ($agregado !== '' ? ' ' . $agregado : '');
        $this->SetXY($boxX + 30, $yBase);
        $this->Cell(73, 4, $nssFull, 0, 1, 'C');
        
        // Row 3: Unidad de Medicina Familiar
        $yBase += 4.7;
        $this->SetFont('helvetica', 'B', 8);
        $this->SetXY($boxX + 2, $yBase);
        $this->Cell(42, 4, 'Unidad de Medicina Familiar:', 0, 0, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->SetXY($boxX + 44, $yBase);
        $this->Cell(59, 4, (string)($a['unidad_nombre'] ?? $a['unidad_clave'] ?? ''), 0, 1, 'C');
        
        // Row 4: Edad, Sexo, ESCOLARIDAD
        $yBase += 4.7;
        $this->SetFont('helvetica', 'B', 8);
        $this->SetXY($boxX + 2, $yBase);
        $this->Cell(9, 4, 'Edad', 0, 0, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->Cell(15, 4, (string)($a['edad_anios'] ?? ''), 0, 0, 'C');
        $this->SetFont('helvetica', 'B', 8);
        $this->Cell(9, 4, 'Sexo', 0, 0, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->Cell(10, 4, (string)($a['sexo'] ?? ''), 0, 0, 'C');
        $this->SetFont('helvetica', 'B', 8);
        $this->Cell(25, 4, 'ESCOLARIDAD', 0, 0, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->Cell(33, 4, mb_strtoupper((string)($a['escolaridad'] ?? ''), 'UTF-8'), 0, 1, 'R');
        
        // Row 5: Ocupación
        $yBase += 4.7;
        $this->SetFont('helvetica', 'B', 8);
        $this->SetXY($boxX + 2, $yBase);
        $this->Cell(18, 4, 'Ocupación', 0, 0, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->SetXY($boxX + 20, $yBase);
        $this->Cell(83, 4, mb_strtoupper((string)($a['ocupacion'] ?? ''), 'UTF-8'), 0, 1, 'C');
        
        // Row 6: Estado civil, Consultorio, Turno
        $yBase += 4.7;
        $this->SetFont('helvetica', 'B', 8);
        $this->SetXY($boxX + 2, $yBase);
        $this->Cell(16, 4, 'Estado civil', 0, 0, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->Cell(17, 4, mb_strtoupper((string)($a['estado_civil'] ?? ''), 'UTF-8'), 0, 0, 'C');
        $this->SetFont('helvetica', 'B', 8);
        $this->Cell(17, 4, 'Consultorio', 0, 0, 'C');
        $this->SetFont('helvetica', '', 7.6);
        $this->Cell(20, 4, (string)($a['consultorio_nombre'] ?? $a['consultorio_clave'] ?? ''), 0, 0, 'C');
        $this->SetFont('helvetica', 'B', 8);
        $this->Cell(10, 4, 'Turno', 0, 0, 'C');
        $this->SetFont('helvetica', '', 8);
        $this->Cell(23, 4, mb_strtoupper((string)($a['turno_nombre'] ?? $a['turno_clave'] ?? ''), 'UTF-8'), 0, 1, 'R');
        
        $startY = 44;
        $this->SetLineWidth(0.4);
        $this->Rect(10, $startY, 190, 12);
        
        $this->SetLineWidth(0.2);
        $this->SetFillColor(217, 217, 217);
        $this->SetFont('helvetica', 'B', 8);
        
        $this->Rect(10, $startY, 75, 4, 'DF');
        $this->SetXY(10, $startY);
        $this->Cell(75, 4, 'Signos Vitales', 0, 0, 'C');
        
        $this->Rect(85, $startY, 115, 4, 'DF');
        $this->SetXY(85, $startY);
        $this->Cell(115, 4, 'Somasometría', 0, 0, 'C');
        
        $y2 = $startY + 4;
        $wSV = [25, 25, 25]; 
        $wSM = [23, 23, 23, 23, 23]; 
        
        $x = 10;
        $colsSV = ['Temperatura', 'Frec Card.', 'Frec. Resp'];
        foreach ($colsSV as $i => $col) {
            $this->Rect($x, $y2, $wSV[$i], 4, 'DF');
            $this->SetXY($x, $y2);
            $this->Cell($wSV[$i], 4, $col, 0, 0, 'C');
            $x += $wSV[$i];
        }
        
        $colsSM = ['Peso', 'Talla', 'PC', 'PT', 'PA'];
        foreach ($colsSM as $i => $col) {
            $this->Rect($x, $y2, $wSM[$i], 4, 'DF');
            $this->SetXY($x, $y2);
            $this->Cell($wSM[$i], 4, $col, 0, 0, 'C');
            $x += $wSM[$i];
        }
        
        $y3 = $startY + 8;
        $this->SetFont('helvetica', '', 8);
        $x = 10;
        $valSV = [(string)($a['temperatura'] ?? ''), (string)($a['frecuencia_cardiaca'] ?? ''), (string)($a['frecuencia_respiratoria'] ?? '')];
        foreach ($valSV as $i => $val) {
            $this->Rect($x, $y3, $wSV[$i], 4);
            $this->SetXY($x, $y3);
            $this->Cell($wSV[$i], 4, $val, 0, 0, 'C');
            $x += $wSV[$i];
        }
        $valSM = [(string)($a['peso'] ?? ''), (string)($a['talla'] ?? ''), (string)($a['perimetro_cefalico'] ?? ''), (string)($a['perimetro_toracico'] ?? ''), (string)($a['perimetro_abdominal'] ?? '')];
        foreach ($valSM as $i => $val) {
            $this->Rect($x, $y3, $wSM[$i], 4);
            $this->SetXY($x, $y3);
            $this->Cell($wSM[$i], 4, $val, 0, 0, 'C');
            $x += $wSM[$i];
        }
        
        $startY = 59;
        $this->SetLineWidth(0.4);
        $this->Rect(10, $startY, 190, 24);
        $this->SetLineWidth(0.2);
        
        $this->SetFillColor(217, 217, 217);
        $this->Rect(10, $startY, 190, 4, 'DF');
        $this->SetFont('helvetica', 'B', 8);
        $this->SetXY(10, $startY);
        $this->Cell(190, 4, 'ESQUEMA DE VACUNACIÓN', 0, 0, 'C');
        
        $y2 = $startY + 4;
        $colsVac = ['', 'BCG', 'HEP B', 'HEXA', 'DP', 'ROTA', 'NEUMO 13 V', 'INFLUENZA', 'SRP'];
        $wVac = [10, 22.5, 22.5, 22.5, 22.5, 22.5, 22.5, 22.5, 22.5];
        $x = 10;
        foreach ($colsVac as $i => $col) {
            $this->Rect($x, $y2, $wVac[$i], 4, 'DF');
            $this->SetXY($x, $y2);
            $this->Cell($wVac[$i], 4, $col, 0, 0, 'C');
            $x += $wVac[$i];
        }
        
        $rowsVac = ['1ER', '2DA', '3ER', 'REF'];
        $yRow = $y2 + 4;
        
        $greyCells = [
            'BCG' => ['2DA', '3ER', 'REF'],
            'HEP B' => ['REF'],
            'DP' => ['1ER', '2DA', '3ER'],
            'ROTA' => ['REF'],
            'SRP' => ['3ER', 'REF'],
        ];
        
        $this->SetFont('helvetica', '', 8);
        foreach ($rowsVac as $rName) {
            $x = 10;
            $this->Rect($x, $yRow, $wVac[0], 4, 'D'); 
            $this->SetXY($x, $yRow);
            $this->Cell($wVac[0], 4, $rName, 0, 0, 'C');
            $x += $wVac[0];
            
            for ($i = 1; $i < count($colsVac); $i++) {
                $cName = $colsVac[$i];
                $isGrey = false;
                if (isset($greyCells[$cName]) && in_array($rName, $greyCells[$cName])) {
                    $isGrey = true;
                }
                
                $fill = $isGrey ? 'DF' : 'D';
                if ($isGrey) {
                    $this->SetFillColor(191, 191, 191);
                } else {
                    $this->SetFillColor(255, 255, 255);
                }
                
                $this->Rect($x, $yRow, $wVac[$i], 4, $fill);
                
                if (!$isGrey) {
                    $dateFound = '';
                    foreach ($vaccines as $v) {
                        $vName = mb_strtoupper((string)($v['vacuna_nombre'] ?? $v['vacuna'] ?? ''), 'UTF-8');
                        $doseStr = mb_strtoupper((string)($v['dosis_clave'] ?? ''), 'UTF-8');
                        $n1 = preg_replace('/[^A-Z0-9]/', '', $cName);
                        $n2 = preg_replace('/[^A-Z0-9]/', '', $vName);
                        
                        if ($n1 !== '' && (strpos($n1, $n2) !== false || strpos($n2, $n1) !== false)) {
                            if (strpos($doseStr, substr($rName, 0, 1)) !== false || $doseStr === $rName || ($rName === 'REF' && strpos($doseStr, 'REF') !== false)) {
                                $dateFound = self::dateSlash((string)($v['fecha_aplicacion'] ?? ''));
                                break;
                            }
                        }
                    }
                    if ($dateFound !== '') {
                        $this->SetXY($x, $yRow);
                        $this->Cell($wVac[$i], 4, $dateFound, 0, 0, 'C');
                    }
                }
                $x += $wVac[$i];
            }
            $yRow += 4;
        }
        
        $startY = 86;
        $this->SetFillColor(217, 217, 217);
        $this->SetFont('helvetica', 'B', 7);
        
        $x = 10;
        $this->Rect($x, $startY, 12, 5, 'DF');
        $this->SetXY($x, $startY);
        $this->Cell(12, 5, 'VIT A', 0, 0, 'C');
        $x += 12;
        $vVitA = self::dateSlash((string)($a['vitamina_a_fecha'] ?? ''));
        $this->Rect($x, $startY, 18, 5, 'D');
        $this->SetXY($x, $startY);
        $this->Cell(18, 5, $vVitA, 0, 0, 'C');
        $x += 18;
        
        $this->Rect($x, $startY, 32, 5, 'DF');
        $this->SetXY($x, $startY);
        $this->Cell(32, 5, 'DESPARASITACIÓN', 0, 0, 'C');
        $x += 32;
        $vDesp = self::dateSlash((string)($a['desparasitacion_fecha'] ?? ''));
        $this->Rect($x, $startY, 25, 5, 'D');
        $this->SetXY($x, $startY);
        $this->Cell(25, 5, $vDesp, 0, 0, 'C');
        $x += 25;
        
        $x += 5;
        
        $this->Rect($x, $startY, 26, 5, 'DF');
        $this->SetXY($x, $startY);
        $this->Cell(26, 5, 'AGUDEZA VISUAL', 0, 0, 'C');
        $x += 26;
        
        $this->Rect($x, $startY, 20, 5, 'D');
        $this->SetXY($x, $startY);
        $this->Cell(20, 5, 'Ojo Derecho', 0, 0, 'C');
        $x += 20;
        
        $vOd = (string)($a['agudeza_visual_od'] ?? '');
        $this->Rect($x, $startY, 15, 5, 'D');
        $this->SetXY($x, $startY);
        $this->Cell(15, 5, $vOd, 0, 0, 'C');
        $x += 15;
        
        $this->Rect($x, $startY, 20, 5, 'D');
        $this->SetXY($x, $startY);
        $this->Cell(20, 5, 'Ojo Izquierdo', 0, 0, 'C');
        $x += 20;
        
        $vOi = (string)($a['agudeza_visual_oi'] ?? '');
        $this->Rect($x, $startY, 17, 5, 'D');
        $this->SetXY($x, $startY);
        $this->Cell(17, 5, $vOi, 0, 0, 'C');
        
        $startY = 94;
        $this->SetLineWidth(0.4);
        $this->Rect(10, $startY, 190, 140);
        $this->SetLineWidth(0.2);
        
        $this->SetFillColor(217, 217, 217);
        $this->SetFont('helvetica', 'B', 8);
        $colW = 190 / 4; 
        $colsText = ['EXPLORACION FISICA', 'NOTA DE ENFERMERÍA', 'EDUCACIÓN PARA LA SALUD', 'OBSERVACIONES'];
        $x = 10;
        foreach ($colsText as $i => $t) {
            $this->Rect($x, $startY, $colW, 5, 'DF');
            $this->SetXY($x, $startY);
            $this->Cell($colW, 5, $t, 0, 0, 'C');
            
            if ($i > 0) {
                $this->Line($x, $startY + 5, $x, $startY + 140);
            }
            
            $x += $colW;
        }
        
        $yCol = $startY + 6;
        $hCol = 133;
        $x = 10;
        
        $vExp = (string)($a['exploracion_fisica'] ?? '');
        $vNot = (string)($a['nota_enfermeria'] ?? '');
        
        $topicsTxt = implode("\n", $topics);
        $vEdu = trim($topicsTxt . ((string)($a['educacion_salud_texto'] ?? '') !== '' ? "\n" . $a['educacion_salud_texto'] : ''));
        
        $vObs = (string)($a['observaciones'] ?? '');
        
        $vals = [$vExp, $vNot, $vEdu, $vObs];
        
        $this->SetFont('helvetica', '', 7.5);
        foreach ($vals as $i => $v) {
            if ($v !== '') {
                $this->SetXY($x + 1, $yCol);
                $this->MultiCell($colW - 2, 4, $v, 0, 'L');
            }
            $x += $colW;
        }
        
        $ySig = $startY + 140;
        
        $this->SetFillColor(217, 217, 217);
        $this->SetLineWidth(0.4);
        $this->Rect(10, $ySig, 190, 5);
        $this->SetLineWidth(0.2);
        
        $this->SetFont('helvetica', 'B', 8);
        $wSig = [50, 70, 70];
        $x = 10;
        $this->Rect($x, $ySig, $wSig[0], 5, 'DF');
        $this->SetXY($x, $ySig);
        $this->Cell($wSig[0], 5, 'Fecha de atención', 0, 0, 'C');
        $x += $wSig[0];
        
        $this->Rect($x, $ySig, $wSig[1], 5, 'DF');
        $this->SetXY($x, $ySig);
        $this->Cell($wSig[1], 5, 'Nombre y firma EEMF', 0, 0, 'C');
        $x += $wSig[1];
        
        $this->Rect($x, $ySig, $wSig[2], 5, 'DF');
        $this->SetXY($x, $ySig);
        $this->Cell($wSig[2], 5, 'Nombre y firma JE o SJE o EJPMF', 0, 0, 'C');
        
        $ySigVal = $ySig + 5;
        $this->SetLineWidth(0.4);
        $this->Rect(10, $ySigVal, 190, 6);
        $this->SetLineWidth(0.2);
        
        $x = 10;
        $this->SetFont('helvetica', '', 8);
        $vFechaAt = self::dateSlash((string)($a['fecha'] ?? ''));
        $this->Rect($x, $ySigVal, $wSig[0], 6, 'D');
        $this->SetXY($x, $ySigVal);
        $this->Cell($wSig[0], 6, $vFechaAt, 0, 0, 'C');
        $x += $wSig[0];
        
        $vEnf = mb_strtoupper((string)($a['enfermera_nombre'] ?? ''), 'UTF-8');
        $this->Rect($x, $ySigVal, $wSig[1], 6, 'D');
        $this->SetXY($x, $ySigVal);
        $this->Cell($wSig[1], 6, $vEnf, 0, 0, 'C');
        $x += $wSig[1];
        
        $this->Rect($x, $ySigVal, $wSig[2], 6, 'D');
        $this->SetXY($x, $ySigVal);
        $this->Cell($wSig[2], 6, '', 0, 0, 'C');
        
        $yBot = $ySigVal + 8;
        $this->SetFont('helvetica', '', 8);
        
        // Primera línea: Envío con Medico Familiar
        $this->SetXY(10, $yBot);
        $str1 = 'Envío con Medico Familiar por signos y síntomas de alarma:';
        $w1 = $this->GetStringWidth($str1);
        $this->Cell($w1, 4, $str1, 0, 0, 'L');
        
        // Línea sobre la que se puede escribir
        $this->Line(10 + $w1 + 1, $yBot + 3.5, 200, $yBot + 3.5);
        
        // Segunda línea: Fecha
        $this->SetXY(10, $yBot + 6);
        $str2 = 'Fecha:';
        $w2 = $this->GetStringWidth($str2);
        $this->Cell($w2, 4, $str2, 0, 0, 'L');
        
        $fechaEnvio = ((int)($a['envio_medico_familiar'] ?? 0) === 1) ? self::dateSlash((string)($a['fecha_envio_medico_familiar'] ?? '')) : '';
        if ($fechaEnvio !== '') {
            $this->SetXY(10 + $w2 + 1, $yBot + 6);
            $this->Cell(30, 4, $fechaEnvio, 0, 0, 'C');
        }
        
        // Línea sobre la que se puede escribir la fecha
        $this->Line(10 + $w2 + 1, $yBot + 9.5, 50, $yBot + 9.5);
        
        $this->SetFont('helvetica', 'B', 9);
        $this->SetXY(150, $yBot + 6);
        $this->Cell(50, 4, 'Clave: 2250-003-002', 0, 0, 'R');
    }

    public function prostata(array $a, array $questions, array $answers): void
    {
        $this->AddPage('P', 'LETTER');
        $this->SetAutoPageBreak(false, 0);
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0, 0, 0);

        // 1. Encabezado Institucional
        $logoPath = ENFERMERIA_ROOT . '/imss.jpg';
        if (!file_exists($logoPath)) {
            $logoPath = ENFERMERIA_ROOT . '/public/assets/images/imss.jpg';
        }
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 19.5, 10.5, 16.0, 18.0, 'JPG');
        }

        $this->SetFont('helvetica', 'B', 9.2);
        $this->SetXY(38.0, 12.0);
        $this->Cell(125.0, 4.2, 'Instituto Mexicano del Seguro Social', 0, 1, 'C');
        $this->SetX(38.0);
        $this->Cell(125.0, 4.2, 'Direccion de Prestaciones Médicas', 0, 1, 'C');
        $this->SetX(38.0);
        $this->Cell(125.0, 4.2, 'Unidad de Atencion Primaria a la Salud', 0, 1, 'C');
        $this->SetX(38.0);
        $this->Cell(125.0, 4.2, 'Coordinacion de Atención Integral a la Salud en el Primer Nivel', 0, 1, 'C');

        // Emblema ¡CHKT! en línea / PREVENIMSS (esquina superior derecha)
        $this->SetTextColor(198, 40, 40);
        $this->SetFont('helvetica', 'B', 14.5);
        $this->SetXY(164.0, 10.0);
        $this->Cell(24.0, 5.5, '¡CHKT!', 0, 0, 'C');
        $this->SetFont('helvetica', 'B', 7.8);
        $this->SetXY(164.0, 15.2);
        $this->Cell(24.0, 3.8, 'en línea', 0, 0, 'C');

        // Círculo rojo de CHKT
        $this->SetFillColor(211, 47, 47);
        $this->SetDrawColor(211, 47, 47);
        $this->Circle(193.5, 14.5, 4.5, 0, 360, 'DF');
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(0.35);
        $this->Circle(193.5, 14.5, 2.6, 0, 360, 'D');

        // PREVENIMSS
        $this->SetXY(164.0, 19.6);
        $this->SetFont('helvetica', 'B', 10.2);
        $this->SetTextColor(84, 110, 122);
        $this->Cell(17.5, 4.5, 'PREVEN', 0, 0, 'R');
        $this->SetTextColor(27, 94, 32);
        $this->Cell(16.0, 4.5, 'IMSS', 0, 0, 'L');

        // Restaurar colores base
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $this->SetFillColor(255, 255, 255);
        $this->SetLineWidth(0.25);

        // Título central del formato
        $this->SetFont('helvetica', 'B', 10.8);
        $this->SetXY(13.5, 34.5);
        $this->Cell(189.0, 5.5, 'Cuestionario de cálculo de riesgo para la detección de Cáncer de Próstata', 0, 1, 'C');

        // 2. Tabla superior de identificación (Nombre, Número de seguridad social, Fecha)
        $idX = 27.5;
        $idY = 42.5;
        $idW1 = 46.5;
        $idW2 = 109.5;

        $patName = trim(implode(' ', array_filter([
            $a['apellido_paterno'] ?? null,
            $a['apellido_materno'] ?? null,
            $a['nombres'] ?? null,
        ])));
        if ($patName === '' && !empty($a['nombre_completo'])) {
            $patName = (string)$a['nombre_completo'];
        }

        $nss = trim((string)($a['nss'] ?? ''));
        $dv = trim((string)($a['dv'] ?? ''));
        $agregado = trim((string)($a['agregado'] ?? ''));
        $nssDisplay = $nss . ($dv !== '' ? '-' . $dv : '') . ($agregado !== '' ? '   ' . $agregado : '');
        $fechaDisplay = self::dateSlash((string)($a['fecha'] ?? date('Y-m-d')));

        // Fila 1: Nombre
        $this->Rect($idX, $idY, $idW1, 6.0);
        $this->Rect($idX + $idW1, $idY, $idW2, 6.0);
        $this->SetFont('helvetica', '', 9.5);
        $this->SetXY($idX + 1.8, $idY + 0.8);
        $this->Cell($idW1 - 2.5, 4.5, 'Nombre', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 9.0);
        $this->SetXY($idX + $idW1 + 2.0, $idY + 0.8);
        $this->Cell($idW2 - 4.0, 4.5, $patName, 0, 0, 'L');

        // Fila 2: Numero de seguridad social
        $idY2 = $idY + 6.0;
        $this->Rect($idX, $idY2, $idW1, 9.5);
        $this->Rect($idX + $idW1, $idY2, $idW2, 9.5);
        $this->SetFont('helvetica', '', 9.5);
        $this->SetXY($idX + 1.8, $idY2 + 0.8);
        $this->MultiCell($idW1 - 2.5, 4.0, "Numero de seguridad\nsocial", 0, 'L');
        $this->SetFont('helvetica', 'B', 9.5);
        $this->SetXY($idX + $idW1 + 2.0, $idY2 + 2.5);
        $this->Cell($idW2 - 4.0, 4.5, $nssDisplay, 0, 0, 'L');

        // Fila 3: Fecha
        $idY3 = $idY2 + 9.5;
        $this->Rect($idX, $idY3, $idW1, 6.0);
        $this->Rect($idX + $idW1, $idY3, $idW2, 6.0);
        $this->SetFont('helvetica', '', 9.5);
        $this->SetXY($idX + 1.8, $idY3 + 0.8);
        $this->Cell($idW1 - 2.5, 4.5, 'Fecha', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 9.5);
        $this->SetXY($idX + $idW1 + 2.0, $idY3 + 0.8);
        $this->Cell($idW2 - 4.0, 4.5, $fechaDisplay, 0, 0, 'L');

        // 3. Renglón UP y Grupo de Riesgo
        $upY = 67.5;
        $this->SetFont('helvetica', '', 10.0);
        $this->SetXY(27.5, $upY);
        $this->Cell(9.0, 5.0, 'UP:', 0, 0, 'L');

        $upValue = trim((string)($a['unidad_clave'] ?? $a['unidad_nombre'] ?? ''));
        $consRef = trim((string)($a['consultorio_nombre'] ?? $a['consultorio_clave'] ?? ''));
        if ($consRef !== '') {
            $upValue = trim($upValue . ' / CONS. ' . $consRef, ' /');
        }
        $this->SetFont('helvetica', 'B', 8.2);
        $this->SetXY(36.5, $upY);
        $this->Cell(38.0, 4.8, $upValue, 0, 0, 'C');
        $this->Line(36.5, $upY + 4.8, 74.5, $upY + 4.8);

        $grupoRiesgo = (string)($a['grupo_riesgo'] ?? '45_74');
        $is4044 = ($grupoRiesgo === '40_44_AHF');
        $is4574 = ($grupoRiesgo === '45_74');

        // Opción 1: Hombres 40 a 44 años con antecedente heredofamiliar
        $this->SetXY(76.0, $upY - 0.5);
        if ($is4044) {
            $this->SetFillColor(220, 245, 232);
            $this->SetDrawColor(19, 50, 43);
            $this->Rect(76.0, $upY - 0.5, 114.0, 5.0, 'DF');
            $this->SetDrawColor(0, 0, 0);
        }
        $this->SetFont('helvetica', 'BI', 9.8);
        $this->SetXY(76.0, $upY - 0.5);
        $this->Cell(113.0, 5.0, 'Hombres 40 a 44 años con antecedente heredofamiliar', 0, 1, 'R');

        // Opción 2: Hombres 45 a 74 años
        $this->SetXY(128.0, $upY + 4.6);
        if ($is4574) {
            $this->SetFillColor(220, 245, 232);
            $this->SetDrawColor(19, 50, 43);
            $this->Rect(132.0, $upY + 4.6, 58.0, 5.0, 'DF');
            $this->SetDrawColor(0, 0, 0);
        }
        $this->SetFont('helvetica', 'B', 9.8);
        $this->SetXY(128.0, $upY + 4.6);
        $this->Cell(61.0, 5.0, 'Hombres 45 a 74 años', 0, 1, 'R');

        // 4. Tabla Principal del Cuestionario
        $gridX = 13.5;
        $gridY = 78.0;
        $colWidths = [79.5, 18.0, 13.5, 18.0, 16.5, 20.5, 19.5]; // Total = 185.5 mm
        $totalGridW = array_sum($colWidths);
        $headerH = 19.0;

        $headers = [
            "PREGUNTAS",
            "Nunca",
            "Una vez\ncada\ncinco",
            "Menos\nde la\nmitad de\nveces",
            "La mitad\nde las\nveces",
            "Más de la\nmitad de\nlas veces",
            "Casi\nsiempre",
        ];

        $this->SetFillColor(255, 255, 255);
        $this->SetLineWidth(0.25);
        $cx = $gridX;
        foreach ($headers as $idx => $hText) {
            $w = $colWidths[$idx];
            $this->Rect($cx, $gridY, $w, $headerH);
            $this->SetFont('helvetica', 'B', $idx === 0 ? 8.8 : 8.0);
            if ($idx === 0) {
                $this->SetXY($cx, $gridY + 7.0);
                $this->Cell($w, 5.0, $hText, 0, 0, 'C');
            } else {
                $this->SetXY($cx + 0.5, $gridY + 1.2);
                $this->MultiCell($w - 1.0, 3.6, $hText, 0, 'C');
            }
            $cx += $w;
        }

        // Textos enriquecidos con las negritas oficiales del formato IMSS
        $styledQuestions = [
            1 => 'Durante el último mes ¿Con qué frecuencia tuvo la sensación de <b>no haber vaciado completamente</b> la vejiga después de orinar?',
            2 => 'Durante el último mes ¿Con qué frecuencia <b>debió orinar nuevamente</b> en menos de dos horas después de haber terminado de orinar?',
            3 => 'Durante el último mes ¿Con qué frecuencia descubrió que <b>al orinar se detenía</b> y comenzaba a orinar nuevamente?',
            4 => 'Durante el último mes ¿Cuántas veces le resultó <b>difícil demorar la micción</b>?',
            5 => 'Durante el último mes ¿Cuántas veces ha tenido un <b>chorro urinario débil</b>?',
            6 => 'Durante el último mes ¿Cuántas veces tuvo que <b>esforzarse para comenzar a orinar</b>?',
            7 => 'Durante el último mes ¿Cuántas veces <b>se ha levantado para orinar</b> desde que se acostó por la noche hasta que se levantó en la mañana?',
        ];

        $rowY = $gridY + $headerH;
        $rowH = 16.2;

        if (empty($questions)) {
            foreach ($styledQuestions as $ord => $plain) {
                $questions[] = ['id' => $ord, 'orden' => $ord, 'pregunta' => strip_tags($plain)];
            }
        }

        foreach ($questions as $qIdx => $qRow) {
            $qId = (int)($qRow['id'] ?? ($qIdx + 1));
            $orden = (int)($qRow['orden'] ?? ($qIdx + 1));
            $selectedVal = $answers[$qId] ?? $answers[(string)$qId] ?? null;
            if ($selectedVal !== null && $selectedVal !== '') {
                $selectedVal = (int)$selectedVal;
            } else {
                $selectedVal = null;
            }

            // Celda de pregunta
            $this->Rect($gridX, $rowY, $colWidths[0], $rowH);
            $this->SetFont('helvetica', '', 8.3);
            $htmlQuestion = $styledQuestions[$orden] ?? htmlspecialchars((string)$qRow['pregunta'], ENT_QUOTES, 'UTF-8');
            $this->writeHTMLCell($colWidths[0] - 2.4, $rowH - 2.0, $gridX + 1.2, $rowY + 2.2, $htmlQuestion, 0, 0, false, true, 'L', true);

            // 6 celdas de puntaje (0 a 5)
            $cx = $gridX + $colWidths[0];
            for ($score = 0; $score <= 5; $score++) {
                $cw = $colWidths[$score + 1];
                $this->SetLineWidth(0.25);
                $this->SetDrawColor(0, 0, 0);
                $this->Rect($cx, $rowY, $cw, $rowH);

                $isSelected = ($selectedVal !== null && $selectedVal === $score);
                $centerX = $cx + ($cw / 2.0);
                $centerY = $rowY + ($rowH / 2.0) + 1.0;

                if ($isSelected) {
                    $this->SetFillColor(19, 50, 43);
                    $this->SetDrawColor(19, 50, 43);
                    $this->SetTextColor(255, 255, 255);
                } else {
                    $this->SetFillColor(255, 255, 255);
                    $this->SetDrawColor(0, 0, 0);
                    $this->SetTextColor(0, 0, 0);
                }

                $this->SetLineWidth(0.55);
                // En el formato oficial IMSS: 0, 2 y 4 van en círculo; 1, 3 y 5 van en recuadro
                if ($score % 2 === 0) {
                    $this->Circle($centerX, $centerY, 5.0, 0, 360, $isSelected ? 'DF' : 'D');
                } else {
                    $boxSize = 9.4;
                    $this->Rect($centerX - ($boxSize / 2.0), $centerY - ($boxSize / 2.0), $boxSize, $boxSize, $isSelected ? 'DF' : 'D');
                }

                $this->SetFont('helvetica', 'B', $isSelected ? 9.5 : 8.5);
                $this->SetXY($centerX - 4.5, $centerY - 2.2);
                $this->Cell(9.0, 4.4, (string)$score, 0, 0, 'C');

                // Restaurar estado gráfico
                $this->SetTextColor(0, 0, 0);
                $this->SetDrawColor(0, 0, 0);
                $this->SetFillColor(255, 255, 255);
                $this->SetLineWidth(0.25);

                $cx += $cw;
            }

            $rowY += $rowH;
        }

        // 5. Renglón de suma total
        $sumH = 12.5;
        $this->Rect($gridX, $rowY, $totalGridW, $sumH);
        $this->SetFont('helvetica', 'B', 9.0);
        $this->SetXY($gridX + 1.2, $rowY + 4.0);
        $this->Cell(154.0, 5.0, 'Sume los números correspondientes a cada uno de sus respuestas y valide con la valoración', 0, 0, 'L');

        // Recuadro del puntaje total
        $scoreBoxX = $gridX + 158.0;
        $scoreBoxY = $rowY + 1.8;
        $scoreBoxW = 13.0;
        $scoreBoxH = 9.0;
        $this->SetLineWidth(0.6);
        $this->SetFillColor(234, 243, 240);
        $this->Rect($scoreBoxX, $scoreBoxY, $scoreBoxW, $scoreBoxH, 'DF');
        $this->SetLineWidth(0.25);
        $this->SetFillColor(255, 255, 255);

        $puntajeTotal = isset($a['puntaje_total']) ? (int)$a['puntaje_total'] : 0;
        $this->SetFont('helvetica', 'B', 11.5);
        $this->SetXY($scoreBoxX, $scoreBoxY + 1.8);
        $this->Cell($scoreBoxW, 5.4, (string)$puntajeTotal, 0, 0, 'C');

        $rowY += $sumH;

        // 6. Recuadro inferior: Valoración, Enfermera/Matrícula y Escala Clínica
        $botH = 22.0;
        $leftW = $colWidths[0];
        $rightW = $totalGridW - $leftW;

        $this->Rect($gridX, $rowY, $leftW, $botH);
        $this->Rect($gridX + $leftW, $rowY, $rightW, $botH);

        $valoracionActual = (string)($a['valoracion'] ?? '');
        $enfNombre = mb_strtoupper(trim((string)($a['enfermera_nombre'] ?? '')), 'UTF-8');
        $enfMatricula = trim((string)($a['matricula'] ?? $a['enfermera_matricula'] ?? ''));

        // Columna izquierda inferior
        $this->SetFont('helvetica', 'B', 9.5);
        $this->SetXY($gridX + 1.5, $rowY + 4.0);
        $this->Cell(23.0, 4.5, 'Valoración :', 0, 0, 'L');
        $this->SetTextColor(19, 50, 43);
        $this->SetFont('helvetica', 'B', 9.2);
        $this->Cell($leftW - 26.0, 4.5, $valoracionActual, 0, 0, 'L');
        $this->SetTextColor(0, 0, 0);

        $this->SetFont('helvetica', '', 9.5);
        $this->SetXY($gridX + 1.5, $rowY + 9.5);
        $this->Write(4.5, 'ENFRA(O): ');
        $this->SetFont('helvetica', 'U', 9.2);
        $this->Write(4.5, $enfNombre !== '' ? $enfNombre : '________________________');

        $this->SetFont('helvetica', '', 9.5);
        $this->SetXY($gridX + 1.5, $rowY + 14.8);
        $this->Write(4.5, 'MATRICULA: ');
        $this->SetFont('helvetica', 'U', 9.5);
        $this->Write(4.5, $enfMatricula !== '' ? $enfMatricula : '______________');

        // Columna derecha inferior: Escala oficial
        $scaleItems = [
            ['min' => 0,  'max' => 0,   'html' => '0 <b>Normal</b>'],
            ['min' => 1,  'max' => 7,   'html' => '1-7 Sintomatología <b>LEVE</b>'],
            ['min' => 8,  'max' => 19,  'html' => '8-19 Sintomatología <b>MODERADA</b>'],
            ['min' => 20, 'max' => 999, 'html' => 'Arriba de 20 Sintomatología <b>SEVERA</b>'],
        ];

        $sy = $rowY + 1.8;
        foreach ($scaleItems as $item) {
            $activeRange = ($puntajeTotal >= $item['min'] && $puntajeTotal <= $item['max']);
            if ($activeRange) {
                $this->SetFillColor(220, 245, 232);
                $this->SetDrawColor(19, 50, 43);
                $this->Rect($gridX + $leftW + 1.2, $sy - 0.3, $rightW - 2.4, 4.6, 'DF');
                $this->SetDrawColor(0, 0, 0);
                $this->SetFillColor(255, 255, 255);
            }
            $this->SetFont('helvetica', '', 8.6);
            $this->writeHTMLCell($rightW - 4.0, 4.4, $gridX + $leftW + 2.0, $sy, $item['html'], 0, 1, false, true, 'L', true);
            $sy += 4.7;
        }
    }

    /**
     * Detección de Cáncer de Colon y Recto sobre el formato oficial UMF 40 B.C.S.
     * Una sola página (Carta vertical).
     */
    public function colon(array $a, array $answers): void
    {
        $this->AddPage('P', 'LETTER');
        $this->SetAutoPageBreak(false, 0);

        $bgPath = ENFERMERIA_ROOT . '/templates/pdf/02_deteccion_ca_colon_recto_bg.png';
        if (file_exists($bgPath)) {
            // Carta: 215.9 mm ancho x 279.4 mm alto
            $this->Image($bgPath, 0, 0, 215.9, 279.4, 'PNG');
        }

        // Corregir título duplicado del formato base ("Detección de Cáncer de Cáncer de Colon y Recto")
        $this->SetFillColor(255, 255, 255);
        $this->Rect(21.5, 42.0, 170.0, 10.2, 'F');
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('helvetica', '', 19.5);
        $this->SetXY(22.5, 43.0);
        $this->Cell(165.0, 8.0, 'Detección de Cáncer de Colon y Recto', 0, 0, 'L');

        // Datos del paciente
        $pacienteNombre = mb_strtoupper(trim((string)($a['paciente_nombre'] ?? $a['nombre_completo'] ?? '')), 'UTF-8');
        $nss = trim((string)($a['nss'] ?? ''));
        $agregado = trim((string)($a['agregado'] ?? ''));
        $nssDisplay = $agregado !== '' ? ($nss . ' ' . $agregado) : $nss;
        $cons = mb_strtoupper(trim((string)($a['consultorio'] ?? $a['consultorio_nombre'] ?? $a['consultorio_clave'] ?? '')), 'UTF-8');
        $turno = mb_strtoupper(trim((string)($a['turno'] ?? $a['turno_nombre'] ?? $a['turno_clave'] ?? '')), 'UTF-8');
        $edad = trim((string)($a['edad'] ?? $a['edad_anios'] ?? ''));
        if ($edad !== '' && is_numeric($edad)) {
            $edad .= ' AÑOS';
        }
        $fecha = trim((string)($a['fecha'] ?? ''));
        $fecha = $fecha !== '' ? self::dateSlash($fecha) : date('d/m/Y');
        $up = trim((string)($a['up'] ?? ''));
        $citaLab = trim((string)($a['cita_laboratorio'] ?? ''));
        if ($citaLab !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $citaLab)) {
            $citaLab = self::dateSlash($citaLab);
        }
        $citaEemf = trim((string)($a['cita_eemf'] ?? ''));
        if ($citaEemf !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $citaEemf)) {
            $citaEemf = self::dateSlash($citaEemf);
        }

        // 1. Nombre
        $this->SetFont('helvetica', 'B', 10);
        $this->SetXY(46.0, 56.2);
        $this->Cell(143.0, 5.0, $pacienteNombre, 0, 0, 'L');

        // 2. NSS
        $this->SetXY(35.0, 61.9);
        $this->Cell(98.0, 5.0, $nssDisplay, 0, 0, 'L');

        // 3. Edad, Consultorio, Turno
        $this->SetFont('helvetica', 'B', 9.5);
        $this->SetXY(38.0, 73.2);
        $this->Cell(24.0, 5.0, $edad, 0, 0, 'C');
        $this->SetXY(100.2, 73.2);
        $this->Cell(24.0, 5.0, $cons, 0, 0, 'C');
        $this->SetXY(141.0, 73.2);
        $this->Cell(34.0, 5.0, $turno, 0, 0, 'C');

        // 4. Fecha, UP
        $this->SetXY(41.0, 78.9);
        $this->Cell(46.0, 5.0, $fecha, 0, 0, 'C');
        $this->SetXY(104.2, 78.9);
        $this->Cell(55.5, 5.0, $up, 0, 0, 'C');

        // 5. Cita Laboratorio, Cita EEMF
        $this->SetXY(72.5, 90.3);
        $this->Cell(32.5, 5.0, $citaLab, 0, 0, 'C');
        $this->SetXY(147.0, 90.3);
        $this->Cell(44.5, 5.0, $citaEemf, 0, 0, 'C');

        // 6. Preguntas 1 a 5 (centradas horizontalmente sobre cada línea de respuesta y en 11.5pt negrita)
        $qLines = [
            1 => [108.0, 82.5, 114.0, mb_strtoupper(trim((string)($answers[1] ?? $answers['q1'] ?? 'NO')), 'UTF-8')],
            2 => [58.5, 133.0, 135.0, mb_strtoupper(trim((string)($answers[2] ?? $answers['q2'] ?? 'NO')), 'UTF-8')],
            3 => [167.0, 26.5, 142.0, mb_strtoupper(trim((string)($answers[3] ?? $answers['q3'] ?? 'NO')), 'UTF-8')],
            4 => [120.0, 73.5, 156.0, mb_strtoupper(trim((string)($answers[4] ?? $answers['q4'] ?? 'NO')), 'UTF-8')],
            5 => [126.0, 67.0, 163.0, mb_strtoupper(trim((string)($answers[5] ?? $answers['q5'] ?? 'NO')), 'UTF-8')],
        ];

        $this->SetFont('helvetica', 'B', 11.5);
        foreach ($qLines as $idx => [$lineX, $lineW, $lineY, $ansVal]) {
            $isSi = in_array($ansVal, ['SI', 'SÍ'], true);
            $text = $isSi ? 'SÍ' : 'NO';
            $this->SetTextColor(0, 0, 0);
            $this->SetXY($lineX, $lineY);
            $this->Cell($lineW, 5.2, $text, 0, 0, 'C');
        }

        // 7. Bloque inferior de Firma / Enfermera y Matrícula (limpiar fondo con rectángulo blanco)
        $this->SetFillColor(255, 255, 255);
        $this->Rect(21.5, 179.0, 172.0, 16.5, 'F');

        $prefijo = rtrim(mb_strtoupper(trim((string)($a['prefijo'] ?? $a['enfermera_prefijo'] ?? '')), 'UTF-8'), ':');
        if ($prefijo === '') {
            $prefijo = 'ENFRA(O).';
        }
        $nurse = mb_strtoupper(trim((string)($a['enfermera_nombre'] ?? '')), 'UTF-8');
        $matr = trim((string)($a['matricula'] ?? ''));

        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.25);

        // Renglón 1: [PREFIJO]: [NOMBRE COMPLETO DEL USUARIO]
        $row1Y = 179.8;
        $this->SetFont('helvetica', '', 10.0);
        $prefixLabel = $prefijo . ':';
        $prefixW = $this->GetStringWidth($prefixLabel) + 3.0;
        $this->SetXY(22.5, $row1Y);
        $this->Cell($prefixW, 5.2, $prefixLabel, 0, 0, 'L');

        $this->SetFont('helvetica', 'B', 10.0);
        $nurseValW = max(75.0, $this->GetStringWidth($nurse) + 8.0);
        $this->SetXY(22.5 + $prefixW + 1.5, $row1Y);
        $this->Cell($nurseValW, 5.0, $nurse, 'B', 0, 'L');

        // Renglón 2: MATRÍCULA:   [MATRICULA DEL USUARIO] (con separación vertical de 7.5 mm)
        $row2Y = 187.3;
        $this->SetFont('helvetica', '', 10.0);
        $matLabel = 'MATRÍCULA:';
        $matLabelW = $this->GetStringWidth($matLabel) + 3.0;
        $this->SetXY(22.5, $row2Y);
        $this->Cell($matLabelW, 5.2, $matLabel, 0, 0, 'L');

        $this->SetFont('helvetica', 'B', 10.0);
        $matValW = max(35.0, $this->GetStringWidth($matr) + 8.0);
        $this->SetXY(22.5 + $matLabelW + 2.0, $row2Y);
        $this->Cell($matValW, 5.0, $matr, 'B', 0, 'C');
    }

    /**
     * Cuestionario de Salud Mental. Detección de ansiedad, depresión y trastornos de pánico (PHQ-4 + PHQ-TP).
     * Diseño institucional IMSS en 1 sola hoja tamaño Carta (2 tantos idénticos: media carta superior e inferior).
     */
    public function saludMental(array $a, array $questions, array $answers): void
    {
        $this->AddPage('P', 'LETTER');
        $this->SetAutoPageBreak(false, 0);

        // Tanto 1: Media carta superior
        $this->renderSaludMentalHalf(5.5, $a, $questions, $answers);

        // Línea punteada sutil de corte a la mitad exacta de la hoja Carta (Y = 139.7 mm)
        $this->SetLineStyle(['width' => 0.25, 'cap' => 'butt', 'join' => 'miter', 'dash' => '3,3', 'color' => [140, 155, 150]]);
        $this->Line(10.0, 139.7, 205.9, 139.7);
        $this->SetLineStyle(['width' => 0.25, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => [0, 0, 0]]);

        // Tanto 2: Media carta inferior
        $this->renderSaludMentalHalf(144.0, $a, $questions, $answers);
    }

    private function renderSaludMentalHalf(float $offsetY, array $a, array $questions, array $answers): void
    {
        $leftX = 12.0;
        $totalW = 191.9; // 215.9 - 24.0

        // 1. Encabezado Institucional IMSS
        $logoPath = ENFERMERIA_ROOT . '/public/assets/images/imss.jpg';
        if (!file_exists($logoPath)) {
            $logoPath = ENFERMERIA_ROOT . '/imss.jpg';
        }
        if (file_exists($logoPath)) {
            $this->Image($logoPath, $leftX + 1.0, $offsetY, 12.0, 13.2, 'JPG');
        }

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('helvetica', 'B', 8.4);
        $this->SetXY($leftX + 15.0, $offsetY + 0.6);
        $this->Cell(136.0, 3.8, 'INSTITUTO MEXICANO DEL SEGURO SOCIAL', 0, 1, 'C');

        $this->SetFont('helvetica', 'B', 7.6);
        $this->SetX($leftX + 15.0);
        $this->Cell(136.0, 3.6, 'Dirección de Prestaciones Médicas', 0, 1, 'C');

        $this->SetFont('helvetica', '', 7.2);
        $this->SetX($leftX + 15.0);
        $this->Cell(136.0, 3.5, 'Unidad de Atención Primaria a la Salud · Coordinación de Atención Integral a la Salud en el Primer Nivel', 0, 1, 'C');

        // Emblema ¡CHKT! en línea / PREVENIMSS (esquina superior derecha)
        $this->SetTextColor(198, 40, 40);
        $this->SetFont('helvetica', 'B', 11.0);
        $this->SetXY($leftX + 154.0, $offsetY + 0.2);
        $this->Cell(22.0, 4.2, '¡CHKT!', 0, 0, 'C');
        $this->SetFont('helvetica', 'B', 6.5);
        $this->SetXY($leftX + 154.0, $offsetY + 4.1);
        $this->Cell(22.0, 3.0, 'en línea', 0, 0, 'C');

        $this->SetFillColor(211, 47, 47);
        $this->SetDrawColor(211, 47, 47);
        $this->Circle($leftX + 181.5, $offsetY + 3.8, 3.2, 0, 360, 'DF');
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(0.3);
        $this->Circle($leftX + 181.5, $offsetY + 3.8, 1.8, 0, 360, 'D');

        $this->SetXY($leftX + 152.0, $offsetY + 7.6);
        $this->SetFont('helvetica', 'B', 8.2);
        $this->SetTextColor(84, 110, 122);
        $this->Cell(16.5, 3.6, 'PREVEN', 0, 0, 'R');
        $this->SetTextColor(27, 94, 32);
        $this->Cell(15.0, 3.6, 'IMSS', 0, 0, 'L');

        // Banda de título en verde institucional (#13322B)
        $bandY = $offsetY + 13.8;
        $bandH = 6.2;
        $this->SetFillColor(19, 50, 43);
        $this->SetDrawColor(19, 50, 43);
        $this->SetLineWidth(0.25);
        $this->Rect($leftX, $bandY, $totalW, $bandH, 'DF');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 8.3);
        $this->SetXY($leftX, $bandY + 1.0);
        $this->Cell($totalW, 4.2, 'Cuestionario de salud mental. Detección de ansiedad, depresión y trastornos de pánico (PHQ-4 + PHQ-TP)', 0, 0, 'C');

        // Restaurar colores
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $this->SetFillColor(255, 255, 255);

        // 2. Cintillo de Datos Precargados (Paciente, Unidad y Enfermera)
        $patName = mb_strtoupper(trim(implode(' ', array_filter([
            $a['apellido_paterno'] ?? null,
            $a['apellido_materno'] ?? null,
            $a['nombres'] ?? null,
        ]))), 'UTF-8');
        if ($patName === '' && !empty($a['nombre_completo'])) {
            $patName = mb_strtoupper(trim((string)$a['nombre_completo']), 'UTF-8');
        }

        $nss = trim((string)($a['nss'] ?? ''));
        $dv = trim((string)($a['dv'] ?? ''));
        $agregado = trim((string)($a['agregado'] ?? ''));
        $nssDisplay = trim($nss . ($dv !== '' ? '-' . $dv : '') . ($agregado !== '' ? '  ' . $agregado : ''));

        $fechaEval = self::dateSlash((string)($a['fecha'] ?? date('Y-m-d')));
        $unidadDisplay = trim((string)($a['unidad_nombre'] ?? $a['unidad_clave'] ?? ''));
        if ($unidadDisplay === '') {
            $unidadDisplay = 'UMF 40';
        }
        $edadDisplay = trim((string)($a['edad'] ?? $a['edad_anios'] ?? ''));
        if ($edadDisplay !== '' && is_numeric($edadDisplay)) {
            $edadDisplay .= ' AÑOS';
        }
        $consDisplay = trim((string)($a['consultorio_nombre'] ?? $a['consultorio_clave'] ?? $a['consultorio'] ?? ''));
        $telDisplay = trim((string)($a['telefono'] ?? ''));

        $prefijo = rtrim(mb_strtoupper(trim((string)($a['prefijo'] ?? $a['enfermera_prefijo'] ?? 'L.E.')), 'UTF-8'), ':');
        if ($prefijo === '') {
            $prefijo = 'ENFRA(O).';
        }
        $enfNombre = mb_strtoupper(trim((string)($a['enfermera_nombre'] ?? '')), 'UTF-8');
        $enfMatricula = trim((string)($a['matricula'] ?? $a['enfermera_matricula'] ?? ''));

        $defaultQuestions = [
            1 => ['id' => 1, 'orden' => 1, 'seccion' => 'PHQ4', 'pregunta' => '1. Sentirse nervioso, angustiado o muy tenso'],
            2 => ['id' => 2, 'orden' => 2, 'seccion' => 'PHQ4', 'pregunta' => '2. Ser incapaz de controlar las preocupaciones'],
            3 => ['id' => 3, 'orden' => 3, 'seccion' => 'PHQ4', 'pregunta' => '3. Tener poco interés o alegría por hacer cosas'],
            4 => ['id' => 4, 'orden' => 4, 'seccion' => 'PHQ4', 'pregunta' => '4. Sentirse decaído, deprimido o sin esperanza'],
            5 => ['id' => 5, 'orden' => 5, 'seccion' => 'PHQTP', 'pregunta' => '5. En las últimas 4 semanas, ¿ha tenido algún ataque de ansiedad o sensación repentina de pánico o miedo?'],
        ];

        $qMap = $defaultQuestions;
        foreach ($questions as $qItem) {
            $ord = (int)($qItem['orden'] ?? 0);
            if ($ord >= 1 && $ord <= 5) {
                $qMap[$ord] = $qItem;
            }
        }

        // Precalcular puntajes por bloque y claves UP para mostrar tanto en el encabezado como en la tabla final
        $ordScores = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $hasPhqAnswers = false;
        for ($ord = 1; $ord <= 4; $ord++) {
            $qId = (int)($qMap[$ord]['id'] ?? $ord);
            $rawAns = $answers[$qId] ?? $answers[(string)$qId] ?? null;
            if ($rawAns !== null && $rawAns !== '') {
                $ordScores[$ord] = (int)$rawAns;
                $hasPhqAnswers = true;
            }
        }
        $q5Id = (int)($qMap[5]['id'] ?? 5);
        $q5Val = $answers[$q5Id] ?? $answers[(string)$q5Id] ?? ($a['ataque_panico'] ?? null);
        $q5Val = ($q5Val !== null && $q5Val !== '') ? (int)$q5Val : null;

        $sumAnsiedad = $hasPhqAnswers
            ? ($ordScores[1] + $ordScores[2])
            : (int)($a['puntaje_ansiedad'] ?? 0);
        $sumDepresion = $hasPhqAnswers
            ? ($ordScores[3] + $ordScores[4])
            : (int)($a['puntaje_depresion'] ?? 0);

        $isPosAnsiedad = ($sumAnsiedad > 3);
        $isPosDepresion = ($sumDepresion >= 3);
        $isPosPanico = ($q5Val === 1);

        $computedUps = [];
        $computedInterps = [];
        if ($isPosAnsiedad) {
            $computedUps[] = 'UP307';
            $computedInterps[] = 'PB TRASTORNO DE ANSIEDAD';
        }
        if ($isPosDepresion) {
            $computedUps[] = 'UP306';
            $computedInterps[] = 'PB TRASTORNO DEPRESIVO';
        }
        if ($isPosPanico) {
            $computedUps[] = 'UP308';
            $computedInterps[] = 'PB TRASTORNO DE PANICO, MIEDO';
        }

        $claveUpDisplay = trim((string)($a['clave_up'] ?? ''));
        if ($claveUpDisplay === '') {
            $claveUpDisplay = !empty($computedUps) ? implode(', ', $computedUps) : '—';
        }
        $sitioEnvioDisplay = trim((string)($a['sitio_envio'] ?? ''));
        if ($sitioEnvioDisplay === '') {
            $sitioEnvioDisplay = !empty($computedUps) ? 'ENVIO MF' : '—';
        }
        $valoracionDisplay = !empty($computedInterps)
            ? implode(' / ', $computedInterps)
            : 'SIN RIESGO DETECTADO / NORMAL';

        $infoY = $offsetY + 20.8;
        $infoRowH = 4.9;

        // Fila 1: UNIDAD | UP | FECHA
        $this->SetFillColor(246, 250, 248);
        $this->Rect($leftX, $infoY, 84.0, $infoRowH, 'DF');
        $this->Rect($leftX + 84.0, $infoY, 50.0, $infoRowH, 'DF');
        $this->Rect($leftX + 134.0, $infoY, $totalW - 134.0, $infoRowH, 'DF');

        $this->SetFont('helvetica', 'B', 7.3);
        $this->SetXY($leftX + 1.5, $infoY + 0.7);
        $this->Cell(15.0, 3.5, 'UNIDAD:', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 7.6);
        $this->Cell(66.0, 3.5, $unidadDisplay, 0, 0, 'L');

        $this->SetFont('helvetica', 'B', 7.3);
        $this->SetXY($leftX + 85.5, $infoY + 0.7);
        $this->Cell(8.0, 3.5, 'UP:', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 7.6);
        $this->SetTextColor(19, 50, 43);
        $this->Cell(39.0, 3.5, $claveUpDisplay, 0, 0, 'L');
        $this->SetTextColor(0, 0, 0);

        $this->SetFont('helvetica', 'B', 7.3);
        $this->SetXY($leftX + 135.5, $infoY + 0.7);
        $this->Cell(13.0, 3.5, 'FECHA:', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 7.6);
        $this->Cell(42.0, 3.5, $fechaEval, 0, 0, 'L');

        // Fila 2: NOMBRE | EDAD
        $r2Y = $infoY + $infoRowH;
        $this->SetFillColor(255, 255, 255);
        $this->Rect($leftX, $r2Y, 142.0, $infoRowH, 'DF');
        $this->Rect($leftX + 142.0, $r2Y, $totalW - 142.0, $infoRowH, 'DF');
        $this->SetFont('helvetica', 'B', 7.3);
        $this->SetXY($leftX + 1.5, $r2Y + 0.7);
        $this->Cell(16.5, 3.5, 'NOMBRE:', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 7.8);
        $this->Cell(122.0, 3.5, $patName, 0, 0, 'L');

        $this->SetFont('helvetica', 'B', 7.3);
        $this->SetXY($leftX + 143.5, $r2Y + 0.7);
        $this->Cell(12.0, 3.5, 'EDAD:', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 7.6);
        $this->Cell(34.0, 3.5, $edadDisplay !== '' ? $edadDisplay : '—', 0, 0, 'L');

        // Fila 3: NSS | CONSULTORIO | TELÉFONO
        $r3Y = $r2Y + $infoRowH;
        $this->SetFillColor(246, 250, 248);
        $this->Rect($leftX, $r3Y, 74.0, $infoRowH, 'DF');
        $this->Rect($leftX + 74.0, $r3Y, 58.0, $infoRowH, 'DF');
        $this->Rect($leftX + 132.0, $r3Y, $totalW - 132.0, $infoRowH, 'DF');

        $this->SetFont('helvetica', 'B', 7.3);
        $this->SetXY($leftX + 1.5, $r3Y + 0.7);
        $this->Cell(10.0, 3.5, 'NSS:', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 7.6);
        $this->Cell(61.0, 3.5, $nssDisplay, 0, 0, 'L');

        $this->SetFont('helvetica', 'B', 7.3);
        $this->SetXY($leftX + 75.5, $r3Y + 0.7);
        $this->Cell(23.5, 3.5, 'CONSULTORIO:', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 7.6);
        $this->Cell(32.0, 3.5, $consDisplay !== '' ? $consDisplay : '—', 0, 0, 'L');

        $this->SetFont('helvetica', 'B', 7.3);
        $this->SetXY($leftX + 133.5, $r3Y + 0.7);
        $this->Cell(18.0, 3.5, 'TELÉFONO:', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 7.6);
        $this->Cell(38.0, 3.5, $telDisplay !== '' ? $telDisplay : '—', 0, 0, 'L');

        // Fila 4: [PREFIJO]: [NOMBRE ENFERMERA] | MATRÍCULA: [MATRICULA]
        $r4Y = $r3Y + $infoRowH;
        $this->SetFillColor(255, 255, 255);
        $this->Rect($leftX, $r4Y, 132.0, $infoRowH, 'DF');
        $this->Rect($leftX + 132.0, $r4Y, $totalW - 132.0, $infoRowH, 'DF');

        $prefLabel = $prefijo . ':';
        $this->SetFont('helvetica', 'B', 7.3);
        $prefW = max(14.0, $this->GetStringWidth($prefLabel) + 2.5);
        $this->SetXY($leftX + 1.5, $r4Y + 0.7);
        $this->Cell($prefW, 3.5, $prefLabel, 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 7.6);
        $this->Cell(128.0 - $prefW, 3.5, $enfNombre !== '' ? $enfNombre : '—', 0, 0, 'L');

        $this->SetFont('helvetica', 'B', 7.3);
        $this->SetXY($leftX + 133.5, $r4Y + 0.7);
        $this->Cell(20.0, 3.5, 'MATRÍCULA:', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 7.6);
        $this->Cell(36.0, 3.5, $enfMatricula !== '' ? $enfMatricula : '—', 0, 0, 'L');

        // 3. Instrucciones y Tabla PHQ-4
        $instY = $r4Y + $infoRowH + 0.6;
        $this->SetFont('helvetica', 'BI', 7.3);
        $this->SetTextColor(19, 50, 43);
        $this->SetXY($leftX, $instY);
        $this->Cell($totalW, 3.6, 'Instrucciones: Realice el siguiente cuestionario al paciente y marque los números que designen sus respuestas.', 0, 1, 'L');
        $this->SetTextColor(0, 0, 0);

        $tableY = $instY + 3.9;
        $qColW = 107.9;
        $optColW = 21.0; // 4 * 21.0 = 84.0 -> 107.9 + 84.0 = 191.9
        $thH = 8.0;

        // Cabecera PHQ-4
        $this->SetFillColor(19, 50, 43);
        $this->SetDrawColor(19, 50, 43);
        $this->SetTextColor(255, 255, 255);
        $this->Rect($leftX, $tableY, $qColW, $thH, 'DF');
        $this->SetFont('helvetica', 'B', 6.8);
        $this->SetXY($leftX + 1.8, $tableY + 0.9);
        $this->MultiCell($qColW - 3.6, 3.0, "En los últimos catorce días, ¿con qué frecuencia le ha molestado\nalguno de los siguientes problemas?", 0, 'L');

        $phq4Headers = [
            "Nunca",
            "Varios días",
            "Más de la mitad\nde los días",
            "Casi cada día",
        ];
        $cx = $leftX + $qColW;
        foreach ($phq4Headers as $hIdx => $hLabel) {
            $this->Rect($cx, $tableY, $optColW, $thH, 'DF');
            $this->SetFont('helvetica', 'B', 6.9);
            if (strpos($hLabel, "\n") !== false) {
                $this->SetXY($cx + 0.5, $tableY + 0.9);
                $this->MultiCell($optColW - 1.0, 3.0, $hLabel, 0, 'C');
            } else {
                $this->SetXY($cx, $tableY + 2.2);
                $this->Cell($optColW, 3.6, $hLabel, 0, 0, 'C');
            }
            $cx += $optColW;
        }

        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $this->SetFillColor(255, 255, 255);

        $rowY = $tableY + $thH;
        $rowH = 6.9;

        for ($ord = 1; $ord <= 4; $ord++) {
            $qRow = $qMap[$ord];
            $qId = (int)($qRow['id'] ?? $ord);
            $qText = (string)($qRow['pregunta'] ?? $defaultQuestions[$ord]['pregunta']);
            $selectedVal = $answers[$qId] ?? $answers[(string)$qId] ?? null;
            $selectedVal = ($selectedVal !== null && $selectedVal !== '') ? (int)$selectedVal : null;

            $this->SetFillColor($ord % 2 === 0 ? 248 : 255, $ord % 2 === 0 ? 251 : 255, $ord % 2 === 0 ? 250 : 255);
            $this->SetDrawColor(180, 190, 195);
            $this->Rect($leftX, $rowY, $qColW, $rowH, 'DF');
            $this->SetFont('helvetica', '', 7.6);
            $this->SetTextColor(0, 0, 0);
            $this->SetXY($leftX + 1.8, $rowY + 1.6);
            $this->Cell($qColW - 3.6, 3.6, $qText, 0, 0, 'L');

            $cx = $leftX + $qColW;
            for ($score = 0; $score <= 3; $score++) {
                $isSel = ($selectedVal !== null && $selectedVal === $score);
                $this->SetDrawColor(180, 190, 195);
                $this->SetFillColor(255, 255, 255);
                $this->Rect($cx, $rowY, $optColW, $rowH, 'DF');

                if ($isSel) {
                    $this->SetFillColor(220, 245, 232);
                    $this->SetDrawColor(19, 50, 43);
                    $this->SetLineWidth(0.5);
                    $this->Rect($cx + 2.5, $rowY + 0.9, $optColW - 5.0, $rowH - 1.8, 'DF');
                    $this->SetLineWidth(0.25);
                    $this->SetFont('helvetica', 'B', 8.8);
                    $this->SetTextColor(19, 50, 43);
                } else {
                    $this->SetFont('helvetica', '', 7.8);
                    $this->SetTextColor(70, 80, 85);
                }

                $this->SetXY($cx, $rowY + 1.6);
                $this->Cell($optColW, 3.6, (string)$score, 0, 0, 'C');
                $cx += $optColW;
            }

            $rowY += $rowH;
        }

        // 4. Bloque PHQ-TP (Pregunta 5)
        $tpY = $rowY + 1.1;
        $tpH = 8.6;
        $q5Row = $qMap[5];
        $q5Text = (string)($q5Row['pregunta'] ?? $defaultQuestions[5]['pregunta']);

        $tagW = 18.0;
        $tpQColW = $qColW + ($optColW * 2) - $tagW; // 131.9
        $tpOptW = $optColW; // 21.0 cada una (Sí y No)

        // Etiqueta PHQ-TP
        $this->SetFillColor(19, 50, 43);
        $this->SetDrawColor(19, 50, 43);
        $this->Rect($leftX, $tpY, $tagW, $tpH, 'DF');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 7.6);
        $this->SetXY($leftX, $tpY + 2.4);
        $this->Cell($tagW, 3.8, 'PHQ-TP', 0, 0, 'C');

        // Texto Pregunta 5
        $this->SetFillColor(246, 250, 248);
        $this->SetDrawColor(19, 50, 43);
        $this->Rect($leftX + $tagW, $tpY, $tpQColW, $tpH, 'DF');
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('helvetica', 'B', 6.8);
        $this->SetXY($leftX + $tagW + 1.8, $tpY + 2.4);
        $this->Cell($tpQColW - 3.6, 3.8, $q5Text, 0, 0, 'L');

        // Casillas Sí y No
        $tpChoices = [
            ['label' => 'Sí', 'val' => 1],
            ['label' => 'No', 'val' => 0],
        ];
        $cx = $leftX + $tagW + $tpQColW;
        foreach ($tpChoices as $tpOpt) {
            $isSel = ($q5Val !== null && $q5Val === $tpOpt['val']);
            $this->SetDrawColor(19, 50, 43);
            $this->SetFillColor(255, 255, 255);
            $this->Rect($cx, $tpY, $tpOptW, $tpH, 'DF');

            if ($isSel) {
                $this->SetFillColor(220, 245, 232);
                $this->SetDrawColor(19, 50, 43);
                $this->SetLineWidth(0.55);
                $this->Rect($cx + 2.0, $tpY + 1.1, $tpOptW - 4.0, $tpH - 2.2, 'DF');
                $this->SetLineWidth(0.25);
                $this->SetFont('helvetica', 'B', 8.8);
                $this->SetTextColor(19, 50, 43);
            } else {
                $this->SetFont('helvetica', 'B', 7.8);
                $this->SetTextColor(90, 100, 105);
            }

            $this->SetXY($cx, $tpY + 2.4);
            $this->Cell($tpOptW, 3.8, $tpOpt['label'], 0, 0, 'C');
            $cx += $tpOptW;
        }

        // 5. Tabla Oficial "RESULTADO DE SALUD MENTAL" (PREGUNTAS | RESULTADO | INTERPRETACIÓN | UP | SITIO DE ENVIO)
        $resY = $tpY + $tpH + 1.2;
        $resCols = [
            ['label' => 'PREGUNTAS',       'w' => 25.0, 'align' => 'C'],
            ['label' => 'RESULTADO',       'w' => 33.0, 'align' => 'C'],
            ['label' => 'INTERPRETACIÓN',  'w' => 79.9, 'align' => 'L'],
            ['label' => 'UP',              'w' => 24.0, 'align' => 'C'],
            ['label' => 'SITIO DE ENVIO',  'w' => 30.0, 'align' => 'C'],
        ];
        $resThH = 4.6;
        $resRowH = 4.5;

        // Encabezado de la tabla de resultados
        $this->SetFillColor(19, 50, 43);
        $this->SetDrawColor(19, 50, 43);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 6.8);
        $cx = $leftX;
        foreach ($resCols as $rc) {
            $this->Rect($cx, $resY, $rc['w'], $resThH, 'DF');
            $this->SetXY($cx, $resY + 0.7);
            $this->Cell($rc['w'], 3.2, $rc['label'], 0, 0, 'C');
            $cx += $rc['w'];
        }

        $resRows = [
            [
                'preguntas' => '1 Y 2',
                'resultado' => '>3 (' . $sumAnsiedad . ' pts)',
                'interpretacion' => 'PB TRASTORNO DE ANSIEDAD',
                'up' => 'UP307',
                'envio' => 'ENVIO MF',
                'active' => $isPosAnsiedad,
            ],
            [
                'preguntas' => '3 Y 4',
                'resultado' => '3 O MAS (' . $sumDepresion . ' pts)',
                'interpretacion' => 'PB TRASTORNO DEPRESIVO',
                'up' => 'UP306',
                'envio' => 'ENVIO MF',
                'active' => $isPosDepresion,
            ],
            [
                'preguntas' => '5',
                'resultado' => 'SI (' . ($q5Val === 1 ? 'SI' : 'NO') . ')',
                'interpretacion' => 'PB TRASTORNO DE PANICO, MIEDO',
                'up' => 'UP308',
                'envio' => 'ENVIO MF',
                'active' => $isPosPanico,
            ],
        ];

        $ry = $resY + $resThH;
        foreach ($resRows as $rIdx => $rr) {
            $isActive = (bool)$rr['active'];
            $cx = $leftX;
            $vals = [$rr['preguntas'], $rr['resultado'], $rr['interpretacion'], $rr['up'], $rr['envio']];

            foreach ($resCols as $cIdx => $rc) {
                if ($isActive) {
                    $this->SetFillColor(220, 245, 232);
                    $this->SetDrawColor(19, 50, 43);
                    $this->SetLineWidth(0.4);
                    $this->Rect($cx, $ry, $rc['w'], $resRowH, 'DF');
                    $this->SetLineWidth(0.25);
                    $this->SetFont('helvetica', 'B', 7.1);
                    $this->SetTextColor(19, 50, 43);
                } else {
                    $this->SetFillColor($rIdx % 2 === 1 ? 248 : 255, $rIdx % 2 === 1 ? 251 : 255, $rIdx % 2 === 1 ? 250 : 255);
                    $this->SetDrawColor(180, 190, 195);
                    $this->Rect($cx, $ry, $rc['w'], $resRowH, 'DF');
                    $this->SetFont('helvetica', $cIdx === 0 || $cIdx === 3 ? 'B' : '', 6.9);
                    $this->SetTextColor(70, 80, 85);
                }

                $padX = $rc['align'] === 'L' ? 2.0 : 0.0;
                $this->SetXY($cx + $padX, $ry + 0.6);
                $this->Cell($rc['w'] - ($padX * 2), 3.3, $vals[$cIdx], 0, 0, $rc['align']);
                $cx += $rc['w'];
            }
            $ry += $resRowH;
        }

        // Barra inferior de síntesis diagnóstica
        $footH = 4.6;
        $valColW = 137.9;
        $upEnvColW = $totalW - $valColW; // 54.0
        $this->SetFillColor(246, 250, 248);
        $this->SetDrawColor(19, 50, 43);
        $this->Rect($leftX, $ry, $valColW, $footH, 'DF');
        $this->Rect($leftX + $valColW, $ry, $upEnvColW, $footH, 'DF');
        $this->SetFont('helvetica', 'B', 6.3);
        $this->SetTextColor(19, 50, 43);
        $this->SetXY($leftX + 1.5, $ry + 0.7);
        $this->Cell($valColW - 3.0, 3.2, 'VALORACIÓN: ' . $valoracionDisplay, 0, 0, 'L');
        $this->SetXY($leftX + $valColW + 1.5, $ry + 0.7);
        $this->Cell($upEnvColW - 3.0, 3.2, 'UP: ' . $claveUpDisplay . '  |  ' . $sitioEnvioDisplay, 0, 0, 'C');

        // Restaurar estado gráfico
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $this->SetFillColor(255, 255, 255);
        $this->SetLineWidth(0.25);
    }
}
