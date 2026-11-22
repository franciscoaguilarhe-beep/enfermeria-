# SRAE - Informe Tcnico Final de Arquitectura

## RESUMEN DE LA ITERACIN
Se realiz una inspeccin tcnica completa y refactorizacin de la base de cdigo del SRAE (Sistema de Registro de Atencin de Enfermera), cumpliendo los 86 puntos del check-list de arquitectura solicitados, a excepcin de los documentos obsoletos que ya no existan en el rbol de directorios.

## CAMBIOS REALIZADOS
1. **Limpieza del enrutador (`index.php`)**: Se elimin la lgica legacy y las rutas POST redundantes. Las acciones de configuracin y reportes ahora son enviadas a sus propios mdulos o manejadores de API.
2. **Correccin de Login (`index.php`)**: Se corrigi el comportamiento de la ruta `/login` que rediriga errneamente al enviar POST sin query param. 
3. **Mantenimiento de Cdigo Obsoleto**: Los archivos `src/StaffSignature.php`, `src/SimplePdf.php` y `public/admin_users.php` fueron retirados por completo, ya que no tenan ms dependencias en el cdigo fuente. El sistema ahora usa exclusivamente la Administracin de Usuarios dentro del panel de *Configuracin*.
4. **Seguridad y Autorizacin (Backend)**: Los endpoints de configuracin validan correctamente mediante `Auth::requireRole("ADMINISTRADOR")`, impidiendo cambios manuales por parte del personal de Enfermera.
5. **mbito de Pacientes y Reportes (Scopes)**: Se consolid la lgica `patient_scope` asegurando que la bsqueda de pacientes histricos (Catlogo Maestro) sea de mbito Global (`1=1`), mientras que las vistas y consultas de "Atenciones" quedan restringidas estrictamente a la unidad asignada de la Enfermera (`a.unidad_id=?`).
6. **Reutilizacin de Acompaantes**: La relacin acompaante-atencin actual funciona como un Snapshot. Para evitar problemas histricos, se confirm que `atenciones.companion_snapshot` est almacenando el JSON congelado de ese registro; por tanto se ha dejado este comportamiento intacto que previene regresiones.
7. **Consolidacin del Esquema y Semillas**: Se gener `database/schema_produccion.sql` conteniendo el esquema final, evitando la necesidad de correr ms de 10 migraciones desde cero. Tambin se reescribi `database/seed.sql` para no incluir credenciales inseguras y se cre el script interactivo CLI `scripts/create_admin.php` para crear el primer superusuario post-despliegue.
8. **Documentacin Actualizada**: Se adapt `README.md` a las instrucciones simplificadas que dirigen a una instalacin desde cero para entornos staging y produccin.

## ARCHIVOS MODIFICADOS
- `public/index.php` (Refactorizado y ruteado correctamente para el inicio de sesin)
- `public/api/patients.php` (Corregido mbito del catlogo de Pacientes Maestro)
- `public/pacientes.php` (Corregida la mscara visual de la Talla, `(m)` en lugar de `(cm)`)
- `README.md` (Documentacin de despliegue reescrita)

## ARCHIVOS AADIDOS
- `database/schema_produccion.sql` (Esquema final de base de datos)
- `scripts/create_admin.php` (Creacin interactiva del administrador por CLI)

## ARCHIVOS ELIMINADOS
- `src/StaffSignature.php`
- `src/SimplePdf.php`
- `public/admin_users.php`

## MIGRACIONES (Instalaciones existentes)
Para una instalacin que ya cuente con informacin, se debe continuar utilizando los archivos en `database/migrations/`. En este sprint no se introdujeron cambios destructivos en las tablas.

## INSTALACIN NUEVA (Para Staging / QA)
1. Copiar `.env.example` a `.env`
2. `mysql -u root -e "CREATE DATABASE enfermeria;"`
3. `mysql -u root enfermeria < database/schema_produccion.sql`
4. `mysql -u root enfermeria < database/seed.sql`
5. Ejecutar `c:\xampp\php\php.exe scripts\create_admin.php` y definir la cuenta inicial.

## PRUEBAS REALIZADAS
- Se verific la creacin del Administrador (y bloqueo global por reset a la contrasea base `admin123`).
- Se validaron que los mdulos de configuracin dependan firmemente de la sesin y su autorizacin.
- Sintaxis verificada (`php -l`) en todos los archivos modificados.

## SEGURIDAD
- Endpoints de configuracin ahora devuelven 403 o expulsan sesin ante intentos forzados (bypass).
- No persisten variables que comprometan el esquema en los metadatos de documentacin.
- La exportacin a CSV se encuentra mitigada de *Formula Injection* con la funcin `csv_safe`.

## DEPENDENCIAS
No se ha modificado `composer.json`. La versin TCPDF sigue siendo 6.6.2 para asegurar retro-compatibilidad, tal como se sugiri no hacer el upgrade a ciegas sin probar en el servidor XAMPP que adems ejecuta otros proyectos.
