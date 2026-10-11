#!/bin/bash
source scripts/prepare-drupal-lint.sh

EXIT_CODE=0

echo "---- Checking with PHPCompatibility PHP 8.3 and up ----"
phpcs --standard=PHPCompatibility \
  --runtime-set testVersion 8.3- \
  --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml \
  --ignore=node_modules,ai_image_alt_text/vendor,.github,vendor \
  -v \
  .
status=$?
if [ $status -ne 0 ]; then
  EXIT_CODE=$status
fi

echo "---- Checking with Drupal standard... ----"
phpcs --standard=Drupal \
  --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml \
  --ignore=node_modules,ai_image_alt_text/vendor,.github,vendor \
  -v \
  .
status=$?
if [ $status -ne 0 ]; then
  EXIT_CODE=$status
fi

echo "---- Checking with DrupalPractice standard... ----"
phpcs --standard=DrupalPractice \
  --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml \
  --ignore=node_modules,ai_image_alt_text/vendor,.github,vendor \
  -v \
  .

status=$?
if [ $status -ne 0 ]; then
  EXIT_CODE=$status
fi

# Exit with failure if any of the checks failed
exit $EXIT_CODE 
