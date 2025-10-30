document.addEventListener("DOMContentLoaded", function () {
  const feeds = document.querySelectorAll(".js-chrono-insta-feed");
  if (!feeds.length) {
    return;
  }

  const settings = window.ChronoInstaSettings || {};
  const scraperBaseUrl =
    settings.scraperUrl || "/wp-content/plugins/ChronoInsta/scraper.php";
  const profileBaseUrl =
    settings.profileBaseUrl || "https://www.instagram.com/";

  feeds.forEach(function (container) {
    const username = container.getAttribute("data-username");
    if (!username) {
      return;
    }

    const profileUrl =
      container.getAttribute("data-profile-url") ||
      profileBaseUrl + username + "/";
    const limit = parseInt(container.getAttribute("data-limit"), 10) || 9;
    const placeholderCount = Math.min(Math.max(limit, 6), 12);

    container.innerHTML = "";
    container.setAttribute("aria-busy", "true");
    container.classList.add("is-loading");

    for (let i = 0; i < placeholderCount; i += 1) {
      const placeholder = document.createElement("div");
      placeholder.className = "chrono-insta-card is-placeholder";
      placeholder.innerHTML = '<span class="chrono-insta-skeleton"></span>';
      container.appendChild(placeholder);
    }

    const requestUrl =
      scraperBaseUrl + "?username=" + encodeURIComponent(username);

    fetch(requestUrl)
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Respuesta invalida del servidor");
        }
        return response.json();
      })
      .then(function (data) {
        container.classList.remove("is-loading");
        container.setAttribute("aria-busy", "false");

        const hasImages =
          data && Array.isArray(data.images) && data.images.length > 0;

        if (!hasImages) {
          const errorMessage =
            data && data.error
              ? data.error
              : "No se pudieron cargar imagenes.";
          container.innerHTML =
            '<div class="chrono-insta-feedback is-error">' +
            errorMessage +
            "</div>";
          return;
        }

        container.innerHTML = "";

        data.images.slice(0, limit).forEach(function (item) {
          var isObject = item && typeof item === "object";
          var imageUrl = isObject ? item.url : item;

          if (!imageUrl) {
            return;
          }

          var permalink = isObject && item.permalink ? item.permalink : profileUrl;
          var altText =
            isObject && item.alt
              ? item.alt
              : "Publicacion reciente de " + username;

          const card = document.createElement("a");
          card.className = "chrono-insta-card";
          card.href = permalink;
          card.target = "_blank";
          card.rel = "noopener noreferrer";
          card.setAttribute(
            "aria-label",
            "Abrir publicacion de " + username + " en Instagram"
          );

          const img = document.createElement("img");
          img.src = imageUrl.replace(/\\u0026/g, "&");
          img.alt = altText;
          img.loading = "lazy";
          img.decoding = "async";

          img.addEventListener("load", function () {
            card.classList.add("is-ready");
          });

          img.addEventListener("error", function () {
            card.classList.add("is-error");
          });

          card.appendChild(img);
          container.appendChild(card);
        });
      })
      .catch(function (error) {
        console.error("ChronoInsta error:", error);
        container.classList.remove("is-loading");
        container.setAttribute("aria-busy", "false");
        container.innerHTML =
          '<div class="chrono-insta-feedback is-error">Error al cargar imagenes. Intenta mas tarde.</div>';
      });
  });
});
