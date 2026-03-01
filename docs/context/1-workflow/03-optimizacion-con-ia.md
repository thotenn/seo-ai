# Optimización con IA

## Funciones de IA en el Metabox

El metabox SEO AI ofrece tres acciones de IA para optimizar el contenido:

### Generate Meta

Genera campos SEO individuales (título, descripción) usando IA a partir del contenido del post.

- **Qué hace:** Envía el contenido del post al proveedor de IA activo y recibe sugerencias para el título SEO y la meta descripción.
- **Cuándo usarlo:** Cuando tienes contenido escrito pero aún no has definido los meta tags.

### Fix with AI

Analiza los checks que están fallando y solicita correcciones específicas a la IA.

- **Qué hace:** Envía las puntuaciones actuales y la lista de checks que no pasan al proveedor de IA. La IA devuelve recomendaciones y correcciones específicas.
- **Cuándo usarlo:** Cuando ya tienes meta tags pero la puntuación es baja y quieres mejorarla.

### Optimize All

Optimización completa con un solo clic.

- **Qué hace:** Genera automáticamente título SEO, meta descripción, focus keyword, tags Open Graph y datos de Schema.
- **Cuándo usarlo:** Para una optimización rápida y completa del post.

---

## Endpoints REST

Las funciones de IA se comunican a través de la REST API de WordPress:

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/seo-ai/v1/ai/generate-meta` | `POST` | Genera meta tags individuales |
| `/seo-ai/v1/ai/optimize` | `POST` | Optimización completa del post |
| `/seo-ai/v1/ai/generate-schema` | `POST` | Genera datos de Schema |
| `/seo-ai/v1/ai/bulk-optimize` | `POST` | Optimización masiva de múltiples posts |

Todos los endpoints requieren autenticación de WordPress (nonce) y permisos de edición del post.

---

## Construcción de Prompts

El plugin construye los prompts que envía a la IA combinando:

1. **Contenido del post** — Título, cuerpo del texto y extracto.
2. **Meta actual** — Título SEO, meta descripción y keyword actuales (si existen).
3. **Instrucciones de idioma** — El prompt se adapta al idioma configurado en WordPress.
4. **Contexto de checks** — En el caso de "Fix with AI", incluye los checks que fallan y sus puntuaciones.

Ejemplo simplificado de prompt para Generate Meta:

```
Dado el siguiente contenido de un artículo, genera un título SEO
(máximo 60 caracteres) y una meta descripción (máximo 160 caracteres)
optimizados para la palabra clave "[keyword]".

Título del artículo: [título]
Contenido: [contenido resumido]
```

---

## Selección de Proveedor

- El plugin usa el **proveedor activo** configurado en **SEO AI → Configuración → Proveedores**.
- Cada proveedor tiene su propia configuración de modelo, API key, URL base y temperatura.
- Si el proveedor activo falla (API key inválida, servicio no disponible), se muestra un error al usuario.

Para cambiar de proveedor, ve a **Configuración → Proveedores** y selecciona otro proveedor como activo.

---

**Anterior:** [Optimización Manual](02-optimizacion-manual.md) · **Siguiente:** [Optimización Masiva](04-optimizacion-masiva.md)
