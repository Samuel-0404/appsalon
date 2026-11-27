# API REST de AppSalon

API JSON sin estado (stateless) para autenticarse, administrar la cuenta, consultar servicios y gestionar citas. Comparte la base de datos y las reglas de negocio con la aplicación web (`models/ValidadorCita.php`).

## Documentación interactiva (Swagger)

| Entorno | Dirección |
|---|---|
| Docker | `http://localhost:8080/swagger/` |
| XAMPP | `http://localhost/appsalon/public/swagger/` |

La especificación OpenAPI está en `public/swagger/openapi.json`. Para probar rutas protegidas: ejecutar el login, pulsar **Authorize** y pegar el token. Si cambia un endpoint, hay que actualizar ese archivo.

## 1. URL base

| Entorno | URL base |
|---|---|
| Docker | `http://localhost:8080/api` |
| XAMPP con `mod_rewrite` (por defecto) | `http://localhost/appsalon/public/api` |
| Sin `mod_rewrite` | `http://localhost/appsalon/public/api.php` |
| Servidor embebido (`php -S localhost:8000 -t public`) | `http://localhost:8000/api.php` |

Requisitos:
- Tener aplicada la migración `database/migraciones/002_api_reportes.sql`, o reimportar `database/appsalon.sql`.
- `public/.htaccess` activo (en XAMPP, `AllowOverride All` en `htdocs`).

## 2. Formato de respuesta

Todas las respuestas, incluidos los errores, son `application/json; charset=utf-8` y van en un sobre uniforme:

```json
{ "ok": true, "data": { } }
```

```json
{ "ok": false, "error": { "codigo": 422, "mensaje": "Datos inválidos", "detalles": { "hora": "..." } } }
```

`detalles` aparece solo cuando hay errores por campo. Los montos van en COP como número (`90000.0`), las fechas como `AAAA-MM-DD` y las horas como `HH:MM`.

## 3. Autenticación

Todas las rutas requieren token, excepto `POST /auth/register` y `POST /auth/login`.

1. Si no tienes cuenta, `POST /auth/register` y abre el enlace de confirmación que llega al correo.
2. `POST /auth/login` con email y contraseña devuelve un `token`.
3. En cada petición protegida se envía la cabecera `Authorization: Bearer <token>`.
4. El token vence a las **8 horas**. Antes se puede revocar con `POST /auth/logout` (el actual), `POST /auth/logout-all` (todos) o `DELETE /tokens/{id}` (uno).

Detalles de seguridad:
- El token tiene 256 bits aleatorios (`random_bytes(32)`). En la tabla `api_tokens` solo se guarda su hash SHA-256.
- La API no usa cookies ni sesión, así que no es vulnerable a CSRF y no necesita token CSRF.
- Las cuentas sin confirmar (`confirmado = 0`) o desactivadas (`activo = 0`) reciben `403`.

## 4. Códigos HTTP

| Código | Cuándo |
|---|---|
| 200 OK | Consulta o actualización correcta |
| 201 Created | Cuenta o cita creada. La cita incluye la cabecera `Location` |
| 204 No Content | Logout o revocación de tokens correcta |
| 400 Bad Request | JSON mal formado o que no es un objeto |
| 401 Unauthorized | Falta el token, o es inválido o vencido. Incluye `WWW-Authenticate: Bearer` |
| 403 Forbidden | Sin permiso: datos de otro usuario, cliente que cambia un estado distinto de cancelar, o cuenta desactivada |
| 404 Not Found | La ruta o el recurso no existen. A un cliente, una cita ajena también le responde 404 |
| 405 Method Not Allowed | Método no soportado en esa ruta. Incluye la cabecera `Allow` |
| 409 Conflict | Horario ocupado, o la cita está en un estado que no permite el cambio |
| 415 Unsupported Media Type | El cuerpo no se envió como `application/json` |
| 422 Unprocessable Entity | Errores de validación por campo |
| 500 / 503 | Error interno / sistema ocupado (con `Retry-After`) |

## 5. Endpoints

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| POST | `/auth/register` | No | Registrar un cliente |
| POST | `/auth/login` | No | Obtener token |
| POST | `/auth/logout` | Sí | Revocar el token actual |
| POST | `/auth/logout-all` | Sí | Revocar todos mis tokens |
| GET | `/auth/profile` | Sí | Ver mi perfil |
| PUT | `/auth/profile` | Sí | Actualizar mi perfil |
| GET | `/tokens` | Sí | Listar mis tokens vigentes |
| DELETE | `/tokens/{id}` | Sí | Revocar uno de mis tokens |
| GET | `/servicios` | Sí | Servicios activos |
| GET | `/servicios/{id}` | Sí | Un servicio |
| GET | `/citas/usuario/{id}` | Sí | Citas de un usuario (el propio usuario o un admin). Filtro opcional `?estado=` |
| GET | `/citas/{id}` | Sí | Una cita (su dueño o un admin) |
| POST | `/citas` | Sí | Crear una cita |
| PUT | `/citas/{id}` | Sí | Reprogramar y/o cambiar el estado |

### POST /auth/register

```json
{ "nombre": "Laura", "apellido": "Gómez", "email": "laura@ejemplo.com", "telefono": "3001234567",
  "password": "secreta123", "password_confirmation": "secreta123" }
```

Usa las mismas reglas que el registro web. Siempre crea un **cliente** sin confirmar (un `rol` enviado se ignora) y no devuelve token. Responde `201` con `usuario` y `confirmacionEnviada`; si el correo no salió, el enlace se puede reenviar desde el inicio de sesión de la web. Errores por campo: `422`.

