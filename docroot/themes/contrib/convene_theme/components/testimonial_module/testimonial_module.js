(function (Drupal) {
  Drupal.behaviors.testimonialSlider = {
    attach(context, settings) {
      const sliders = context.querySelectorAll(
        '.testimonial-module__slider-track',
      );

      sliders.forEach((slider) => {
        if (slider.dataset.jsProcessed) return;
        slider.dataset.jsProcessed = 'true';

        const container = slider.closest('.testimonial-module');
        const prevBtn = container.querySelector(
          '.testimonial-module__nav--prev',
        );
        const nextBtn = container.querySelector(
          '.testimonial-module__nav--next',
        );
        const dotsContainer = container.querySelector(
          '.testimonial-module__dots',
        );

        let isDown = false;
        let startX;
        let scrollLeft;

        // Calculate scroll amount (card width)
        const getScrollAmount = () => {
          const firstCard = slider.firstElementChild;
          if (!firstCard) return slider.clientWidth;
          return firstCard.offsetWidth;
        };

        const updateButtons = () => {
          if (prevBtn) prevBtn.disabled = slider.scrollLeft <= 5; // Tolerance
          if (nextBtn)
            nextBtn.disabled =
              slider.scrollLeft >= slider.scrollWidth - slider.clientWidth - 5;
        };

        // Dots generation
        const generateDots = () => {
          if (!dotsContainer) return;
          dotsContainer.innerHTML = '';
          const cards = Array.from(slider.children);
          if (cards.length <= 1) return; // Note: if only 1 item, no dots needed

          const totalDots = cards.length;

          for (let i = 0; i < totalDots; i++) {
            const dot = document.createElement('button');
            dot.classList.add('testimonial-module__dot');
            dot.ariaLabel = `Go to slide ${i + 1}`;
            if (i === 0) dot.classList.add('active');
            dotsContainer.appendChild(dot);

            dot.addEventListener('click', () => {
              const scrollAmount = getScrollAmount();
              slider.scrollTo({
                left: i * scrollAmount,
                behavior: 'smooth',
              });
            });
          }
        };

        const updateDots = () => {
          if (!dotsContainer) return;
          const dots = dotsContainer.querySelectorAll(
            '.testimonial-module__dot',
          );
          if (dots.length === 0) return;

          const scrollAmount = getScrollAmount();
          // Avoid division by 0
          if (scrollAmount <= 0) return;
          const activeIndex = Math.round(slider.scrollLeft / scrollAmount);

          dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === activeIndex);
          });
        };

        if (prevBtn && nextBtn) {
          prevBtn.addEventListener('click', () => {
            slider.scrollBy({ left: -getScrollAmount(), behavior: 'smooth' });
          });

          nextBtn.addEventListener('click', () => {
            slider.scrollBy({ left: getScrollAmount(), behavior: 'smooth' });
          });
        }

        slider.addEventListener('scroll', () => {
          updateButtons();
          updateDots();
        });

        // Initial setup
        // Need a slight delay to ensure DOM is fully laid out for Canvas Builder
        setTimeout(() => {
          generateDots();
          updateButtons();
        }, 100);

        // Touch/Drag support
        slider.addEventListener('mousedown', (e) => {
          isDown = true;
          slider.classList.add('active');
          startX = e.pageX - slider.offsetLeft;
          scrollLeft = slider.scrollLeft;
        });
        slider.addEventListener('mouseleave', () => {
          isDown = false;
          slider.classList.remove('active');
        });
        slider.addEventListener('mouseup', () => {
          isDown = false;
          slider.classList.remove('active');
        });
        slider.addEventListener('mousemove', (e) => {
          if (!isDown) return;
          e.preventDefault();
          const x = e.pageX - slider.offsetLeft;
          const walk = (x - startX) * 2;
          slider.scrollLeft = scrollLeft - walk;
        });
      });
    },
  };
})(Drupal);
