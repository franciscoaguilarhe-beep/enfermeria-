# SRAE - Sistema de Registro de Atencin de Enfermera

Aplicacin web para registrar pacientes y atenciones de enfermera sobre el XAMPP institucional existente.

## Decisiones de Arquitectura
- PHP nativo con PDO y MariaDB; no agrega dependencias complejas ni contenedores.
- Seguridad por Auth de Sesin y validacin de permisos estricta backend.
- Pacientes gestionados como catlogo maestro global, atenciones restringidas al mbito de la unidad de enfermera.
- Historiales de pacientes y atenciones mediante *snapshots* para garantizar la fidelidad de reimpresiones histricas.
- Reportes centralizados con filtros autorizados y exportacin segura.

## Instalacin Nueva en XAMPP

Para instalaciones completamente nuevas (por ejemplo, servidor de staging o primer pase a produccin):

1. Copiar `.env.example` a `.env` y configurar variables reales, asegurando `APP_ENV=production` y zona horaria (ej. `America/Mazatlan`).
2. En MariaDB, crear la base de datos `enfermeria`.
3. Importar el esquema consolidado: `mysql -u root enfermeria < database/schema_produccion.sql`
4. Importar catlogos iniciales: `mysql -u root enfermeria < database/seed.sql`
5. Configurar permisos mnimos de base de datos creando un usuario de MySQL que no sea root.
6. Crear la primera cuenta de administrador ejecutando desde la lnea de comandos:
   `c:\xampp\php\php.exe scripts\create_admin.php`
7. Iniciar sesin en el sistema con el administrador creado para terminar de configurar Unidades, Consultorios, Turnos y usuarios adicionales.

> **NOTA:** Jams dejes credenciales de prueba o por defecto en un servidor de produccin.

## Actualizacin de Instancia Existente

Para actualizar un SRAE que ya contiene informacin de generaciones pasadas:
1. Asegurarse de tener un respaldo completo.
2. Aplicar de manera secuencial los archivos `.sql` dentro del directorio `database/migrations/` que an no hayan sido aplicados en la instancia local.

## Documentacin

Por favor revisa la carpeta `docs/` para manuales de usuario, arquitectura, modelo de datos y procedimientos de respaldo.