### GET y PUT /auth/profile

`GET` devuelve `id`, `nombre`, `apellido`, `email`, `telefono`, `rol`, `admin` y `confirmado`.

`PUT` cambia solo los campos enviados: `nombre`, `apellido`, `telefono`, `email`, `password` (con `password_confirmation`).

| Cambio | Exige `password_actual` | Efecto en los tokens |
|---|---|---|
| Nombre, apellido o teléfono | No | Ninguno |
| Contraseña | Sí | Se revocan los demás; el actual sigue sirviendo |
| Email | Sí | Se revocan todos. La cuenta queda sin confirmar hasta abrir el enlace enviado al correo nuevo |

Los cambios no cierran las sesiones ya abiertas en la web.

### GET /tokens y DELETE /tokens/{id}

```json
{ "ok": true, "data": [ { "id": 7, "creado": "2026-11-27 15:00:00", "expira": "2026-11-27 23:00:00", "actual": true } ] }
```

Cada usuario, incluido el administrador, solo ve y revoca sus propios tokens (uno ajeno responde `404`). El valor del token nunca se devuelve.

### POST /auth/login

```bash
curl -X POST http://localhost/appsalon/public/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"cliente@appsalon.com","password":"cliente123"}'
```

```json
{ "ok": true, "data": {
  "token": "a662…(64 hex)", "tipo": "Bearer", "expira": "2026-11-22 05:54:20",
  "usuario": { "id": 2, "nombre": "Cliente", "apellido": "Prueba", "email": "cliente@appsalon.com", "admin": false } } }
```

Errores: `422` (email o contraseña vacíos), `401` (credenciales incorrectas, con un mensaje genérico que no revela si el email existe) y `403` (cuenta sin confirmar o desactivada).

### GET /servicios

Requiere token.

```json
{ "ok": true, "data": [
  { "id": 1, "nombre": "Corte de cabello", "descripcion": "Corte para dama o caballero", "precio": 25000.0, "duracion": 45 } ] }
```

### GET /citas/usuario/{id}

```bash
curl http://localhost/appsalon/public/api/citas/usuario/2?estado=pendiente \
  -H "Authorization: Bearer $TOKEN"
```

```json
{ "ok": true, "data": [ {
  "id": 1, "usuarioId": 2, "fecha": "2026-11-23", "hora": "10:00", "estado": "pendiente",
  "total": 90000.0, "duracion": 120,
  "servicios": [ { "id": 2, "nombre": "Tinte completo", "precio": 90000.0, "duracion": 120 } ],
  "creada": "2026-11-21 21:54:20" } ] }
```

### POST /citas

```bash
curl -X POST http://localhost/appsalon/public/api/citas \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"fecha":"2026-11-23","hora":"12:00","servicios":[1,3]}'
```

| Campo | Tipo | Regla |
|---|---|---|
| `fecha` | string `AAAA-MM-DD` | Ni pasada ni domingo |
| `hora` | string `HH:00` | Franjas de 09:00 a 18:00. Si la fecha es hoy, la hora no puede haber pasado |
| `servicios` | int[] | Al menos uno, existentes y activos. Los duplicados se ignoran |
| `usuarioId` | int (opcional) | Solo para admin: reservar a nombre de un cliente |

- El `total` y la `duracion` los calcula siempre el servidor. Si el cliente envía un `total`, se ignora.
- La cita debe terminar a las 19:00 como máximo: `hora + Σ duración ≤ 19:00`.
- **Solapamiento**: dos citas chocan si se cruzan los intervalos `[hora, hora + Σ duración)`. Una cita de 120 min a las 10:00 bloquea también la franja de las 11:00. Los bloqueos administrativos cuentan como 60 min.
- La validación y la inserción se serializan por fecha con `GET_LOCK`, así dos reservas simultáneas no pueden quedarse con el mismo horario.

Respuestas: `201` con la cita y la cabecera `Location: /appsalon/public/api/citas/{id}`, `409` si el horario está ocupado y `422` si hay datos inválidos. Se envía el mismo correo de confirmación que en la web.

### PUT /citas/{id}

PUT reemplaza la reserva, así que para reprogramar hay que enviar los tres campos:

```json
{ "fecha": "2026-11-23", "hora": "15:00", "servicios": [2] }
```

Cambiar el estado:

```json
{ "estado": "cancelada" }
```

Ambas operaciones se pueden enviar en la misma petición.

| Quién | Puede reprogramar | Puede cambiar el estado |
|---|---|---|
| Cliente (dueño) | Solo citas `pendiente` | Solo a `cancelada`, y solo si está `pendiente` |
| Admin | Solo citas `pendiente` | A `pendiente`, `confirmada`, `completada` o `cancelada` |

Las citas `cancelada` o `completada` ya no admiten cambios de estado (`409`).

## 6. Prueba rápida (bash)

```bash
B=http://localhost/appsalon/public/api
TOKEN=$(curl -s -X POST $B/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"cliente@appsalon.com","password":"cliente123"}' | php -r 'echo json_decode(stream_get_contents(STDIN),true)["data"]["token"];')
curl -s $B/servicios -H "Authorization: Bearer $TOKEN"
curl -s -X POST $B/citas -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"fecha":"2026-11-23","hora":"10:00","servicios":[2]}'
curl -s $B/citas/usuario/2 -H "Authorization: Bearer $TOKEN"
```

También está la colección de Postman: `docs/AppSalon_API.postman_collection.json`. El login guarda el token en una variable de la colección automáticamente.
