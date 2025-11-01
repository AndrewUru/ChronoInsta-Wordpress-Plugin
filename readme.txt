=== ChronoInsta ===
Contributors: andres-tobon
Tags: instagram, feed, shortcode
Requires at least: 5.5
Tested up to: 6.4
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Muestra las ultimas imagenes publicas de un perfil de Instagram mediante un shortcode configurable y un endpoint JSON opcional.

== Descripcion ==
ChronoInsta genera un grid responsive con las imagenes publicas de un perfil de Instagram directamente desde el servidor. El shortcode `[chrono_insta_feed]` consulta la pagina del perfil, extrae las imagenes disponibles y entrega HTML estatico para evitar bloqueos CORS o dependencias de JavaScript.

El plugin incluye una pagina de ajustes en Ajustes > ChronoInsta donde puedes definir el usuario por defecto, el limite de imagenes y el tiempo de cache. El cache se almacena en transients y puede vaciarse manualmente desde la misma pantalla.

Tambien expone un endpoint REST (`/wp-json/chrono-insta/v1/feed`) y mantiene el archivo `scraper.php` para integraciones externas. Ambos devuelven JSON con las imagenes y respetan los parametros `username`, `limit` y `refresh`.

Instagram puede modificar el marcado en cualquier momento. Si sucede, ChronoInsta mantiene la ultima respuesta valida en cache y mostrara un aviso hasta que la llamada se recupere.

== Caracteristicas ==
- Shortcode `[chrono_insta_feed]` con soporte para `username`, `limit` (1-12) y `refresh`.
- Ajustes en el administrador para usuario por defecto, limite y TTL del cache.
- Cache automatica usando transients con fallback si Instagram falla temporalmente.
- Endpoint REST y `scraper.php` para obtener el feed en formato JSON.
- Estilos propios (`assets/css/style.css`) con mejoras de accesibilidad y CTA opcional.
- Filtro `chrono_insta_cache_ttl` para sobreescribir el tiempo de cache desde el tema o un plugin.

== Instalacion ==
1. Copia la carpeta `ChronoInsta` en `wp-content/plugins/`.
2. Activa el plugin desde el panel de administracion de WordPress.
3. Ve a Ajustes > ChronoInsta, guarda el usuario por defecto y el tiempo de cache deseado.

== Uso ==
- Inserta el shortcode `[chrono_insta_feed]` en cualquier entrada o plantilla.
- Controla el numero de imagenes con `[chrono_insta_feed limit="6"]`.
- Fuerza un refresco puntual del cache con `[chrono_insta_feed refresh="1"]`.
- Consume el feed en JSON con `https://tu-sitio.com/wp-json/chrono-insta/v1/feed?username=midestino&limit=6`.
- Si prefieres un endpoint sencillo, usa `https://tu-sitio.com/wp-content/plugins/ChronoInsta/scraper.php?username=midestino&limit=6`.

== Preguntas frecuentes ==
= Necesito una clave de API? =
No. ChronoInsta analiza el HTML publico de Instagram. El perfil debe ser publico y Instagram puede cambiar su marcado en cualquier momento.

= Por que no aparecen imagenes? =
Comprueba que el perfil sea publico y que tu servidor pueda hacer peticiones externas. Si Instagram responde con errores, ChronoInsta seguira mostrando la ultima version cacheada y un aviso para los visitantes.

= Puedo ajustar el tiempo de cache? =
Si. Usa la pagina de ajustes o el filtro `chrono_insta_cache_ttl`. El valor minimo son 60 segundos.

== Notas tecnicas ==
- Requiere WordPress 5.5+, PHP 7.4+ y transients habilitados.
- Las respuestas del REST API y `scraper.php` incluyen metadatos `username`, `limit`, `from_cache` y `fetched_at` (timestamp unix).
- El cache se registra en la opcion `chrono_insta_cache_index` para poder vaciarlo desde la interfaz.

== Changelog ==
= 1.7.0 =
* Nueva pagina de ajustes con controles para usuario, limite y cache, ademas de un boton para vaciar transients.
* Se expone un endpoint REST (`chrono-insta/v1/feed`) y se actualiza `scraper.php` para devolver JSON consistente.
* Mejor manejo de cache con fallback y mensajes de aviso cuando se usan datos almacenados.
* Estilos refinados con mejoras de accesibilidad y eliminacion de caracteres corruptos en el CTA.
* Limpieza general del codigo, normalizacion de cadenas y soporte de traducciones.

= 1.6.0 =
* Se reubico el CSS en `assets/css/style.css` y se encola como `chrono-insta-style` para evitar referencias a scripts removidos.
* No se encola JavaScript por defecto; cualquier script anterior debe eliminarse o hacerse opt-in manual.

= 1.5.0 =
* El feed ahora se renderiza en el servidor para evitar fetch en el navegador y los bloqueos CORS asociados.
* `scraper.php` reutiliza la nueva funcion interna y devuelve JSON consistente con el shortcode.
* Se elimina el script por defecto; los ejemplos JavaScript quedan como opt-in manual.
* Documentacion actualizada con recomendaciones para rutas CSS inexistentes.

= 1.4.0 =
* Se anadio cache con transients (15 minutos por defecto) para minimizar el riesgo de rate limits de Instagram; configurable mediante el filtro `chrono_insta_cache_ttl`.
* Las respuestas devuelven datos almacenados si Instagram bloquea temporalmente la solicitud, junto con un aviso opcional.

= 1.3.2 =
* Ajuste de cabeceras y cookies temporales para mejorar la compatibilidad con `web_profile_info` en servidores compartidos que bloquean las llamadas iniciales.
* Mensajes de error mas descriptivos cuando Instagram devuelve codigos distintos de 200.

= 1.3.1 =
* Se reemplazo el scraping HTML por el endpoint `web_profile_info` para obtener imagenes de forma mas estable.
* Las tarjetas enlazan directamente a la publicacion original cuando hay shortcode disponible.
* Manejo de errores mejorado en `scraper.php` con codigos HTTP y mensajes descriptivos.

= 1.3 =
* Nuevo grid responsive con placeholders y CTA para visitar el perfil.
* Script y estilos actualizados para mejorar la experiencia de carga y los mensajes de error.

= 1.2 =
* Version incluida en este repositorio.

== Notas de desarrollo ==
- El plugin esta pensado para WordPress 5.0+ y PHP 7.4+.

