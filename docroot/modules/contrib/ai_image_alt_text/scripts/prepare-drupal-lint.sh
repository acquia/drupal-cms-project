#!/bin/bash
set -e

if [ -z "$TARGET_DRUPAL_CORE_VERSION" ]; then
  # default to target Drupal 11, you can override this by setting the secrets value on your github repo
  TARGET_DRUPAL_CORE_VERSION=11
fi

echo "php --version"
php --version
echo "composer --version"
composer --version

echo "\$COMPOSER_HOME: $COMPOSER_HOME"
echo "TARGET_DRUPAL_CORE_VERSION: $TARGET_DRUPAL_CORE_VERSION"

# Add this line to avoid the plugin prompt
composer config --global allow-plugins.dealerdirect/phpcodesniffer-composer-installer true

composer global require drupal/coder --dev
composer global require phpcompatibility/php-compatibility --dev

export PATH="$PATH:$COMPOSER_HOME/vendor/bin"

composer global require dealerdirect/phpcodesniffer-composer-installer --dev

composer global show -P
phpcs -i

phpcs --config-set colors 1
phpcs --config-set drupal_core_version 11$TARGET_DRUPAL_CORE_VERSION

phpcs --config-show 