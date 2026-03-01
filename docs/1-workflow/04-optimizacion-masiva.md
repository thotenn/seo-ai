# Optimización Masiva

## Auto-SEO al Publicar

Cada post individual puede activar la optimización automática al publicar:

- **Meta del post:** `_seo_ai_auto_seo`
- **Comportamiento:** Cuando está activado, al publicar o actualizar el post se ejecuta automáticamente la optimización completa con IA (vía el hook `wp_after_insert_post`).
- **Alcance:** Genera título SEO, meta descripción, focus keyword, tags OG y Schema.

Para activar Auto-SEO en un post:

1. Abre el post en el editor.
2. En el metabox SEO AI, pestaña **Advanced**, activa la opción "Auto-SEO al publicar".
3. Cada vez que publiques o actualices el post, se ejecutará la optimización automáticamente.

---

## Bulk Optimize — Optimización en lote

La página de **Bulk Optimize** permite seleccionar múltiples posts y optimizarlos de forma asíncrona.

### Cómo funciona

1. Ve a **SEO AI → Bulk Optimize** en el panel de administración.
2. Selecciona los posts que deseas optimizar.
3. Inicia el proceso.

### Procesamiento asíncrono

Los posts seleccionados no se procesan todos al mismo tiempo. El plugin usa el sistema de **WP-Cron** para procesarlos uno por uno:

- **Acción de cron:** `seo_ai_process_bulk_item`
- **Cola:** Los items se almacenan en el transient `seo_ai_bulk_queue`.
- **Velocidad:** Se procesa un post a la vez para evitar sobrecargar la API del proveedor de IA.

### Seguimiento del progreso

El progreso se puede consultar vía REST API:

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/seo-ai/v1/ai/bulk-optimize` | `POST` | Iniciar optimización masiva |

La interfaz de administración muestra el progreso en tiempo real: posts pendientes, en proceso y completados.

---

## Consideraciones

- **Límites de API:** Cada post consume una llamada al proveedor de IA. Considera los límites de tu plan.
- **Tiempo:** El procesamiento depende de la velocidad del proveedor. Ollama (local) es inmediato pero más lento por request; las APIs cloud son más rápidas pero tienen rate limits.
- **Revisión:** Después de la optimización masiva, revisa los resultados. La IA genera buenos meta tags, pero siempre es recomendable verificar el contenido generado.

---

**Anterior:** [Optimización con IA](03-optimizacion-con-ia.md) · **Siguiente:** [Configuración General](05-configuracion-general.md)
