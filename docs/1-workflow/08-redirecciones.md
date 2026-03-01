# Redirecciones y Monitoreo 404

## Gestión de Redirecciones

Accede a **SEO AI → Redirects** para administrar las redirecciones de URL.

### CRUD de Redirecciones

Puedes crear, editar y eliminar redirecciones con los siguientes campos:

| Campo | Descripción |
|-------|-------------|
| Source URL | URL de origen (la que se redirige) |
| Target URL | URL de destino (a donde se envía al usuario) |
| Tipo de redirección | Código HTTP de la redirección |
| Is Regex | Si la URL de origen es una expresión regular |
| Status | Estado de la redirección (activa/inactiva) |
| Hits | Contador de veces que se ha ejecutado la redirección |

### Tipos de redirección soportados

| Código | Nombre | Descripción |
|--------|--------|-------------|
| **301** | Permanent Redirect | Redirección permanente. Transfiere link juice al destino. |
| **302** | Temporary Redirect | Redirección temporal. |
| **307** | Temporary Redirect (strict) | Redirección temporal que preserva el método HTTP. |
| **410** | Gone | El recurso ya no existe. No requiere URL destino. |
| **451** | Unavailable for Legal Reasons | No disponible por razones legales. No requiere URL destino. |

### Tabla en la base de datos

Las redirecciones se almacenan en la tabla `{prefix}seo_ai_redirects` con las columnas:

`id`, `source_url`, `target_url`, `type`, `is_regex`, `hits`, `status`, `created_at`, `updated_at`

Índices: `source_url(191)`, `status`, `type`.

---

## Ejecución de Redirecciones

El plugin intercepta las peticiones usando el hook `template_redirect` de WordPress:

1. Se obtiene la URL actual de la petición.
2. Se consulta la tabla de redirecciones buscando coincidencias en `source_url`.
3. Si hay coincidencia y la redirección está activa, se ejecuta el redirect HTTP con el código configurado.
4. Se incrementa el contador de `hits`.

---

## Monitoreo de Errores 404

Accede a **SEO AI → 404 Log** para ver el registro de errores 404.

### Información registrada

Cada error 404 se almacena con:

| Campo | Descripción |
|-------|-------------|
| URL | La URL que generó el error 404 |
| Referrer | Página desde la que se accedió a la URL |
| User Agent | Navegador o bot que hizo la petición |
| IP Address | Dirección IP del visitante |
| Hits | Número de veces que se ha accedido a esa URL |
| Last Hit | Fecha/hora del último acceso |
| Created At | Fecha/hora del primer registro |

### Tabla en la base de datos

Los errores 404 se almacenan en la tabla `{prefix}seo_ai_404_log` con las columnas:

`id`, `url`, `referrer`, `user_agent`, `ip_address`, `hits`, `last_hit`, `created_at`

Índices: `url(191)`, `last_hit`.

---

## Flujo de trabajo recomendado

1. **Monitorear 404s** — Revisa periódicamente el registro de errores 404.
2. **Identificar URLs rotas** — Filtra por las URLs con más hits para priorizar.
3. **Crear redirecciones** — Para cada URL rota relevante, crea una redirección 301 hacia la página correcta.
4. **Verificar** — Después de crear la redirección, comprueba que la URL redirige correctamente.

Este flujo permite recuperar tráfico perdido y mejorar la experiencia del usuario y el SEO.

---

**Anterior:** [Schema Markup](07-schema-markup.md) · **Siguiente:** [Sitemap y Robots](09-sitemap-y-robots.md)
