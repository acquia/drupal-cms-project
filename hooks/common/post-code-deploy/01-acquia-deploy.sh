#!/bin/bash
# Acquia Cloud Hook: post-code-deploy
# Runs database updates, imports configuration, and rebuilds cache after each code deploy.
# Arguments provided by Acquia Cloud: site target_env source_branch deployed_tag repo_url repo_type

site=$1
target_env=$2
drush_alias="${site}.${target_env}"

echo "--- post-code-deploy: ${drush_alias} ---"

# Run pending database updates (entity schema, module updates).
./vendor/bin/drush @"${drush_alias}" updatedb --yes

# Import configuration with active config splits (environment detected via AH_SITE_ENVIRONMENT in settings.php).
./vendor/bin/drush @"${drush_alias}" config-import --yes

# Rebuild cache after config import.
./vendor/bin/drush @"${drush_alias}" cache-rebuild

echo "--- post-code-deploy: complete ---"
