#!/bin/bash
set -vo pipefail

DRUPAL_RECOMMENDED_PROJECT=${DRUPAL_RECOMMENDED_PROJECT:-11.x-dev}
PHP_EXTENSIONS="gd"
DRUPAL_CHECK_TOOL="mglaman/drupal-check"

# Install required PHP extensions
for ext in $PHP_EXTENSIONS; do
  if ! php -m | grep -q $ext; then
    apk update && apk add --no-cache ${ext}-dev
    docker-php-ext-install $ext
  fi
done

# Create Drupal project if it doesn't exist
if [ ! -d "/drupal" ]; then
  composer create-project drupal/recommended-project=$DRUPAL_RECOMMENDED_PROJECT drupal --no-interaction --stability=dev
fi

cd drupal
mkdir -p web/modules/contrib/

# Symlink analyze_ai_brand_voice if not already linked
if [ ! -L "web/modules/contrib/analyze_ai_brand_voice" ]; then
  ln -s /src web/modules/contrib/analyze_ai_brand_voice
fi

# Install the statistic modules if D11 (removed from core).
if [[ $DRUPAL_RECOMMENDED_PROJECT == 11.* ]]; then
  composer require drupal/statistics
fi

# Install drupal-check
composer require $DRUPAL_CHECK_TOOL --dev

# Run drupal-check
./vendor/bin/drupal-check --drupal-root . -ad web/modules/contrib/analyze_ai_brand_voice 