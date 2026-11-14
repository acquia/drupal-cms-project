#!/bin/bash

source scripts/prepare-drupal-lint.sh

phpcbf --standard=Drupal \
  --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml \
  --ignore=node_modules,ai_image_alt_text/vendor,.github,vendor \
  .

phpcbf --standard=DrupalPractice \
  --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml \
  --ignore=node_modules,ai_image_alt_text/vendor,.github,vendor \
  . 
