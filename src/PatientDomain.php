<?php
declare(strict_types=1);

final class PatientDomain
{
    public static function normalizeAggregate(string $value): string
    {
        return strtoupper(trim($value));
    }

    public static function validateIdentity(string $nss, string $dv, string $agregado): void
    {
        if (!preg_match('/^\d{10}$/D', $nss)) throw new InvalidArgumentException('El NSS debe contener exactamente 10 dígitos.');
        if (!preg_match('/^\d$/D', $dv)) throw new InvalidArgumentException('El DV debe contener exactamente 1 dígito.');
        if (!preg_match('/^[A-Z0-9]{8}$/D', $agregado)) throw new InvalidArgumentException('El agregado debe contener exactamente 8 caracteres alfanuméricos, sin espacios.');
    }

    public static function calculateDv(string $nss): ?string
    {
        if (!preg_match('/^\d{10}$/D', $nss)) return null;
        $sum = 0;
        for ($i = 0; $i < strlen($nss); $i++) {
            $product = ((int)$nss[$i]) * (($i % 2 === 0) ? 1 : 2);
            $sum += intdiv($product, 10) + ($product % 10);
        }
        return (string)((10 - ($sum % 10)) % 10);
    }

    public static function ageParts(?string $birthDate, string $attentionDate): array
    {
        if (!$birthDate || !valid_date($birthDate) || !valid_date($attentionDate)) return [null, null, null];
        $birth = new DateTimeImmutable($birthDate);
        $date = new DateTimeImmutable($attentionDate);
        if ($birth > $date) return [null, null, null];
        $diff = $birth->diff($date);
        return [$diff->y, $diff->m, $diff->d];
    }

    public static function address(array $patient): string
    {
        $street = trim(implode(' ', array_filter([$patient['calle'] ?? null, $patient['numero_exterior'] ?? null, $patient['numero_interior'] ?? null], static fn($v) => $v !== null && trim((string)$v) !== '')));
        $parts = array_filter([$street, $patient['colonia'] ?? null, $patient['municipio'] ?? null, $patient['entidad_residencia'] ?? null, $patient['codigo_postal'] ?? null], static fn($v) => $v !== null && trim((string)$v) !== '');
        return normalize_spaces(implode(', ', $parts));
    }

    public static function snapshot(array $patient): array
    {
        $fields = ['public_id','nss','dv','agregado','curp','escolaridad','ocupacion','estado_civil','apellido_paterno','apellido_materno','nombres','fecha_nacimiento','lugar_nacimiento','sexo','indigena','afromexicano','discapacidad','pais_residencia','entidad_residencia','municipio','colonia','calle','numero_exterior','numero_interior','codigo_postal','telefono','otro_contacto','domicilio'];
        $snapshot = [];
        foreach ($fields as $field) $snapshot[$field] = array_key_exists($field, $patient) ? $patient[$field] : null;
        $snapshot['domicilio_construido'] = self::address($patient);
        $snapshot['nombre_presentacion'] = normalize_spaces(implode(' ', array_filter([$patient['apellido_paterno'] ?? null, $patient['apellido_materno'] ?? null, $patient['nombres'] ?? null], static fn($v) => $v !== null && trim((string)$v) !== '')));
        return $snapshot;
    }
}
