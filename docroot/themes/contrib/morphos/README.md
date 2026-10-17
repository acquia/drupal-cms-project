# Morphos Theme

Morphos is a component-based Drupal theme, providing a modern and flexible starting point for site owners to build scalable and efficient websites using [Drupal Canvas](https://www.drupal.org/project/canvas).

## Getting started

To use Morphos, you can install it via Composer, like any other Drupal theme. But Morphos is designed to be copied, rather than used as a contributed theme or base theme, and you should not assume that future updates will be compatible with your site.

To clone the theme for your site, copy it into your custom themes directory:

```shell
mkdir -p web/themes/custom
cp -R web/themes/contrib/morphos web/themes/custom/morphos
git add web/themes/custom/morphos
composer remove morpht/morphos
```

This will create a copy of Morphos in `web/themes/custom/morphos`. You can customize it in any way you see fit.

**Important:** If you are using the default content, do not rename the theme. The components are namespaced to `morphos`, and renaming the theme will break them. You should also ensure that `morpht/morphos` is removed from Composer (as shown above) so that it is not reinstalled or updated, which could conflict with your custom copy.

### Sub-theming

**Don't create your custom theme as a sub-theme of Morphos.** Morphos is designed to be copied directly (as described above), not used as a base theme. It does not provide backward compatibility, which allows us to rapidly innovate, iterate, and improve. If you create a sub-theme of Morphos, it is likely to break in the future.

## Customizing

### Custom components

Morphos uses [single-directory components](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components) and comes with a variety of commonly used components. You can add new components and modify existing ones, but be sure to rebuild the CSS when you make changes.

## Building CSS

Morphos uses [Tailwind CSS](https://tailwindcss.com) and [daisyUI](https://daisyui.com) to simplify styling by using classes to compose designs directly in the markup.

If you want to customize the Tailwind-generated CSS, install the development tooling dependencies by running the following command in your theme's directory:

```shell
npm install
```

If you modify CSS files or classes in a Twig template, you need to rebuild the CSS:

```bash
npm run build
```

For development, you can watch for changes and automatically rebuild the CSS:

```bash
npm run dev
```

## Code Formatting

Morphos uses [Prettier](https://prettier.io) to automatically format code for consistency. The project is configured with plugins for Tailwind CSS and Twig templates.

For the best experience, [set up Prettier in your editor](https://prettier.io/docs/editors) to automatically format files on save.

To format all files in the project:

```bash
npm run format
```

To check if files are formatted correctly without making changes:

```bash
npm run format:check
```

**Note**: Some files are excluded from formatting via `.prettierignore`, such as Drupal's `html.html.twig` template, which contains placeholder tokens that break Prettier's HTML parsing.
