=== ChronoInsta ===
Contributors: andres-tobon
Tags: instagram, feed, shortcode
Requires at least: 5.5
Tested up to: 6.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Muestra las ultimas imagenes publicas de un perfil de Instagram mediante un shortcode configurable.

== Descripcion ==
ChronoInsta renderiza un feed basico de Instagram a partir del nombre de usuario que definas en Ajustes > ChronoInsta. El plugin consulta el endpoint web `web_profile_info` de Instagram y genera el HTML en el servidor, entregando las imagenes como etiquetas `<img>` tradicionales para evitar bloqueos CORS y peticiones `fetch` desde el navegador. El archivo `scraper.php` queda disponible como endpoint JSON opcional para integraciones externas o refrescos forzados.

Al no depender de una API autenticada, el perfil debe ser publico y Instagram puede modificar el comportamiento del endpoint en cualquier momento. Para reducir el riesgo de bloqueos por exceso de peticiones, el resultado se cachea durante 15 minutos (valor configurable mediante el filtro `chrono_insta_cache_ttl`).

== Caracteristicas ==
- Ajuste unico para definir el nombre de usuario del perfil de Instagram que se mostrara.
- Shortcode `[chrono_insta_feed]` con soporte para el atributo `limit` (1 a 12) que controla cuantas imagenes se renderizan.
- Renderizado en el servidor sin dependencias JavaScript y con estilos propios (`css/style.css`) para evitar rutas 404 desde el tema.
- Cache automatica con fallback: si Instagram rechaza nuevas solicitudes se reutiliza la ultima respuesta valida e incluye un aviso.
- Endpoint `scraper.php` opcional para consumir el JSON del feed o forzar un refresco remoto (`refresh=1`).

== Instalacion ==
1. Copia la carpeta `ChronoInsta` en `wp-content/plugins/`.
2. Activa el plugin desde el panel de administracion de WordPress.
3. Visita Ajustes > ChronoInsta y guarda el nombre de usuario del perfil de Instagram que quieres mostrar.

== Uso ==
- Inserta el shortcode `[chrono_insta_feed]` en cualquier entrada, pagina o widget compatible.
- Controla el numero de imagenes con `[chrono_insta_feed limit="6"]` (valor por defecto: 9).
- Personaliza la apariencia sobrescribiendo los selectores `.chrono-insta-feed` o `.chrono-insta-card img` desde tu tema o CSS personalizado.
- El archivo `js/carousel.js` incluye un ejemplo de carrusel automatico; puedes encolarlo manualmente desde tu tema si deseas usarlo con un contenedor que tenga la clase `chrono-insta-feed`.
- Si necesitas una carga asincrona o placeholders, revisa `js/chrono-insta.js` como punto de partida y encola el script desde tu propio tema.

== Preguntas frecuentes ==
= Necesito una clave de API? =
No. El plugin consulta el endpoint web de Instagram y extrae las imagenes disponibles sin autenticacion.

= Por que no aparecen imagenes? =
Asegurate de que el perfil es publico y de que el servidor puede realizar peticiones externas. Instagram puede modificar o limitar el endpoint web que usa el plugin; si ocurre y el cache caduca, tendras que adaptar el codigo o esperar a que se levante el bloqueo.

= El navegador muestra 404 de output.css o styles.css =
Esas rutas no pertenecen al plugin. Revisa tu tema o snippets personalizados y elimina llamadas como `wp_enqueue_style( 'output', get_stylesheet_directory_uri() . '/assets/css/output.css' )` o `wp_enqueue_style( 'custom', get_site_url() . '/styles.css' )`. Si necesitas mantenerlas, crea los archivos en la ruta correspondiente para que WordPress deje de marcar 404. ChronoInsta solo encola `wp-content/plugins/ChronoInsta/css/style.css`.

== Notas tecnicas ==
- `chrono_insta_fetch_feed_payload()` utiliza el endpoint `web_profile_info` de Instagram con cabeceras simulando un navegador real.
- El HTML del feed se genera en el servidor y se entrega como parte del contenido del shortcode, evitando peticiones `fetch` del navegador y problemas CORS con `cdninstagram.com`.
- `scraper.php` expone el mismo resultado en formato JSON para automatizaciones externas o para forzar un refresco (`refresh=1`).
- Los estilos viven en `css/style.css`; el plugin ya no encola scripts por defecto.
- El resultado se cachea mediante transients (15 minutos por defecto) y se puede personalizar con el filtro `chrono_insta_cache_ttl`.

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
