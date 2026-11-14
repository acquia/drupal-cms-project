# Customizing Eureka
**Do not subtheme Eureka.** It is tightly coupled to the Archimedes site template and does not provide backwards compatibility.

## Fonts & Colors
To change the fonts or colors, copy the `eureka/components/00-base/colours.css` and `eureka/components/00-base/variables.css` files to your web root, so that `colours.css` and `variables.css` are sitting next to `index.php`. Commit them to your Git repository, and clear Drupal's cache. You can customize these files however you like; changes will be reflected immediately on your site.

If you want to make deeper customizations, you will need to convert Eureka Theme to a custom theme with the same machine name. You can do this by running the following at the command line from your Drupal project root (assuming `web` is the web root):

```sh
mkdir -p web/themes/custom
cp -R web/themes/contrib/eureka web/themes/custom/eureka
git add web/themes/custom/eureka
composer remove drupal/eureka
```

Finally, clear Drupal's cache (via the UI, or `drush cache:rebuild`).

## Custom Components
Eureka Theme uses [single-directory](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components) components and comes with a variety of commonly used components. You can add new components and modify existing ones, but be sure to rebuild the CSS when you make changes.

## Building CSS
Eureka uses CSS for its styling, which is compiled and minified using a simple pnpm task. Running `pnpm build` will minify any assets. 

SVG Icons are pressed down into a single sprite file: any changes (e.g. additions / edits) to source files should be made in the `images/icons` folder. Running `pnpm svg` will update these assets. 
