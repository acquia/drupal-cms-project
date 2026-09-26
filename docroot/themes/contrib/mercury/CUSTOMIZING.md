## Customizing Mercury

**Don't subtheme Mercury!** It does not provide backwards compatibility. This allows us to rapidly innovate, iterate, and improve it. If you create a sub-theme of Mercury, it is likely to break in the future.

### Fonts & colors

To change the fonts or colors, copy the `src/theme.css` and `src/fonts.css` files to the web root, so that `theme.css` and `fonts.css` are sitting next to `index.php`. Commit them to your Git repository, and clear Drupal's cache. You can customize them however you like; changes you make to it will be reflected immediately on your site.

### Advanced customizations

If you want to make deeper customizations (e.g., to components or JavaScript), you will need to convert Mercury to a custom theme with the same machine name. You can do this by running the following at the command line, from the Drupal project root (assuming `web` is the web root):

```shell
mkdir -p web/themes/custom
cp -R web/themes/contrib/mercury web/themes/custom/mercury
git add web/themes/custom/mercury
composer remove drupal/mercury
```

Finally, clear Drupal's cache (via the UI, or `drush cache:rebuild`).

### Custom components

Mercury uses [single-directory components](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components) and comes with a variety of commonly used components. You can add new components and modify existing ones, but be sure to rebuild the CSS when you make changes.

## Building CSS

Mercury uses [Tailwind](https://tailwindcss.com) to simplify styling by using classes to compose designs directly in the markup.

If you want to customize the Tailwind-generated CSS, install the development tooling dependencies by running `npm install` in your theme's directory.

If you modify CSS files or classes in a Twig template, run `npm run build` to rebuild the CSS. For development, you can use `npm run dev` to watch for changes and automatically rebuild the CSS.

## Code Formatting

Mercury uses [Prettier](https://prettier.io) to automatically format code for consistency. The project is configured with plugins for Tailwind CSS and Twig templates.

For the best experience, [set up Prettier in your editor](https://prettier.io/docs/editors) to automatically format files on save.

Run `npm run format` to format all files in your theme, or `npm run format:check` to check if files are formatted correctly without making changes.

**Note**: Some files are excluded from formatting via `.prettierignore`, such as Drupal's `html.html.twig` template, which contains placeholder tokens that break Prettier's HTML parsing.

## Component JavaScript

`lib/component.js` has two classes you can use to nicely encapsulate your component JS without pasting all the `Drupal.behaviors.componentName` boilerplate into every file. The steps are:

1. Extend the `ComponentInstance` class to a new class with the code for your component.
2. Create a new instance of the `ComponentType` class to automatically activate all the component instances on that page.

For example, here's a stub of `accordion.js`:

```js
import { ComponentType, ComponentInstance } from "../../lib/component.js";

// Make a new class with the code for our component.
//
// In every method of this class, `this.el` is an HTMLElement object of
// the component container, whose selector you provide below. You don't
// have an array of elements that you have to `.forEach()` over yourself;
// the ComponentType class handles all that for you.
class Accordion extends ComponentInstance {
  // Every subclass must have an `init` method to activate the component.
  init() {
    this.el.querySelector(".accordion--content").classList.toggle("visible");
    this.el.addClass("js");
  }

  // You may also implement a `remove()` method to clean up when a component is
  // about to be removed from the document. This will be invoked during the
  // `detach()` method of the Drupal behavior.

  // You can create as many other methods as you want; in all of them,
  // `this.el` represents the single instance of the component. Any other
  // properties you add to `this` will be isolated to that one instance
  // as well.
}

// Now we instantiate ComponentType to find the component elements and run
// our script.
new ComponentType(
  // First argument: The subclass of ComponentInstance we just created above.
  Accordion,
  // Second argument: A camel-case unique ID for the behavior (and for `once()`
  // if applicable).
  "accordion",
  // Third argument: A selector for `querySelectorAll()`. All matching elements
  // on the page get their own instance of the subclass you created, each of
  // which has `this.el` pointing to one of those matches.
  ".accordion",
);
```

This is all the code required to be in each component. The ComponentType instance handles finding the elements, running them through `once` if available, and adding them to `Drupal.behaviors`.

All the objects created this way will be stored in a global variable so you can do stuff with them later. Since the `namespace` variable at the top of component.js is `mercuryComponents`, you would find the Accordion's ComponentType instance at `window.mercuryComponents.accordion`.

Furthermore, `window.mercuryComponents.accordion.instances` is an array of all the ComponentInstance objects, and `window.mercuryComponents.accordion.elements` is an array of all the component container elements.
