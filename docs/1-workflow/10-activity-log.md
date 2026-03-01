# Activity Log

## Descripción

El Activity Log registra todas las operaciones realizadas por el plugin SEO AI. Cada entrada incluye nivel de severidad, operación, mensaje descriptivo, contexto detallado (JSON), usuario y timestamp.

---

## Acceso

Ve a **SEO AI → Activity Log** en el panel de administración.

---

## Niveles de log

| Nivel | Color | Descripción |
|-------|-------|-------------|
| `debug` | Gris | Información de diagnóstico detallada |
| `info` | Azul | Operaciones exitosas y eventos normales |
| `warn` | Amarillo | Situaciones inesperadas que no son errores |
| `error` | Rojo | Errores que impidieron completar una operación |

---

## Operaciones registradas

| Operación | Cuándo se registra |
|-----------|--------------------|
| `settings_change` | Al activar el plugin |
| `auto_seo` | Al ejecutar Auto-SEO al guardar/publicar un post (éxito o error) |
| `bulk_optimize` | Al lanzar optimización masiva desde el dropdown de acciones |
| `wizard_optimize` | Al iniciar, completar o cancelar el asistente de optimización del Dashboard |
| `wizard_item` | Por cada post procesado en el asistente (éxito o error) |
| `log_cleanup` | Al eliminar entradas antiguas del log |

---

## Filtros

La página de Activity Log ofrece tres filtros combinables:

- **Level** — Dropdown para filtrar por nivel: All Levels, Debug, Info, Warn, Error.
- **Operation** — Dropdown con las operaciones únicas registradas en la base de datos.
- **Search** — Campo de texto para buscar en los mensajes de log.

Los filtros se aplican vía parámetros GET y son combinables. El botón "Clear" restablece todos los filtros.

---

## Tabla de entradas

Cada fila muestra:

| Columna | Contenido |
|---------|-----------|
| Time | Fecha y hora formateada (ej: `Mar 1, 14:23:05`) |
| Level | Badge con color según nivel |
| Operation | Nombre de la operación en formato `code` |
| Message | Mensaje descriptivo de la entrada |
| User | Nombre del usuario que generó la entrada (o `—` si fue un proceso automático) |
| Context | Botón `…` para expandir el JSON con datos adicionales |

Al hacer clic en el botón `…`, se despliega una fila con el contexto en formato JSON indentado. Esto incluye datos como `post_id`, `fields`, `old_score`, `new_score`, `error`, etc.

---

## Paginación

Se muestran 30 entradas por página. La paginación incluye botones Previous/Next y el indicador "Page X of Y (N entries)".

---

## Limpieza de logs

El botón **"Clear Old Logs"** (rojo) permite eliminar entradas antiguas:

1. Haz clic en el botón.
2. Ingresa el número de días (por defecto: 30).
3. Se eliminan todas las entradas más antiguas que el número de días indicado.
4. La propia acción de limpieza se registra como una entrada `log_cleanup`.

---

## REST API

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/seo-ai/v1/logs` | `GET` | Obtener logs paginados. Params: `level`, `operation`, `search`, `page`, `per_page` |
| `/seo-ai/v1/logs` | `DELETE` | Eliminar logs antiguos. Params: `days` (número de días) |

Ambos endpoints requieren la capacidad `seo_ai_manage_settings`.

---

## Dashboard — Actividad Reciente

El Dashboard principal muestra las últimas 10 entradas del Activity Log en la sección "Recent Activity". Cada entrada muestra el badge de nivel, mensaje, timestamp relativo y botón para expandir el contexto.

---

## Clase Activity_Log

La clase `SeoAi\Activity_Log` provee métodos estáticos para interactuar con el log desde código PHP:

```php
// Registrar una entrada
Activity_Log::log('info', 'my_operation', 'Descripción del evento', [
    'post_id' => 123,
    'extra'   => 'datos adicionales',
]);

// Obtener entradas con filtros
$result = Activity_Log::get([
    'level'     => 'error',
    'operation' => 'auto_seo',
    'search'    => 'failed',
    'page'      => 1,
    'per_page'  => 30,
]);
// $result = ['items' => [...], 'total' => 42, 'pages' => 2]

// Limpiar entradas antiguas
$deleted = Activity_Log::cleanup(30); // elimina entradas de más de 30 días
```

---

**Anterior:** [Sitemap y Robots](09-sitemap-y-robots.md)
