<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
if (PHP_SAPI !== 'cli') exit(1);
$dir=ENFERMERIA_ROOT.'/templates/pdf'; if(!is_dir($dir))mkdir($dir,0770,true);
$blank=['nombre_presentacion'=>'','nss'=>'','dv'=>'','agregado'=>'','fecha'=>'','hora'=>'','unidad_nombre'=>'','fecha_nacimiento'=>'','lugar_nacimiento'=>'','sexo'=>'','unidad_adscripcion_id'=>null,'calle'=>'','numero_exterior'=>'','numero_interior'=>'','colonia'=>'','codigo_postal'=>'','telefono'=>'','municipio'=>'','entidad_residencia'=>'','pais_residencia'=>'','indigena'=>null,'afromexicano'=>null,'discapacidad'=>null,'enfermera_nombre'=>'','matricula'=>''];
$pdf=new PdfTemplate();$pdf->censo($blank,[],null);$pdf->Output($dir.'/censo_ammates.pdf','F');
$pdf=new PdfTemplate();$pdf->integral($blank,[],[]);$pdf->Output($dir.'/hoja_atencion_integral.pdf','F');
fwrite(STDOUT,"Plantillas limpias generadas en templates/pdf.\n");
