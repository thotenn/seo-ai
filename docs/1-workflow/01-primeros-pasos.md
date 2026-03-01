# Primeros Pasos

## Activación del Plugin

Al activar SEO AI, el plugin configura automáticamente:

### Tablas en la base de datos

- **`{prefix}seo_ai_redirects`** — Almacena redirecciones URL con columnas: `id`, `source_url`, `target_url`, `type`, `is_regex`, `hits`, `status`, `created_at`, `updated_at`.
- **`{prefix}seo_ai_404_log`** — Registro de errores 404 con columnas: `id`, `url`, `referrer`, `user_agent`, `ip_address`, `hits`, `last_hit`, `created_at`.

### Capacidades (Capabilities)

Se asignan al rol Administrador:

- `seo_ai_manage_settings` — Gestionar configuración del plugin.
- `seo_ai_manage_redirects` — Gestionar redirecciones.
- `seo_ai_view_reports` — Ver reportes y estadísticas.

### Opciones por defecto

- **`seo_ai_settings`** — Configuración principal con valores por defecto para todos los módulos, plantillas de título, tipos de post, análisis de contenido, schema, sitemap, redes sociales, redirecciones, Image SEO, breadcrumbs, Auto-SEO y ajustes avanzados.
- **`seo_ai_providers`** — Configuración de proveedores de IA (Ollama activo por defecto).
- **`seo_ai_version`** — Versión del plugin instalada.

Además, se reescriben las reglas de rewrite y se dispara el hook `seo_ai/activate`.

---

## Gestor de Módulos

SEO AI está organizado en 9 módulos independientes. Todos están habilitados por defecto excepto **Breadcrumbs**:

| # | Módulo | Slug | Activo por defecto |
|---|--------|------|--------------------|
| 1 | Meta Tags | `meta_tags` | Sí |
| 2 | Schema Markup | `schema` | Sí |
| 3 | XML Sitemap | `sitemap` | Sí |
| 4 | Redirects & 404 Monitor | `redirects` | Sí |
| 5 | Open Graph | `open_graph` | Sí |
| 6 | Twitter Cards | `twitter_cards` | Sí |
| 7 | Image SEO | `image_seo` | Sí |
| 8 | Breadcrumbs | `breadcrumbs` | No |
| 9 | Robots.txt | `robots_txt` | Sí |

Puedes activar o desactivar cada módulo desde **SEO AI → Configuración → General**.

---

## Configuración del Proveedor de IA

El plugin soporta 5 proveedores de IA:

| Proveedor | Requiere API Key | Modelo por defecto |
|-----------|-------------------|--------------------|
| **OpenAI** | Sí | Configurable |
| **Claude** (Anthropic) | Sí | Configurable |
| **Gemini** (Google) | Sí | Configurable |
| **Ollama** | No (local) | Configurable |
| **OpenRouter** | Sí | Configurable |

Para configurar un proveedor:

1. Ve a **SEO AI → Configuración → Proveedores**.
2. Ingresa la API Key del proveedor elegido.
3. Selecciona el modelo deseado.
4. Usa el botón **Probar Conexión** para verificar que funciona.
5. Marca el proveedor como activo.

> Por defecto, Ollama está configurado como proveedor activo (ejecución local, sin API key).

---

## Primeros pasos después de activar

1. **Configurar proveedor de IA** — Ve a Configuración → Proveedores y configura al menos un proveedor.
2. **Abrir un post** — Edita cualquier entrada o página existente.
3. **Ver el metabox SEO AI** — Aparece debajo del editor con 5 pestañas: SEO, Readability, Social, Schema y Advanced.
4. **Definir el keyword principal** — En la pestaña SEO, ingresa la palabra clave de enfoque.
5. **Revisar los análisis** — El plugin analiza automáticamente tu contenido y muestra puntuaciones.

---

**Siguiente:** [Optimización Manual](02-optimizacion-manual.md)
