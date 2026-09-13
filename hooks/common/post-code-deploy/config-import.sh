#!/bin/sh
#
# Cloud Hook: Import config
#
# Run drush config:import in all environments post code deploy.

# Map the script inputs to convenient names.
site=$1
target_env=$2
drush_alias=$site'.'$target_env

if drush @$drush_alias status 2>/dev/null | grep -q "Successful"; then
  # Always run database updates if Drupal is installed.
  drush @$drush_alias updatedb --yes

  config_dir=$(drush @$drush_alias core:status --field=config-sync 2>/dev/null)
  if [ -n "$config_dir" ] && find "$config_dir" -name "*.yml" -print -quit 2>/dev/null | grep -q .; then
    # Drush will give a non-zero exit code if you try to import an empty config directory. Only run config imports if
    # the configured directory contains config files.
    drush @$drush_alias config:import --yes
  fi
fi
