#!/bin/sh
#
# Cloud Hook: Import config
#
# Run drush config:import in all environments post code deploy.

site="$1"
target_env="$2"

# Use vendor drush if available, otherwise fall back to drush9
drush='drush9'
if [ -e /var/www/html/vendor/bin/drush ]; then
  drush='/var/www/html/vendor/bin/drush'
fi

echo "Importing config for $site.$target_env"
$drush @$site.$target_env cim -y
