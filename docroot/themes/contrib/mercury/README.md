# Mercury Theme

Mercury is a component-based Drupal theme, providing a modern and flexible starting point for site owners to build scalable and efficient websites using [Drupal Canvas](/project/canvas).

## Customization

See [CUSTOMIZING.md](CUSTOMIZING.md) for detailed instructions on how to customize this theme, including fonts, colors, components, and JavaScript.

## Known issues

Canvas's code components are currently not compatible with Tailwind-based themes like Mercury, and creating a code component will break Mercury's styling. This will be fixed in [#3549628], but for now, here's how to work around it:

1. In Canvas's in-browser code editor, open the Global CSS tab.
2. Paste the contents of your custom theme's `theme.css` into the code editor. It must be at the top.
3. Paste the contents of your custom theme's `main.css` into the code editor, removing all the `@import` statements at the top first. It must come _after_ the contents of `theme.css`.
4. Save the global CSS.

## Getting help

If you have trouble or questions, please [visit the issue queue](https://www.drupal.org/project/issues/mercury?categories=All) or find us on [Drupal Slack](https://www.drupal.org/community/contributor-guide/reference-information/talk/tools/slack), in the `#drupal-cms-support` channel.

## Roadmap

Mercury is under active development. Planned improvements include more components, better customization options, and [Storybook support](https://www.drupal.org/project/mercury/issues/3562711). If you want to contribute to Mercury, check out the `#drupal-cms-development` channel in Drupal Slack.
