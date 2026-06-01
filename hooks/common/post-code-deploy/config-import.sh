#!/bin/sh
#
# Cloud Hook: Import config
#
# Run drush config:import in all environments post code deploy.

site=$1
target_env=$2
repo_root="/var/www/html"

echo "Current working directory: $(pwd)"
echo "Importing config for $site.$target_env"

$repo_root/vendor/bin/drush @$site.$target_env config:import -y
