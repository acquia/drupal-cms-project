document.addEventListener('DOMContentLoaded', () => {
  const counters = document.querySelectorAll('.counter');

  counters.forEach(counter => {
    counter.innerText = '0';

    const updateCounter = () => {
      const target = +counter.getAttribute('data-target');
      const duration = 2000;
      const delay = 16;
      const increment = target / (duration / delay);

      const countIt = () => {
        const current = +counter.innerText || 0;
        if (current < target) {
          counter.innerText = Math.ceil(current + increment);
          setTimeout(countIt, delay);
        } else {
          counter.innerText = target;
        }
      };
      countIt();
    };

    updateCounter();
  });
});
