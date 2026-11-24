-- =============================================================================
--  Script de inicialización de MySQL
--  - Se ejecuta automáticamente SOLO en el primer arranque (volumen vacío).
--  - Corre dentro de la base MYSQL_DATABASE (la imagen oficial ya creó la base
--    y el usuario MYSQL_USER con permisos sobre ella).
--  - Para volver a ejecutarlo:  docker compose down -v  (¡borra los datos!)
-- =============================================================================

-- Asegura UTF-8 completo (emojis, acentos) en la base de la aplicación
ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Las tablas las crean las migraciones de Laravel (RUN_MIGRATIONS=true).
-- Si tu proyecto NO usa migraciones, pega aquí el dump de tu base
-- (mysqldump --no-create-db ...) o agrega archivos 02-*.sql, 03-*.sql:
-- se ejecutan en orden alfabético.
