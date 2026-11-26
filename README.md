# API de correo transaccional en PHP con SMTP y PHPMailer

[![CI](https://github.com/KevinAndrey96/php-smtp-mailer-api/actions/workflows/ci.yml/badge.svg)](https://github.com/KevinAndrey96/php-smtp-mailer-api/actions/workflows/ci.yml)
![PHP 8.4 | 8.5](https://img.shields.io/badge/PHP-8.4%20%7C%208.5-777BB4?logo=php&logoColor=white)
![PHPMailer 7](https://img.shields.io/badge/PHPMailer-7-blue)
![PHPStan max](https://img.shields.io/badge/PHPStan-max%20%2B%20strict-brightgreen)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)
[![Licencia MIT](https://img.shields.io/badge/licencia-MIT-green)](LICENSE)

Microservicio en **PHP 8.4+** que expone una **API JSON** para enviar **correos transaccionales** por **SMTP** con **PHPMailer**: confirmaciones de registro, formularios de contacto o notificaciones desde cualquier backend o sitio web. Las credenciales SMTP viven solo en variables de entorno, los clientes se autentican con un **token Bearer** y una **lista de dominios permitidos** impide que el servicio se convierta en un relé de spam.

## Contenido

- [Inicio rápido](#inicio-rápido)
- [API](#api)
- [Configuración](#configuración)
- [Seguridad](#seguridad)
- [Arquitectura](#arquitectura)
- [Pruebas y calidad](#pruebas-y-calidad)
- [Historia del proyecto](#historia-del-proyecto)

## Inicio rápido

```bash
cp .env.example .env          # y reemplaza API_TOKEN por: openssl rand -hex 32
docker compose up -d --build --wait
./scripts/smoke-test.sh       # envía un correo de prueba de punta a punta
```

El entorno local entrega los correos a [Mailpit](https://mailpit.axllent.org/), un servidor SMTP de pruebas con bandeja web en <http://localhost:8025>. Para producción basta con apuntar `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME` y `SMTP_PASSWORD` al servidor real.

## API

### `POST /messages`

```bash
curl -X POST http://localhost:8080/messages \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
        "to": ["cliente@example.com"],
        "subject": "Confirmación de registro",
        "text": "Hola, tu registro está completo.",
        "html": "<p>Hola, tu registro <strong>está completo</strong>.</p>",
        "reply_to": "soporte@example.com"
      }'
```

```json
{"status":"sent","message_id":"<k2Jd8x0...@localhost>"}
```

| Campo | Tipo | Reglas |
| --- | --- | --- |
| `to` | texto o lista | Entre 1 y `MAX_RECIPIENTS` direcciones, todas de dominios permitidos |
| `subject` | texto | Obligatorio, hasta 200 caracteres, sin saltos de línea |
| `text` | texto | Obligatorio, hasta 100 KB; es la alternativa en texto plano del HTML |
| `html` | texto | Opcional, hasta 100 KB |
| `reply_to` | texto | Opcional, dirección para las respuestas |

El remitente no se acepta en la petición: siempre es `MAIL_FROM_ADDRESS`, así que un cliente no puede suplantar otra dirección.

| Código | Cuándo |
| --- | --- |
| `202` | El servidor SMTP aceptó el mensaje |
| `400` | El cuerpo no es un objeto JSON |
| `401` | Falta el token o no coincide |
| `403` | Algún destinatario está fuera de `ALLOWED_RECIPIENT_DOMAINS` |
| `413` | El cuerpo supera 256 KB |
| `415` | El `Content-Type` no es `application/json` |
| `422` | Campos inválidos, todos reportados a la vez en `error.fields` |
| `502` | El servidor SMTP rechazó el mensaje; el detalle queda en el registro, no en la respuesta |

```json
{"error":{"code":"validation_failed","message":"the message is invalid","fields":{"to[0]":"is not a valid email address","subject":"must be a string"}}}
```

### `GET /health`

Responde `{"status":"ok"}` cuando el servicio está configurado. Lo usa el `HEALTHCHECK` de Docker.

## Configuración

| Variable | Obligatoria | Valor por defecto | Descripción |
| --- | --- | --- | --- |
| `API_TOKEN` | Sí | | Secreto compartido de al menos 32 caracteres |
| `SMTP_HOST` | Sí | | Servidor SMTP |
| `SMTP_PORT` | No | `587` | Puerto |
| `SMTP_ENCRYPTION` | No | `tls` | `tls` (STARTTLS), `ssl` (SMTPS, puerto 465) o `none` |
| `SMTP_USERNAME`, `SMTP_PASSWORD` | No | | Sin usuario no se intenta autenticar |
| `MAIL_FROM_ADDRESS` | Sí | | Remitente de todos los mensajes |
| `MAIL_FROM_NAME` | No | | Nombre visible del remitente |
| `ALLOWED_RECIPIENT_DOMAINS` | Sí | | Dominios separados por comas; `*` permite cualquiera y debe elegirse a propósito |
| `MAX_RECIPIENTS` | No | `10` | Entre 1 y 100 |
| `ALLOWED_ORIGINS` | No | | Orígenes de navegador con CORS habilitado, separados por comas |

Si falta alguna variable o tiene un valor inválido, el servicio responde `500 misconfigured` y registra la lista completa de problemas.

## Seguridad

| Riesgo | Medida |
| --- | --- |
| Credenciales SMTP expuestas | Solo en variables de entorno; nunca viajan en las peticiones ni en el repositorio |
| Uso no autorizado | Token Bearer comparado con `hash_equals` en tiempo constante |
| Relé de spam | Lista de dominios permitidos y límite de destinatarios por mensaje |
| Inyección de cabeceras SMTP | Se rechazan caracteres de control en direcciones y saltos de línea en el asunto |
| Intercepción de la contraseña SMTP | Verificación de certificados TLS siempre activa |
| Fuga de detalles internos | Los errores SMTP se registran en el servidor y el cliente recibe un mensaje genérico |
| Abuso desde navegadores | CORS solo para `ALLOWED_ORIGINS`; cabeceras `nosniff`, `no-store` y CSP `default-src 'none'` |

El servicio no limita la tasa de peticiones: en producción conviene ponerlo detrás de un proxy inverso con límites por IP.

## Arquitectura

```text
src/
├── Domain/           EmailAddress, Message y ValidationException: reglas sin dependencias
├── Application/      SendMessage (caso de uso), RecipientPolicy y la interfaz Mailer
├── Infrastructure/   PhpMailerTransport: implementación SMTP de Mailer
├── Http/             Kernel (rutas, autenticación, CORS), Request y Response
└── Config/           Settings: lectura y validación de variables de entorno
public/index.php      Punto de entrada: compone las piezas y atiende la petición
```

El dominio y la aplicación no conocen PHPMailer ni HTTP; las pruebas del `Kernel` usan un `Mailer` falso y las de integración entregan correos reales a Mailpit.

## Pruebas y calidad

```bash
composer install
vendor/bin/phpstan analyse        # nivel max con reglas estrictas
MAILPIT_HOST=127.0.0.1 vendor/bin/phpunit --fail-on-skipped
```

- 49 pruebas PHPUnit 13: dominio, configuración, caso de uso, todas las respuestas HTTP y entrega real por SMTP.
- Las pruebas de integración verifican en la API de Mailpit el remitente, los destinatarios, `Reply-To`, el HTML y su alternativa en texto plano.
- GitHub Actions ejecuta PHPStan y PHPUnit en PHP 8.4 y 8.5 con Mailpit como servicio, y levanta la imagen Docker completa para la prueba de humo.

## Historia del proyecto

En 2019 era un único `Mailer.php` que recibía por `POST` el servidor, el usuario y la **contraseña SMTP**, desactivaba la verificación de certificados TLS y redirigía a una URL tomada de la misma petición, con PHPMailer 5 copiado en el repositorio. El código original se conserva en la etiqueta [`v0.1-php-mailer-2019`](https://github.com/KevinAndrey96/php-smtp-mailer-api/tree/v0.1-php-mailer-2019); la copia de PHPMailer se purgó del historial y las credenciales de un ejemplo se redactaron. En 2026 se reescribió como una API con configuración por entorno, autenticación, validación, pruebas, Docker y CI.

## Autor

**Kevin Andrey Herrera Silva** · [GitHub](https://github.com/KevinAndrey96)

## Licencia

[MIT](LICENSE) © 2019-2026 Kevin Andrey Herrera Silva
