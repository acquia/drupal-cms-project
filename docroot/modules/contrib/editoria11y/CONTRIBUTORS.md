# Local Development
The editoria11y development team used `ddev` and the drupalspoons composer
plugin for initial development.
Next iteration will use https://github.com/ddev/ddev-drupal-contrib

## Helpful tools and tips
1. Remember to run `./scripts/get.sh` from the project root to grab the latest
copy of the library from GitHub.
2. Use an API development tool, like Postman or RESTman, to test things like the
dismiss API.

## DDEV

### Setup
1. Make sure docker is up to date.
2. [Install DDEV](https://ddev.readthedocs.io/en/stable/#macos-homebrew)
3. Create a clean directory
4. Loosely follow the [Drupal Local Development guide](https://www.drupal.org/docs/official_docs/local-development-guide), with more recent PHP than this snapshot:
```
ddev config --project-type drupal10 --create-docroot --docroot web 
--php-version 8.2
ddev start
ddev composer create drupal/recommended-project -y
ddev composer require drupal/coder
cd web/modules/
mkdir custom
cd custom
git clone git@git.drupal.org:project/editoria11y.git
cd ../../../
ddev composer require drush/drush
ddev drush site:install -y
ddev drush en editoria11y
ddev launch
```
5. In PHPStorm, go to Settings > PHP > Quality Tools > PHP_CodeSniffer and 
click the ellipses to the right of PHP configuration for the secret hidden menu
where you can set the PHPCS file path (???). Set it to wherever yours ended up,
most likely:
/Users/USERNAME/Sites/PROJECTNAME/vendor/bin/phpcs
6. Do the same for phpcbf
7. Set check files to `php,module,inc,install,test,profile,theme,css,info,txt,md,yml`
8. In another editor, open .idea and edit .idea/inspectionProfiles/Project_Default.xml with `<option name="CODING_STANDARD" value="Drupal,DrupalPractice" />`. Ref [IDE configuration](https://www.drupal.org/node/1419988#s-ide-and-editor-configuration) and [PHPCS and Drupal bugs](https://www.drupal.org/project/coder/issues/3262291#comment-15298041)
9. Then make sure you click "ON" to the left of the PHP configuration.
10. Then go to Editor > Inspections > Proofreading and tell it to get off your
    lawn.

### Use
1. Run `ddev start` from the project root.
2. Test site at `https://editoria11y.ddev.site/`.
3. Prefix drush commands with ddev (`ddev drush cr`).

Tip: DDEV supports PHP zero-conf debugging with PHPStorm. You just need to tell
PHPStorm to listen for debug with  and by running `ddev xdebug on` from the bash
prompt.
