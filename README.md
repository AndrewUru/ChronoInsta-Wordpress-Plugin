# ChronoInsta WordPress Plugin

ChronoInsta renderiza un feed basico de Instagram directamente en el servidor a partir del nombre de usuario configurado en WordPress. Al generar el HTML y las etiquetas `<img>` desde PHP evita bloqueos CORS y solo encola su propio CSS para la cuadricula.

## Uso rapido

1. Copia la carpeta `ChronoInsta` en `wp-content/plugins/` y activa el plugin.
2. Abre **Ajustes -> ChronoInsta** e introduce el nombre de usuario del perfil (debe ser publico).
3. Inserta el shortcode `[chrono_insta_feed]` donde quieras mostrar el feed. Ajusta la cantidad de imagenes con `[chrono_insta_feed limit="6"]`.

## Extras

- El endpoint `scraper.php` expone el mismo feed en JSON para integraciones externas (`?username=...&refresh=1` fuerza el refresco).
- `assets/css/style.css` controla el layout; personalizalo desde tu tema si necesitas un diseno distinto.
- Los archivos de la carpeta `js/` quedan como ejemplos opcionales y ya no se encolan automaticamente.
