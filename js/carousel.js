document.addEventListener("DOMContentLoaded", function () {
  const carousel = document.querySelector(".chrono-insta-feed");

  if (!carousel) return;

  let scrollAmount = 0;
  const scrollStep = 2; // Velocidad del scroll (puedes ajustar)
  const maxScroll = carousel.scrollWidth - carousel.clientWidth;

  function autoScroll() {
    scrollAmount += scrollStep;
    if (scrollAmount >= maxScroll) {
      scrollAmount = 0;
    }
    carousel.scrollTo({
      left: scrollAmount,
      behavior: "smooth",
    });
  }

  setInterval(autoScroll, 50); // Cada 50ms hace un pequeño movimiento
});
