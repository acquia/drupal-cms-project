Drupal.behaviors.cardBehaviour = {
  attach(context) {
    const cards = context.querySelectorAll('.card');

    for (let i = 0; i < cards.length; i += 1) {
      let down;
      let up;
      const card = cards[i];
      const link = card.querySelector('a');

      card.addEventListener('mousedown', () => {
        down = +new Date();
      });

      card.addEventListener('mouseup', (e) => {
        const leftClick = e.which && ![1, 2].indexOf(e.which);
        up = +new Date();
        if (up - down < 200 && link && leftClick) {
          link.click();
        }
      });
    }
  },
};
