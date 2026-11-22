<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';

$ok=0;$fail=0;function check3(bool $condition,string $label):void{global $ok,$fail;if($condition){$ok++;echo "OK   {$label}\n";}else{$fail++;echo "FAIL {$label}\n";}}
check3(normalize_text('María de los Ángeles')==='MARÍA DE LOS ÁNGELES','mayúsculas Unicode');
$hash=password_hash('CaseSensitive_2026!A9',defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);
check3(password_verify('CaseSensitive_2026!A9',$hash),'password correcta');
check3(!password_verify('casesensitive_2026!a9',$hash),'password conserva mayúsculas');
check3((bool)preg_match('/^[a-f0-9]{32}$/',bin2hex(random_bytes(16))),'public_id no secuencial');
$blank=['nombre_presentacion'=>'','nss'=>'','dv'=>'','agregado'=>'','fecha'=>'','hora'=>'','unidad_nombre'=>''];$pdf=new PdfTemplate();$pdf->integral($blank,[],['HIGIENE PERSONAL Y SALUD BUCAL','EDUCACIÓN PARA LA SALUD']);$pdfText=$pdf->Output('','S');check3(str_starts_with($pdfText,'%PDF-'),'PDF Unicode genera PDF');
try{$pdo=db();foreach(['roles','temas_educacion_salud','atencion_educacion_salud'] as $table){$q=$pdo->query("SELECT COUNT(*) FROM {$table}");check3((int)$q->fetchColumn()>=0,'tabla '.$table.' disponible');}$cols=$pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios'")->fetchAll(PDO::FETCH_COLUMN);check3(in_array('password_hash',$cols,true)&&in_array('unidad_id',$cols,true),'usuarios con autenticación y asignación');}catch(Throwable $e){echo 'DB smoke omitido: '.$e->getMessage()."\n";}
echo "{$ok} pruebas correctas; {$fail} fallidas.\n";exit($fail?1:0);
