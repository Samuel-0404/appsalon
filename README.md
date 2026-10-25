# AppSalon - Sistema de Gestión de Citas (Entrega Intermedia)

Aplicación web para un salón de belleza. PHP MVC propio + MySQL + Bootstrap.

> Responsable: B (A aporta la sección de instalación y BD)

## Tecnologías
- PHP 8+ (MVC propio, PDO con prepared statements)
- MySQL / MariaDB (XAMPP + phpMyAdmin)
- Bootstrap 5, JavaScript

## Instalación
1. Clonar el repo dentro de `htdocs` de XAMPP.
2. Importar `database/appsalon.sql` desde phpMyAdmin.
3. Copiar `config/config.example.php` a `includes/config.php` y poner tus credenciales.
4. Abrir `http://localhost/appsalon/public/`

## Usuarios de prueba
| Rol | Email | Contraseña |
|---|---|---|
| Admin | admin@appsalon.com | admin123 |
| Cliente | cliente@appsalon.com | cliente123 |

## Funcionalidades implementadas
- [ ] Registro, login y logout
- [ ] Roles (admin / cliente)
- [ ] Visualización pública de servicios
- [ ] CRUD de servicios (admin)

## Equipo
- Julian Andres Gomez Cabrera
- Samuel Orozco Varela
- Juliana Perez Rocha
