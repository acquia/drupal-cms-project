#!/bin/sh
#
# Cloud Hook: Import config
#
# Run drush config:import in all environments post code deploy.

site=$1
target_env=$2

echo "Importing config for $site.$target_env"
./vendor/bin/drush @$site.$target_env config:import -y
