<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config/bootstrap.php';
$pdo=db();$checks=[];
foreach(['pacientes','atenciones','vacunas_aplicadas','vacunas','dosis','catalogos','acompanantes'] as $table){$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$s->execute([$table]);$checks[$table]=(int)$s->fetchColumn()===1;}
$checks['patient_public_id']=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pacientes' AND column_name='public_id'")->fetchColumn();
$checks['patient_identity_unique']=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='pacientes' AND index_name='uq_pacientes_identidad'")->fetchColumn();
$checks['attention_snapshot']=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='atenciones' AND column_name='patient_snapshot'")->fetchColumn();
$failed=[];foreach($checks as $name=>$ok){echo ($ok?'OK   ':'FAIL ').$name."\n";if(!$ok)$failed[]=$name;}exit($failed?1:0);
