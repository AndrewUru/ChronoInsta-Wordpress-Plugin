=== ChronoInsta ===
Contributors: andres-tobon
Tags: instagram, feed, shortcode
Requires at least: 5.5
Tested up to: 6.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Muestra las ultimas imagenes publicas de un perfil de Instagram mediante un shortcode configurable.

== Descripcion ==
ChronoInsta renderiza un feed basico de Instagram a partir del nombre de usuario que definas en Ajustes > ChronoInsta. El plugin usa el archivo `scraper.php` para consultar el endpoint `web_profile_info` que Instagram expone en la web, obtiene el JSON del perfil y genera una cuadricula con las imagenes mas recientes mediante JavaScript. Es una alternativa rapida cuando no puedes usar la API oficial, pensada para sitios sencillos que solo necesitan mostrar imagenes recientes.

Al no depender de una API autenticada, el perfil debe ser publico y Instagram puede modificar el comportamiento del endpoint en cualquier momento. Para reducir el riesgo de bloqueos por exceso de peticiones, el resultado se cachea durante 15 minutos (valor configurable mediante filtro).

== Caracteristicas ==
- Ajuste unico para definir el nombre de usuario del perfil de Instagram que se mostrara.
- Shortcode `[chrono_insta_feed]` para incrustar el contenedor en cualquier entrada o pagina.
- Carga asincrona de imagenes con JavaScript, placeholders de carga y mensajes de error amigables sin necesidad de tokens ni claves adicionales.
- Diseno adaptable mediante un grid responsive con efectos hover y CTA configurable hacia el perfil.
- Cache automatica con fallback para reutilizar la ultima respuesta valida si Instagram rechaza temporalmente nuevas solicitudes.

== Instalacion ==
1. Copia la carpeta `ChronoInsta` en `wp-content/plugins/`.
2. Activa el plugin desde el panel de administracion de WordPress.
3. Visita Ajustes > ChronoInsta y guarda el nombre de usuario del perfil de Instagram que quieres mostrar.

== Uso ==
- Inserta el shortcode `[chrono_insta_feed]` en cualquier entrada, pagina o widget compatible con shortcodes.
- Opcional: ajusta el estilo de las imagenes sobrescribiendo el selector `#chrono-insta-feed img` desde tu tema o CSS personalizado.
- El archivo `js/carousel.js` incluye un ejemplo de carrusel automatico; encola el script desde tu tema si deseas usarlo con un contenedor que tenga la clase `chrono-insta-feed`.
- Para forzar una actualizacion manual puedes llamar a `scraper.php?username=TU_USUARIO&refresh=1`; si Instagram responde con error, el plugin intentara devolver el cache previo.

== Preguntas frecuentes ==
= Necesito una clave de API? =
No. El plugin consulta el endpoint web de Instagram y extrae las imagenes disponibles sin autenticacion.

= Por que no aparecen imagenes? =
Asegurate de que el perfil es publico y de que el servidor puede realizar peticiones externas. Instagram puede modificar o limitar el endpoint web que usa el plugin; si ocurre y el cache caduca, tendras que adaptar el codigo o esperar a que se levante el bloqueo.

== Notas tecnicas ==
- `scraper.php` consulta la API publica `web_profile_info` de Instagram con cabeceras personalizadas y cabeceras de seguridad simulando un navegador real.
- El JavaScript almacenado en `js/chrono-insta.js` consulta `scraper.php`, muestra placeholders mientras espera la respuesta y reemplaza el grid con imagenes cuando estan listas.
- Los estilos se sirven desde `css/style.css`, que aplica el grid, animaciones de carga, CTA y feedback de errores.
- El resultado se cachea mediante transients (15 minutos por defecto) y se puede personalizar con el filtro `chrono_insta_cache_ttl`.
- Comprueba que el hosting permite solicitudes externas a Instagram, ya que algunos servidores bloquean conexiones salientes o requieren whitelists para llamadas HTTP.

== Changelog ==
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
