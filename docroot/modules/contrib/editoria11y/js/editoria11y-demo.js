/* globals Drupal, drupalSettings, Ed11y, console */
/**
 * Drupal initializer.
 * Launch as behavior and pull variables from config.
 */

Drupal.behaviors.editoria11yAdmin = {
  attach: function (context, settings) {
    'use strict';

    if (context !== document) {
      return;
    }

    const ed11yContain = document.getElementById('ed11y-demo');
    ed11yContain.setAttribute('contenteditable', '');
    const main = document.querySelector('main');
    if (main && !main.querySelector('#ed11y-demo')) {
      main.appendChild(ed11yContain);
    } else if (!main) {
      let newMain = document.createElement('main');
      ed11yContain.insertAdjacentElement('beforebegin', newMain);
      newMain.insertAdjacentElement('afterbegin', ed11yContain);
    }

    ed11yContain.innerHTML = `
      <p>${Drupal.t('This page provides a simple text editor that demonstrates some common alerts, using your settings.')}</p>
      <h2>${Drupal.t("Heading level tests")}</h2>
      <h4>${Drupal.t("'Skipped heading level'")}</h4>
      <h4>${Drupal.t("Heading that is far too long.")} ${Drupal.t("Heading that is far too long.")} ${Drupal.t("Heading that is far too long.")} ${Drupal.t("Heading that is far too long.")} ${Drupal.t("Heading that is far too long.")} ${Drupal.t("Heading that is far too long.")} ${Drupal.t("Heading that is far too long.")}</h4>
      <p><strong>${Drupal.t("Paragraph that should be a heading")}</strong></p>
      <h3><em aria-hidden="true">${Drupal.t("Heading with no screen-reader accessible text")}</em></h3>
      <blockquote>${Drupal.t("Short quote")}</blockquote>
      
      <h2>${Drupal.t('Links')}</h2>
      
      <p><a href="https://foo-bar-baz" target="_blank">${Drupal.t('Surprise window')}</a></p>
      <p><a href="#ed11y-demo">${Drupal.t('Click here')}</a></p>
      <p><a href="#ed11y-demo">https://www.princeton.edu</a></p>

      <h2>${Drupal.t('Tables')}</h2>
      <table>
        <tr><th></th><th>3</th></tr>
        <tr><td>1</td><td>2</td></tr>
      </table>
      <h2>${Drupal.t('General QA')}</h2>
      <p>${Drupal.t('THE NEXT PARAGRAPHS SHOULD BE LISTS.')}</p>
      <p>1.</p>
      <p>2.</p>
     
      <h2>${Drupal.t("Images")}</h2>
      <p><img alt="" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 240 120'%3E%3Crect width='240' height='120' fill='%23cccccc'%3E%3C/rect%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='monospace' font-size='26px' fill='%23333333'%3Eimg%3C/text%3E%3C/svg%3E"></p>
      <p><img alt="${Drupal.t("Image of something")}" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 240 120'%3E%3Crect width='240' height='120' fill='%23cccccc'%3E%3C/rect%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='monospace' font-size='26px' fill='%23333333'%3Eimg%3C/text%3E%3C/svg%3E"></p>
      <p><a href="#ed11y-demo"><img alt="${Drupal.t("Image of something")}" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 240 120'%3E%3Crect width='240' height='120' fill='%23cccccc'%3E%3C/rect%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='monospace' font-size='26px' fill='%23333333'%3Eimg%3C/text%3E%3C/svg%3E"></a></p>
      <p><img alt="${Drupal.t("placeholder")}" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 240 120'%3E%3Crect width='240' height='120' fill='%23cccccc'%3E%3C/rect%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='monospace' font-size='26px' fill='%23333333'%3Eimg%3C/text%3E%3C/svg%3E"></p>
      <p><img alt="${Drupal.t("This alt text is far too long. If you need this much text to describe an image, the extra text should probably be in a visible caption, because everybody is going to need help interpreting all the interesting things happening in the image.")} ${Drupal.t("This alt text is far too long. If you need this much text to describe an image, the extra text should probably be in a visible caption, because everybody is going to need help interpreting all the interesting things happening in the image.")}" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 240 120'%3E%3Crect width='240' height='120' fill='%23cccccc'%3E%3C/rect%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='monospace' font-size='26px' fill='%23333333'%3Eimg%3C/text%3E%3C/svg%3E"></p>
      <p><img alt="jpg.jpg"" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 240 120'%3E%3Crect width='240' height='120' fill='%23cccccc'%3E%3C/rect%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='monospace' font-size='26px' fill='%23333333'%3Eimg%3C/text%3E%3C/svg%3E"></p>
      <p><a href="#ed11y-demo">Link combined with image <img alt="${Drupal.t("Something")}" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 240 120'%3E%3Crect width='240' height='120' fill='%23cccccc'%3E%3C/rect%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='monospace' font-size='26px' fill='%23333333'%3Eimg%3C/text%3E%3C/svg%3E"></a></p>
      <p><a href="foo.pdf">${Drupal.t("Surprise")}</a></p>
      
      <h2>${Drupal.t("Frames")}</h2>
      <iframe width="560" height="315" src="https://www.youtube.com/embed/QGsevnbItdU?si=W_b2i2o_n5v1ArvA" title="YouTube video player" sandbox="allow-scripts allow-same-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="origin" allowfullscreen></iframe>
      <h2>${Drupal.t("Editoria11y CSA")}</h2>
      <p class="low-contrast">${Drupal.t("Illegible color choice")}</p>
    `;

    // @todo: first empty heading cell in table should be manual check?
    /*
    * linkNewWindow
linkTextIsGeneric
linkTextIsURL
tableEmptyHeaderCell
textPossibleList
textUppercase
altImageOf
altImageOfLinked
altLong
altLongLinked
altPartOfLinkWithText

embedAudio
embedCustom
embedVideo
embedVisualization
linkDocument
    * */
  }
};


