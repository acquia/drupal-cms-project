#!/usr/bin/env bash

#===============================================================================
# Drupal Operations
#===============================================================================
# This file provides Drupal-specific operations such as configuration export,
# content handling, UUID management, and file path replacements.
#
# Usage: source "${SCRIPT_DIR}/_drupal.sh"
#===============================================================================

# Set the site UUID in system.site.yml configuration
# Arguments:
#   $1 - UUID string
# Output:
#   Confirmation message
# Exit codes:
#   1 if UUID setting fails
set_site_uuid() {
  local uuid="$1"
  local system_site_yml="${ARTIFACT_DIR}/config/default/system.site.yml"

  require_file "${system_site_yml}"

  # Replace UUID in system.site.yml.
  sed -i.bak "s/^uuid: .*/uuid: ${uuid}/" "${system_site_yml}" && rm "${system_site_yml}.bak"

  log_info "Set site UUID to ${GREEN}${uuid}${NC} in ${GREEN}system.site.yml${NC}"
}

# Export Drupal configuration to artifact directory
# Removes UUIDs and _core keys for cleaner, portable configs.
# Output:
#   Exported configuration files in config/default/
export_drupal_configuration() {
  # Export all configuration
  execute_drush_command cex --generic --yes
  log_success "Exported Drupal configuration"
}

# Export site content to artifact directory
# Uses Drush site:export, extracts content, and cleans up temporary files.
# Output:
#   Content files in content/
export_site_content() {
  # Export all content to artifact directory
  execute_drush_command content:export:all "${ARTIFACT_DIR}/content"
}

# Import site content from artifact directory
# Arguments:
#   $1 - Path to content directory
# Exit codes:
#   1 if content directory doesn't exist
import_site_content() {
  local content_dir="$1"
  execute_drush_command content:import "${content_dir}"
  log_success "Imported site content"
}

# Generate a new hash salt for the Drupal site
# Output:
#   New salt.txt file in artifact root
generate_hash_salt() {
  execute_drush_command drupal:hash-salt:init
  log_success "Generated new hash salt"
}

# Install Drupal site with existing configuration
# Arguments:
#   $@ - Additional drush site:install flags (optional)
install_site_with_config() {
  execute_drush_command site:install \
    --existing-config \
    --yes \
    "$@"

  log_success "Installed site with existing configuration"
}

# Install a fresh Drupal site
# Arguments:
#   $1 - Site name
#   $2 - Admin username
#   $3 - Admin password
#   $4 - Site email
#   $5 - Site URI (default: "default")
install_fresh_site() {
  local site_name="$1"
  local admin_user="${2:-admin}"
  local admin_pass="${3:-admin}"
  local site_mail="${4:-admin@example.com}"
  local site_uri="${5:-default}"

  execute_drush_command site:install \
    --site-name="${site_name}" \
    --account-name="${admin_user}" \
    --account-pass="${admin_pass}" \
    --site-mail="${site_mail}" \
    --uri="${site_uri}" \
    --yes

  log_success "Installed fresh Drupal site: ${GREEN}${site_name}${NC}"
}
