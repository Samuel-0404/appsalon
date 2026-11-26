# Despliegue de AppSalon (Docker)

Arquitectura: `app` (PHP 8.3 + Apache, DocumentRoot = `public/`) + `db` (MariaDB 11.4).
Solo `public/` queda expuesto; la BD no publica puertos fuera de Docker.

```
Navegador ──HTTP(S)──> [app :80]  ──PDO──> [db :3306]
                          │
                          ├──HTTPS──> API Nager.Date (festivos)
                          └──SMTP 587 STARTTLS──> Brevo ──> correo del cliente
```

## 1. Requisitos (una sola vez)

```bash
# en la raíz del proyecto: agrega PHPMailer a composer.json y composer.lock
composer require phpmailer/phpmailer
git add composer.json composer.lock
```

## 2. Levantar en local (Zorin / cualquier Linux)

```bash
cp .env.example .env          # completar claves de BD y de Brevo
docker compose up -d --build  # usa el puerto 8080: puede convivir con XAMPP (80/3306)
docker compose ps             # ambos servicios deben quedar "healthy"
```

Abrir http://localhost:8080 — admin: `admin@appsalon.com` / `admin123`, cliente: `cliente@appsalon.com` / `cliente123`.

La BD se importa sola la primera vez, en este orden:
`appsalon.sql` → `002_api_reportes.sql` → `003_festivos.sql` → `datos_prueba.sql`.

| Tarea | Comando |
|---|---|
| Ver logs (PHP, correo, festivos) | `docker compose logs -f app` |
| Entrar a la BD | `docker compose exec db mariadb -u appsalon -p appsalon_mvc_php` |
| Reconstruir tras cambiar código | `docker compose up -d --build app` |
| Reimportar la BD desde cero (**borra datos**) | `docker compose down -v && docker compose up -d` |
| Backup | `docker compose exec db mariadb-dump -u root -p appsalon_mvc_php > backup.sql` |

## 3. Correo (Brevo)

1. Cuenta Free en brevo.com → verificar un remitente (tu Gmail).
2. *SMTP & API → SMTP* → generar SMTP key.
3. En `.env`: `SMTP_USER` (login `xxxx@smtp-brevo.com`), `SMTP_PASS` (la SMTP key), `MAIL_FROM` (el remitente verificado).

Si un correo no llega: `docker compose logs app | grep "\[Email\]"` muestra el motivo exacto.
La copia HTML de cada correo queda además en el volumen `correos` (evidencia para la sustentación).

## 4. Servidor (Google Cloud, e2-micro) — fase 3

Se completa en la siguiente fase: VM Ubuntu, Docker, swap de 2 GB, firewall 80/443,
`git clone` + `.env` + `docker compose up -d`, y Caddy con HTTPS para el dominio.
