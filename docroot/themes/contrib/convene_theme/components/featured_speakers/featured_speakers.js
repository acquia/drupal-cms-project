(function (Drupal) {
  Drupal.behaviors.featuredSpeakersSlider = {
    attach: function (context, settings) {
      const sliders = context.querySelectorAll(".featured-speakers__slider");

      sliders.forEach((slider) => {
        if (slider.dataset.jsProcessed) return;
        slider.dataset.jsProcessed = "true";

        const container = slider.closest(".featured-speakers");
        if (
          container &&
          container.classList.contains("featured-speakers--list")
        ) {
          return;
        }

        const prevBtn = container.querySelector(
          ".featured-speakers__nav--prev",
        );
        const nextBtn = container.querySelector(
          ".featured-speakers__nav--next",
        );
        const dotsContainer = container.querySelector(
          ".featured-speakers__dots",
        );

        let isDown = false;
        let startX;
        let scrollLeft;

        // Calculate scroll amount (card width + gap)
        const getScrollAmount = () => {
          const firstCard = slider.firstElementChild;
          if (!firstCard) return 300;
          const style = window.getComputedStyle(slider);
          const gap = parseInt(style.gap || 0);
          return firstCard.offsetWidth + gap;
        };

        const updateButtons = () => {
          prevBtn.disabled = slider.scrollLeft <= 5; // Tolerance
          nextBtn.disabled =
            slider.scrollLeft >= slider.scrollWidth - slider.clientWidth - 5;
        };

        // Dots generation
        const generateDots = () => {
          dotsContainer.innerHTML = "";
          const cards = Array.from(slider.children);
          if (cards.length === 0) return;

          const style = window.getComputedStyle(slider);
          const gap = parseInt(style.gap || 0);
          // Use card width + gap for scroll amount
          // Fallback to 300 if no cards yet (shouldn't happen here)
          const cardWidth = cards[0].offsetWidth || 300;
          const scrollAmount = cardWidth + gap;

          const visibleCards = Math.floor(slider.clientWidth / scrollAmount);
          // Calculate snap points.
          // If we scroll one by one:
          const totalDots = Math.max(1, cards.length - visibleCards + 1);

          for (let i = 0; i < totalDots; i++) {
            const dot = document.createElement("button");
            dot.classList.add("featured-speakers__dot");
            dot.ariaLabel = `Go to slide ${i + 1}`;
            if (i === 0) dot.classList.add("active");
            dotsContainer.appendChild(dot);

            dot.addEventListener("click", () => {
              slider.scrollTo({
                left: i * scrollAmount,
                behavior: "smooth",
              });
            });
          }
        };

        const updateDots = () => {
          const dots = dotsContainer.querySelectorAll(
            ".featured-speakers__dot",
          );
          if (dots.length === 0) return;

          const style = window.getComputedStyle(slider);
          const gap = parseInt(style.gap || 0);
          const cardWidth = slider.firstElementChild
            ? slider.firstElementChild.offsetWidth
            : 300;
          const scrollAmount = cardWidth + gap;

          const activeIndex = Math.round(slider.scrollLeft / scrollAmount);

          dots.forEach((dot, index) => {
            dot.classList.toggle("active", index === activeIndex);
          });
        };

        if (prevBtn && nextBtn) {
          prevBtn.addEventListener("click", () => {
            slider.scrollBy({ left: -getScrollAmount(), behavior: "smooth" });
          });

          nextBtn.addEventListener("click", () => {
            slider.scrollBy({ left: getScrollAmount(), behavior: "smooth" });
          });
        }

        slider.addEventListener("scroll", () => {
          updateButtons();
          updateDots();
        });

        // Initial setup
        generateDots();
        updateButtons();

        // Touch/Drag support (optional enhancement)
        slider.addEventListener("mousedown", (e) => {
          isDown = true;
          slider.classList.add("active");
          startX = e.pageX - slider.offsetLeft;
          scrollLeft = slider.scrollLeft;
        });
        slider.addEventListener("mouseleave", () => {
          isDown = false;
          slider.classList.remove("active");
        });
        slider.addEventListener("mouseup", () => {
          isDown = false;
          slider.classList.remove("active");
        });
        slider.addEventListener("mousemove", (e) => {
          if (!isDown) return;
          e.preventDefault();
          const x = e.pageX - slider.offsetLeft;
          const walk = (x - startX) * 2; //scroll-fast
          slider.scrollLeft = scrollLeft - walk;
        });
      });
    },
  };
})(Drupal);
