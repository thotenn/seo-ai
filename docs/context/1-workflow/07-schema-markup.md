# Schema Markup (Datos Estructurados)

## Pestaña Schema en el Metabox

La pestaña **Schema** del metabox permite configurar los datos estructurados (Schema.org) para cada post individual.

### Selector de tipo de Schema

Elige el tipo de Schema más adecuado para el contenido:

| Tipo | Uso recomendado |
|------|-----------------|
| **Article** | Artículos generales |
| **BlogPosting** | Entradas de blog |
| **NewsArticle** | Artículos de noticias |
| **WebPage** | Páginas estáticas |
| **FAQPage** | Páginas de preguntas frecuentes |
| **HowTo** | Tutoriales paso a paso |
| **Product** | Páginas de producto |
| **Recipe** | Recetas de cocina |
| **Event** | Eventos |
| **JobPosting** | Ofertas de empleo |
| **Person** | Perfiles de personas |
| **Course** | Cursos educativos |

### Editor JSON personalizado

Para casos avanzados, puedes editar directamente el JSON de Schema. Esto permite agregar propiedades específicas que no están cubiertas por la interfaz visual.

---

## Salida en el Frontend

El plugin inyecta los datos estructurados en el `<head>` de cada página como JSON-LD:

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "Título del artículo",
  "author": {
    "@type": "Person",
    "name": "Nombre del autor"
  },
  "datePublished": "2025-01-15",
  "dateModified": "2025-01-20",
  "image": "https://ejemplo.com/imagen.jpg",
  "publisher": {
    "@type": "Organization",
    "name": "Mi Sitio",
    "logo": {
      "@type": "ImageObject",
      "url": "https://ejemplo.com/logo.png"
    }
  }
}
</script>
```

---

## Auto-generación de Schema

El plugin construye automáticamente el Schema a partir del meta del post:

| Propiedad Schema | Fuente |
|-----------------|--------|
| `headline` | Título del post |
| `author` | Autor del post en WordPress |
| `datePublished` | Fecha de publicación |
| `dateModified` | Fecha de última modificación |
| `image` | Imagen destacada |
| `publisher` | Organización configurada en ajustes |
| `description` | Meta descripción o extracto |

### Schemas siempre incluidos

Independientemente del tipo seleccionado, el plugin siempre genera:

- **WebSite** — Con `SearchAction` para que Google muestre la caja de búsqueda del sitio.
- **Organization/Person** — Entidad publicadora del sitio.
- **BreadcrumbList** — Migas de pan estructuradas (si el módulo Breadcrumbs está activo).

---

## Generación de Schema con IA

Desde la pestaña Schema, puedes usar la IA para generar datos estructurados:

- **Endpoint:** `POST /seo-ai/v1/ai/generate-schema`
- La IA analiza el contenido del post y sugiere el tipo de Schema más apropiado con todos los campos relevantes completados.

---

## Configuración Global (Settings → Schema)

| Opción | Descripción |
|--------|-------------|
| Tipo de Schema por defecto | Tipo que se aplica a posts nuevos (ej: `Article`) |
| Nombre de organización | Nombre de la entidad publicadora |
| Logo de organización | URL del logo para el Schema de organización |

---

**Anterior:** [Redes Sociales](06-redes-sociales.md) · **Siguiente:** [Redirecciones](08-redirecciones.md)
