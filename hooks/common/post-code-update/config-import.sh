#!/bin/sh
#
# Cloud Hook: Import config
#
# Run drush config:import in all environments post code deploy.

# Map the script inputs to convenient names.
site="$1"
target_env="$2"

repo_root="/var/www/html/$site.$target_env"

$repo_root/vendor/bin/drush config:import --yes
