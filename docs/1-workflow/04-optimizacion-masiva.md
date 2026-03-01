# Optimización Masiva

## Asistente de Optimización (Wizard)

El Dashboard de SEO AI incluye un asistente de optimización guiado que permite seleccionar posts, configurar campos y ejecutar la optimización con IA en tiempo real.

### Cómo acceder

1. Ve a **SEO AI** (Dashboard) en el panel de administración.
2. En la **tarjeta hero** superior se muestra la cantidad de posts sin optimizar.
3. Haz clic en **"Start Optimization"** para abrir el asistente modal.

### Paso 1 — Seleccionar Posts

- **Búsqueda:** Filtra posts por nombre usando el campo de búsqueda.
- **Tipo de post:** Selecciona entre los tipos de contenido configurados (post, page, etc.).
- **Filtro de estado:** Todos, Sin optimizar (score = 0 o sin título SEO), u Optimizados.
- **Lista de posts:** Muestra título, tipo y puntuación SEO actual con código de color (verde ≥70, amarillo ≥40, rojo <40).
- **Selección:** Usa checkboxes individuales o "Select All" para marcar los posts a optimizar.
- **Paginación:** Se muestran 20 posts por página con navegación.

### Paso 2 — Configurar Campos

Selecciona qué campos generar con IA:

| Campo | Descripción |
|-------|-------------|
| **SEO Title** | Título optimizado para buscadores |
| **Meta Description** | Descripción meta para SERP |
| **Focus Keyword** | Palabra clave principal del contenido |
| **Schema Type** | Tipo de schema markup (Article, FAQ, etc.) |
| **Open Graph** | Título y descripción para redes sociales |

Por defecto, SEO Title y Meta Description están seleccionados.

### Paso 3 — Revisar

Muestra un resumen antes de iniciar:

- Número de posts seleccionados.
- Campos que se generarán.
- Cálculo total de operaciones (posts × campos).
- Lista de títulos de los posts.
- Advertencia de que los campos existentes serán sobrescritos.

### Paso 4 — Progreso

Al hacer clic en **"Start Optimization"**:

1. Se envía una petición `POST /seo-ai/v1/queue/start` con los IDs de posts y campos.
2. El cliente inicia un **polling AJAX** cada 500ms llamando a `POST /seo-ai/v1/queue/process-next`.
3. Cada llamada procesa **un post a la vez** usando el proveedor de IA configurado.
4. Se muestra en tiempo real:
   - **Barra de progreso** animada con porcentaje.
   - **Contador** de posts procesados vs total.
   - **Terminal de log** estilo consola con entradas coloreadas por nivel (info=azul, warn=amarillo, error=rojo).
5. Al completar todos los posts, se muestra el mensaje "Optimization complete!" y el botón cambia a "Close".

### Cancelar

En cualquier momento durante el Paso 4, puedes hacer clic en **"Cancel"** para detener la cola. Los posts ya procesados conservan sus cambios.

---

## Auto-SEO al Publicar

Cada post individual puede activar la optimización automática al publicar:

- **Meta del post:** `_seo_ai_auto_seo`
- **Comportamiento:** Cuando está activado, al publicar o actualizar el post se ejecuta automáticamente la optimización completa con IA (vía los hooks `save_post` y `transition_post_status`).
- **Alcance:** Genera título SEO, meta descripción, focus keyword, tags OG y Schema según los campos configurados en `auto_seo_fields`.

Para activar Auto-SEO en un post:

1. Abre el post en el editor.
2. En el metabox SEO AI, pestaña **Advanced**, activa la opción "Auto-SEO al publicar".
3. Cada vez que publiques o actualices el post, se ejecutará la optimización automáticamente.

Las operaciones de Auto-SEO se registran en el [Activity Log](10-activity-log.md).

---

## Bulk Actions desde la lista de posts

Desde la lista de posts de WordPress (**Posts → All Posts**), el dropdown de acciones masivas incluye:

| Acción | Descripción |
|--------|-------------|
| **Optimize SEO with AI** | Programa la optimización de los posts seleccionados vía WP-Cron (5s de intervalo entre cada uno) |
| **Set Noindex** | Marca los posts seleccionados con directiva `noindex` |
| **Remove Noindex** | Elimina la directiva `noindex` de los posts seleccionados |

La acción "Optimize SEO with AI" usa `wp_schedule_single_event` y se procesa de forma asíncrona. El progreso no se muestra en tiempo real (a diferencia del Wizard del Dashboard).

---

## REST API del Wizard

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/seo-ai/v1/queue/posts` | `GET` | Lista de posts para selección (soporta `search`, `post_type`, `filter`, `page`, `per_page`) |
| `/seo-ai/v1/queue/start` | `POST` | Iniciar cola de optimización (params: `post_ids[]`, `fields[]`) |
| `/seo-ai/v1/queue/process-next` | `POST` | Procesar el siguiente post en la cola — retorna `{done, progress, log_entry}` |
| `/seo-ai/v1/queue/cancel` | `POST` | Cancelar la cola en ejecución |

La cola se almacena como un transient de WordPress (`seo_ai_optimize_queue`) con expiración de 1 hora.

---

## Consideraciones

- **Límites de API:** Cada post consume una llamada al proveedor de IA. Considera los límites de tu plan.
- **Tiempo:** El procesamiento depende de la velocidad del proveedor. Ollama (local) es inmediato pero más lento por request; las APIs cloud son más rápidas pero tienen rate limits.
- **Revisión:** Después de la optimización masiva, revisa los resultados. La IA genera buenos meta tags, pero siempre es recomendable verificar el contenido generado.
- **Activity Log:** Todas las operaciones de optimización (éxitos y errores) se registran en el Activity Log para auditoría.

---

**Anterior:** [Optimización con IA](03-optimizacion-con-ia.md) · **Siguiente:** [Configuración General](05-configuracion-general.md)
