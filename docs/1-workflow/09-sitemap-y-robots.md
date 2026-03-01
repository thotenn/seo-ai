# XML Sitemap y robots.txt

## XML Sitemap

El módulo de Sitemap genera automáticamente un archivo `sitemap.xml` accesible en la raíz del sitio.

### Estructura

```
https://tusitio.com/sitemap.xml          → Índice principal
https://tusitio.com/sitemap-post-1.xml   → Sitemap de posts (página 1)
https://tusitio.com/sitemap-page-1.xml   → Sitemap de páginas (página 1)
```

### Paginación

- **URLs por página:** 1000 (por defecto)
- **Rango configurable:** 100 a 50,000 URLs por página
- **Opción:** `sitemap_max_entries`

Si tienes más de 1000 posts, se generan múltiples archivos de sitemap automáticamente.

### Contenido incluido

Por defecto incluye:
- Posts publicados
- Páginas publicadas
- Custom Post Types (configurable desde ajustes)

Cada entrada del sitemap incluye:

| Campo | Valor |
|-------|-------|
| `<loc>` | URL del post |
| `<lastmod>` | Fecha de última modificación del post |
| `<changefreq>` | Frecuencia de cambio basada en el tipo de post |
| `<priority>` | Prioridad basada en el tipo de post |

### Sistema de caché

El sitemap usa **transients de WordPress** para evitar regenerar el XML en cada petición:

| Configuración | Valor |
|---------------|-------|
| Prefijo del transient | `seo_ai_sitemap_` |
| TTL (duración del caché) | 43,200 segundos (12 horas) |
| Clave del índice | `seo_ai_sitemap_index` |
| Clave de sub-sitemaps | `seo_ai_sitemap_{type}_{page}` |

### Invalidación automática

El caché se invalida automáticamente cuando:

- Se guarda un post (`save_post`)
- Se elimina un post (`delete_post`)
- Se crea un término/categoría (`created_term`)
- Se edita un término/categoría (`edited_term`)
- Se elimina un término/categoría (`delete_term`)

---

## robots.txt

El plugin genera un archivo **robots.txt virtual** utilizando el filtro `robots_txt` de WordPress. No crea un archivo físico en el servidor.

### Reglas por defecto

```
User-agent: *
Allow: /
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

Sitemap: https://tusitio.com/sitemap.xml
```

### Características

- **Virtual:** Se genera dinámicamente con cada petición a `/robots.txt`. No existe como archivo físico.
- **Inyección de Sitemap:** Agrega automáticamente la URL del sitemap al final del archivo.
- **Reglas base:** Permite el acceso a todo el sitio excepto `/wp-admin/` (con excepción de `admin-ajax.php` que se permite para funcionalidad AJAX).

---

## Configuración Global (Settings → Sitemap)

| Opción | Descripción |
|--------|-------------|
| Post types incluidos | Selecciona qué tipos de contenido se incluyen en el sitemap |
| Posts por página | Número máximo de URLs por archivo de sitemap (defecto: 1000) |

---

**Anterior:** [Redirecciones](08-redirecciones.md)
