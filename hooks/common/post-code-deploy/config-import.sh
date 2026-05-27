#!/bin/sh
#
# Cloud Hook: Import config
#
# Run drush config:import in all environments post code deploy.

# Map the script inputs to convenient names.
site=$1
target_env=$2
drush_alias=$site'.'$target_env

drush @$drush_alias config:import --yes
