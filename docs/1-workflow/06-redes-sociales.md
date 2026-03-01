# Redes Sociales (Open Graph y Twitter Cards)

## Pestaña Social en el Metabox

Al editar un post, la pestaña **Social** del metabox SEO AI permite configurar cómo se verá el contenido al compartirlo en redes sociales.

### Campos Open Graph

| Campo | Meta key | Descripción |
|-------|----------|-------------|
| OG Title | `_seo_ai_og_title` | Título para redes sociales |
| OG Description | `_seo_ai_og_description` | Descripción para redes sociales |
| OG Image | `_seo_ai_og_image` | Imagen para redes sociales (ID de attachment, con selector de medios) |

### Campos Twitter Cards

| Campo | Meta key | Descripción |
|-------|----------|-------------|
| Twitter Title | `_seo_ai_twitter_title` | Título específico para Twitter/X |
| Twitter Description | `_seo_ai_twitter_description` | Descripción específica para Twitter/X |

---

## Salida en el Frontend

El plugin genera automáticamente las etiquetas meta en el `<head>` de cada página:

### Open Graph

```html
<meta property="og:title" content="Título del post">
<meta property="og:description" content="Descripción del post">
<meta property="og:image" content="https://ejemplo.com/imagen.jpg">
<meta property="og:url" content="https://ejemplo.com/post/">
<meta property="og:type" content="article">
<meta property="og:site_name" content="Mi Sitio">
```

### Twitter Cards

```html
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Título del post">
<meta name="twitter:description" content="Descripción del post">
<meta name="twitter:image" content="https://ejemplo.com/imagen.jpg">
<meta name="twitter:site" content="@usuario">
```

---

## Cadena de Fallback

Si no configuras campos específicos para redes sociales, el plugin usa valores alternativos automáticamente:

### OG Title

1. OG Title personalizado (`_seo_ai_og_title`)
2. Título SEO (del módulo Meta Tags)
3. Título del post
4. Nombre del sitio

### OG Description

1. OG Description personalizada (`_seo_ai_og_description`)
2. Meta descripción SEO (del módulo Meta Tags)
3. Extracto del post o contenido
4. Tagline del sitio

### OG Image

1. Imagen OG personalizada (`_seo_ai_og_image`)
2. Imagen destacada del post
3. Imagen OG por defecto (configurada en ajustes)

### Twitter Title

1. Twitter Title personalizado (`_seo_ai_twitter_title`)
2. OG Title
3. Título SEO
4. Título del post
5. Nombre del sitio

### Twitter Description

1. Twitter Description personalizada (`_seo_ai_twitter_description`)
2. OG Description
3. Meta descripción SEO
4. Extracto del post o contenido
5. Tagline del sitio

### Twitter Image

Usa la misma cadena de fallback que OG Image.

---

## Configuración Global (Settings → Social)

| Opción | Descripción |
|--------|-------------|
| Imagen OG por defecto | Imagen que se usa cuando un post no tiene imagen OG ni imagen destacada |
| Tipo de Twitter Card | `summary` (miniatura pequeña) o `summary_large_image` (imagen grande) |
| Usuario de Twitter | Username de Twitter/X del sitio (ej: `@misitio`) |

---

**Anterior:** [Configuración General](05-configuracion-general.md) · **Siguiente:** [Schema Markup](07-schema-markup.md)
