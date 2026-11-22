<?php
declare(strict_types=1);

$passed=0;$failed=0;
function check_case(string $label,bool $condition):void{global $passed,$failed;if($condition){$passed++;echo "OK   {$label}\n";}else{$failed++;echo "FAIL {$label}\n";}}
function valid_nss(string $value):bool{return (bool)preg_match('/^\d{10}$/D',$value);}
function valid_dv(string $value):bool{return (bool)preg_match('/^\d$/D',$value);}
function valid_agregado(string $value):bool{return (bool)preg_match('/^[A-Z0-9]{8}$/D',strtoupper(trim($value)));}
function calculated_dv(string $nss):?string{if(!valid_nss($nss))return null;$sum=0;for($i=0;$i<10;$i++){$p=(int)$nss[$i]*(($i%2===0)?1:2);$sum+=intdiv($p,10)+($p%10);}return (string)((10-($sum%10))%10);}
function display_name(?string $p,?string $m,string $n):string{return trim((string)preg_replace('/\s+/u',' ',implode(' ',array_filter([$p,$m,$n],static fn($v)=>$v!==null&&trim($v)!==''))));}

check_case('NSS de 9 dígitos rechaza',!valid_nss('123456789'));
check_case('NSS de 10 dígitos acepta',valid_nss('1234567890'));
check_case('NSS de 11 dígitos rechaza',!valid_nss('12345678901'));
check_case('NSS con letras rechaza',!valid_nss('12345A7890'));
check_case('DV de 1 dígito acepta',valid_dv('6'));
check_case('DV de 2 dígitos rechaza',!valid_dv('66'));
check_case('DV con letra rechaza',!valid_dv('A'));
check_case('DV calculado como ayuda',calculated_dv('2206861799')==='6');
check_case('Agregado alfanumérico 3F2015OR acepta',valid_agregado('3F2015OR'));
check_case('Agregado alfanumérico 1M1959OR acepta',valid_agregado('1M1959OR'));
check_case('Agregado de 7 caracteres rechaza',!valid_agregado('3F2015O'));
check_case('Agregado de 9 caracteres rechaza',!valid_agregado('3F2015ORX'));
check_case('Agregado con espacios rechaza',!valid_agregado('3F 2015O'));
check_case('Nombre sin apellido paterno',display_name(null,'LÓPEZ','MARÍA')==='LÓPEZ MARÍA');
check_case('Nombre sin apellido materno',display_name('GARCÍA',null,'JUAN')==='GARCÍA JUAN');
check_case('Nombre sin ambos apellidos',display_name(null,null,'MARÍA FERNANDA')==='MARÍA FERNANDA');

echo "\n{$passed} pruebas correctas; {$failed} fallidas.\n";
exit($failed>0?1:0);
