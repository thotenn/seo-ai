# Configuración General

## Menú de Administración

El plugin registra las siguientes páginas en el menú **SEO AI**:

| Página | Slug | Descripción |
|--------|------|-------------|
| Dashboard | `seo-ai` | Panel principal con hero card, progreso de optimización, stats y actividad reciente |
| Settings | `seo-ai-settings` | Configuración del plugin (8 pestañas) |
| Redirects | `seo-ai-redirects` | Gestión de redirecciones URL |
| 404 Log | `seo-ai-404-log` | Monitor de errores 404 |
| Activity Log | `seo-ai-logs` | Historial de todas las operaciones del plugin |

---

## Página de Configuración

Accede a **SEO AI → Configuración** en el panel de administración. La página está organizada en pestañas:

---

## Pestaña General

Configuración global del plugin:

- **Toggles de módulos** — Activa o desactiva cada uno de los 9 módulos independientemente.
- **Separador de título** — Carácter usado entre el título del post y el nombre del sitio (ej: `|`, `-`, `–`, `·`).
- **Robots por defecto** — Configuración global de `noindex`/`nofollow` que aplica a todos los posts que no tengan configuración individual.

---

## Pestaña SEO

Plantillas de título y descripción por defecto:

### Placeholders disponibles

| Placeholder | Se reemplaza por |
|-------------|-----------------|
| `{title}` | Título del post |
| `{sitename}` | Nombre del sitio |
| `{sep}` | Separador configurado |

### Ejemplo de plantilla de título

```
{title} {sep} {sitename}
```

Resultado: `Mi Artículo | Mi Sitio Web`

### Plantilla de meta descripción

Puedes definir una plantilla que se usará cuando un post no tenga meta descripción personalizada.

---

## Pestaña Content Analysis

Permite personalizar el sistema de análisis de contenido:

### Pesos de checks SEO

Ajusta el peso (importancia) de cada uno de los 12 checks SEO. Los valores por defecto son:

| Check | Peso |
|-------|------|
| Keyword en Título | 15 |
| Keyword en Meta Description | 10 |
| Keyword en URL | 10 |
| Keyword en Introducción | 10 |
| Keyword en Subheadings | 8 |
| Densidad del Keyword | 10 |
| Longitud del Título SEO | 8 |
| Longitud de Meta Description | 8 |
| Longitud del Contenido | 7 |
| Enlaces Internos | 7 |
| Enlaces Externos | 4 |
| Texto Alt en Imágenes | 5 |

### Pesos de checks de legibilidad

| Check | Peso |
|-------|------|
| Flesch Reading Ease | 20 |
| Longitud de Oraciones | 15 |
| Longitud de Párrafos | 15 |
| Voz Pasiva | 15 |
| Distribución de Subheadings | 15 |
| Palabras de Transición | 10 |
| Oraciones Consecutivas | 10 |

### Activar/desactivar checks

Puedes desactivar checks individuales que no sean relevantes para tu sitio. Los checks desactivados no afectan la puntuación.

---

## Pestaña Providers

Configuración de proveedores de IA:

Para cada proveedor (OpenAI, Claude, Gemini, Ollama, OpenRouter):

- **API Key** — Clave de acceso a la API.
- **URL base** — Endpoint de la API (personalizable para proxies o instancias locales).
- **Modelo** — Selección del modelo a usar.
- **Temperatura** — Control de creatividad de las respuestas (0 = determinístico, 1 = creativo).
- **Probar Conexión** — Botón para verificar que la configuración funciona correctamente.

---

## Pestaña Social

Ver [Redes Sociales](06-redes-sociales.md) para detalles completos.

---

## Pestaña Schema

Ver [Schema Markup](07-schema-markup.md) para detalles completos.

---

## Pestaña Sitemap

Ver [Sitemap y Robots](09-sitemap-y-robots.md) para detalles completos.

---

## Pestaña Advanced

- **Limpiar al desinstalar** — Toggle para eliminar todas las tablas, opciones y meta del plugin al desinstalarlo. Si está desactivado, los datos se conservan para una reinstalación futura.
- **Modo debug** — Activa el registro detallado de operaciones para diagnóstico de problemas.

---

**Anterior:** [Optimización Masiva](04-optimizacion-masiva.md) · **Siguiente:** [Redes Sociales](06-redes-sociales.md) · **Ver también:** [Activity Log](10-activity-log.md)
