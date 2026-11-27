# Despliegue de AppSalon (Docker)

Arquitectura: `app` (PHP 8.3 + Apache, DocumentRoot = `public/`) + `db` (MariaDB 11.4) y, en producción,
`caddy` (proxy inverso con HTTPS automático). Solo `public/` queda expuesto y la BD no publica puertos.

```
Navegador ──HTTPS──> [caddy :443] ──> [app :80] ──PDO──> [db :3306]
                                         │
                                         ├──HTTPS──> API Nager.Date (festivos)
                                         └──SMTP 587 STARTTLS──> Brevo ──> correo del cliente
```

## 1. Levantar en local (Linux / Zorin)

```bash
cp .env.example .env          # completar claves (formato CLAVE=valor, sin comillas ni define())
docker compose up -d --build  # usa el puerto 8080: puede convivir con XAMPP (80/3306)
docker compose ps             # app y db deben quedar "healthy"
```

Abrir http://localhost:8080 (usuarios de prueba en el README).

La BD se importa sola **solo la primera vez** (volumen vacío), en este orden:
`appsalon.sql` → `002_api_reportes.sql` → `003_festivos.sql` → `004_roles_horario.sql` → `datos_prueba.sql`.

| Tarea | Comando |
|---|---|
| Ver logs (PHP, correo, festivos) | `docker compose logs -f app` |
| Entrar a la BD | `docker compose exec db mariadb -u appsalon -p appsalon_mvc_php` |
| Reconstruir tras cambiar código | `docker compose up -d --build app` |
| Aplicar una migración nueva a una BD existente | `docker compose exec -T db mariadb -u root -p"$DB_ROOT_PASS" appsalon_mvc_php < database/migraciones/004_roles_horario.sql` |
| Reimportar la BD desde cero (**borra datos**) | `docker compose down -v && docker compose up -d` |
| Backup | `docker compose exec db mariadb-dump -u root -p appsalon_mvc_php > backup.sql` |

## 2. Correo (Brevo)

1. Cuenta Free en brevo.com → verificar un remitente (Gmail del proyecto).
2. *SMTP & API → SMTP* → generar SMTP key.
3. En `.env`: `SMTP_USER` (login `xxxx@smtp-brevo.com`), `SMTP_PASS` (la SMTP key), `MAIL_FROM` (el remitente verificado).

Si un correo no llega: `docker compose logs app | grep "\[Email\]"` muestra el motivo exacto.
La copia HTML de cada correo queda además en el volumen `correos` (evidencia para la sustentación).

## 3. Servidor (Azure VM Ubuntu 24.04)

1. Crear la VM (región cercana: East US / South Central US / Mexico Central) con puertos de entrada **22, 80 y 443**.
2. Preparar la VM:
   ```bash
   curl -fsSL https://get.docker.com | sudo sh && sudo usermod -aG docker $USER
   sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
   echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
   sudo timedatectl set-timezone America/Bogota
   ```
3. Volver a entrar por SSH, clonar el repositorio, crear `.env` (con `APP_PORT=80`) y levantar:
   ```bash
   git clone -b dev/julian https://github.com/Samuel-0404/appsalon.git && cd appsalon
   cp .env.example .env && nano .env
   docker compose up -d --build
   ```

## 4. Dominio y HTTPS (Caddy + Let's Encrypt)

1. Subdominio gratuito en duckdns.org apuntando a la IP pública de la VM.
2. En `.env`: `DOMAIN=tu-subdominio.duckdns.org`.
3. Levantar con la capa HTTPS (Caddy toma los puertos 80/443 y obtiene/renueva el certificado solo):
   ```bash
   docker compose down
   docker compose -f docker-compose.yml -f docker-compose.https.yml up -d --build
   docker compose -f docker-compose.yml -f docker-compose.https.yml logs -f caddy   # buscar "certificate obtained"
   ```

## 5. Actualizar el servidor con cambios nuevos

```bash
cd ~/appsalon && git pull
docker compose up -d --build          # (agregar -f ... -f docker-compose.https.yml si usas HTTPS)
# si el cambio trae una migración nueva, aplicarla (tabla de la sección 1)
```
