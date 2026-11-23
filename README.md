# Micro-Despachador de Correo Transaccional con PHPMailer en PHP

[![PHP](https://img.shields.io/badge/Language-PHP-777BB4.svg)](https://www.php.net/)
[![PHPMailer](https://img.shields.io/badge/Library-PHPMailer-blue.svg)](https://github.com/PHPMailer/PHPMailer)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Servicio ligero y modular en **PHP** para el despacho de correos electrónicos transaccionales autenticados mediante **SMTP** y la biblioteca **PHPMailer**. Diseñado para procesar solicitudes asíncronas vía POST (AJAX / Fetch API) o desde backends desacoplados utilizando peticiones cURL.

---

## 🚀 Características Principales

- **Conexión SMTP Autenticada**: Soporte para cifrado TLS (puerto 587) y SSL (puerto 465) con autenticación segura de usuario y contraseña.
- **Soporte HTML y Charset UTF-8**: Envío de plantillas de correo enriquecidas con tablas, estilos CSS en línea y caracteres especiales sin distorsión tipográfica.
- **Múltiples Clientes e Integraciones**: Compatible con peticiones AJAX desde el navegador y llamadas de servidor a servidor vía cURL.
- **Redirección Automatizada**: Redirección fluida del navegador hacia la URL de destino post-envío.

---

## 📂 Estructura del Repositorio

```text
mailer-php/
├── Mailer.php           # Controlador principal de recepcion POST y despacho SMTP
├── PHPMAILER/           # Biblioteca PHPMailer con autocargador de clases
└── README.md            # Documentacion tecnica de integracion y ejemplos
```

---

## 💻 Ejemplos de Integración

### 1. Petición Frontend con JavaScript (Fetch API)

```javascript
const formData = new FormData();
formData.append("Host", "smtp.tudominio.com");
formData.append("From", "notificaciones@tudominio.com");
formData.append("Password", "TU_CONTRASENA_SMTP");
formData.append("Business", "Tu Empresa SAS");
formData.append("Web", "https://tudominio.com/gracias.html");
formData.append("Subject", "Confirmación de Registro");
formData.append("Message", "<h2>¡Bienvenido!</h2><p>Tu cuenta ha sido activada con éxito.</p>");
formData.append("To", "cliente@correo.com");

fetch("Mailer.php", {
    method: "POST",
    body: formData
})
.then(response => {
    console.log("Correo despachado con éxito");
})
.catch(error => {
    console.error("Error al enviar el correo:", error);
});
```

### 2. Petición Frontend con jQuery AJAX

```javascript
$.post("Mailer.php", {
    Host: "smtp.tudominio.com",
    From: "notificaciones@tudominio.com",
    Password: "TU_CONTRASENA_SMTP",
    Business: "Tu Empresa SAS",
    Web: "https://tudominio.com/gracias.html",
    Subject: "Notificación de Estado",
    Message: "<p>Estimado usuario, su solicitud está en proceso.</p>",
    To: "usuario@ejemplo.com"
});
```

### 3. Petición Backend de Servidor a Servidor con PHP (cURL)

```php
<?php
$payload = [
    'Host'     => 'smtp.tudominio.com',
    'From'     => 'notificaciones@tudominio.com',
    'Password' => 'TU_CONTRASENA_SMTP',
    'Business' => 'Tu Empresa SAS',
    'Web'      => 'https://tudominio.com',
    'Subject'  => 'Alerta de Seguridad',
    'Message'  => '<p>Se ha iniciado sesión desde un nuevo dispositivo.</p>',
    'To'       => 'usuario@ejemplo.com'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://tudominio.com/Mailer.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);
```

---

## 🔒 Recomendaciones de Seguridad

> [!WARNING]
> En entornos de producción, evita exponer credenciales SMTP directamente desde el cliente. Se recomienda encapsular los valores sensibles (`Host`, `From`, `Password`) en variables de entorno del servidor (`$_ENV`) o archivos de configuración protegidos fuera de la raíz pública web.

---

## 📄 Licencia

Distribuido bajo la Licencia MIT. Consulta el archivo `LICENSE` para más información.
