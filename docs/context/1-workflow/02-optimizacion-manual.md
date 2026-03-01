# Optimización Manual

## El Metabox del Editor

Al editar un post o página, SEO AI muestra un metabox con 5 pestañas:

| Pestaña | ID | Descripción |
|---------|----|-------------|
| SEO | `seo` | Campos SEO principales y análisis de checks |
| Readability | `readability` | Análisis de legibilidad del contenido |
| Social | `social` | Open Graph y Twitter Cards |
| Schema | `schema` | Datos estructurados (JSON-LD) |
| Advanced | `advanced` | Configuraciones avanzadas del post |

---

## Pestaña SEO — Campos

### Focus Keyword
La palabra clave principal sobre la que deseas posicionar el post. Todos los checks SEO se evalúan en relación a este keyword.

### SEO Title
Título que aparece en los resultados de búsqueda. Incluye un contador de caracteres para mantenerte dentro del rango óptimo (50-60 caracteres).

### Meta Description
Descripción que aparece en los resultados de búsqueda. Incluye un contador de caracteres para el rango óptimo (120-160 caracteres).

### Canonical URL
URL canónica del post. Útil para evitar contenido duplicado.

### Robots Meta
Toggles para controlar la indexación:
- **noindex** — Impide que los buscadores indexen esta página.
- **nofollow** — Impide que los buscadores sigan los enlaces de esta página.

---

## Checks SEO (12 checks)

Cada check tiene un peso que determina su importancia en la puntuación final:

| # | Check | Peso | Qué evalúa |
|---|-------|------|-------------|
| 1 | Keyword en Título | 15 | Si el focus keyword aparece en el título SEO |
| 2 | Keyword en Meta Description | 10 | Si el focus keyword aparece en la meta descripción |
| 3 | Keyword en URL | 10 | Si el focus keyword aparece en el slug del post |
| 4 | Keyword en Introducción | 10 | Si el keyword aparece en las primeras 100 palabras |
| 5 | Keyword en Subheadings | 8 | Si el keyword aparece en encabezados (H2, H3, etc.) |
| 6 | Densidad del Keyword | 10 | Frecuencia del keyword en relación al contenido total |
| 7 | Longitud del Título SEO | 8 | Si el título tiene entre 50-60 caracteres |
| 8 | Longitud de Meta Description | 8 | Si la descripción tiene entre 120-160 caracteres |
| 9 | Longitud del Contenido | 7 | Si el contenido tiene suficientes palabras |
| 10 | Enlaces Internos | 7 | Si el post contiene enlaces a otras páginas del sitio |
| 11 | Enlaces Externos | 4 | Si el post contiene enlaces a sitios externos |
| 12 | Texto Alt en Imágenes | 5 | Si las imágenes tienen atributo alt descriptivo |

**Peso total:** 102

---

## Checks de Legibilidad (7 checks)

| # | Check | Peso | Qué evalúa |
|---|-------|------|-------------|
| 1 | Flesch Reading Ease | 20 | Facilidad de lectura según la fórmula Flesch |
| 2 | Longitud de Oraciones | 15 | Si las oraciones son demasiado largas |
| 3 | Longitud de Párrafos | 15 | Si los párrafos son demasiado extensos |
| 4 | Voz Pasiva | 15 | Porcentaje de oraciones en voz pasiva |
| 5 | Distribución de Subheadings | 15 | Si el texto está bien dividido con encabezados |
| 6 | Palabras de Transición | 10 | Uso de conectores y palabras de transición |
| 7 | Oraciones Consecutivas | 10 | Si hay oraciones consecutivas que inician igual |

**Peso total:** 100

---

## Fórmula de Puntuación

La puntuación de cada categoría (SEO y Legibilidad) se calcula como un **promedio ponderado**:

```
Puntuación = Σ (resultado_check × peso_check) / Σ (pesos)
```

Cada check individual produce un resultado entre 0 y 100. El promedio ponderado genera una puntuación final en escala de 0 a 100.

### Visualización del Score

El metabox muestra un **círculo de puntuación** con código de colores:

| Color | Rango | Significado |
|-------|-------|-------------|
| 🟢 Verde | ≥ 70 | Buena optimización |
| 🟡 Amarillo | ≥ 40 y < 70 | Necesita mejoras |
| 🔴 Rojo | < 40 | Optimización deficiente |

Debajo del círculo se listan los checks individuales con su estado (aprobado, advertencia o fallo) y recomendaciones específicas para mejorar.

---

**Anterior:** [Primeros Pasos](01-primeros-pasos.md) · **Siguiente:** [Optimización con IA](03-optimizacion-con-ia.md)
