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

  # Insert UUID as the first line of the file
  perl -pi -e "print \"uuid: ${uuid}\\n\" if \$. == 1" "${system_site_yml}"

  log_info "Set site UUID to ${GREEN}${uuid}${NC} in ${GREEN}system.site.yml${NC}"
}

# Export Drupal configuration to artifact directory
# Removes UUIDs and _core keys for cleaner, portable configs.
# Output:
#   Exported configuration files in config/default/
export_drupal_configuration() {
  # Export all configuration
  execute_drush_command cex --yes

  # For now, we will clean up UUIDs and _core keys using shell script.
  # @todo Remove after https://www.drupal.org/i/3564710 is merged and code is
  # available in `drupal_cms_helper` module and replace above command with:
  # execute_drush_command cex --generic --yes
  #====================================Start Remove============================
  local config_dir="${ARTIFACT_DIR}/config/default"

  # Remove UUID from all configs.
  log_info "Removing ${GREEN}uuid${NC} keys from configuration files"
  if [[ "$OSTYPE" == "darwin"* ]]; then
    find "${config_dir}" -type f -not -name 'canvas.folder*' -exec sed -i '' -e '/^uuid: /d' {} \;
  else
    find "${config_dir}" -type f -not -name 'canvas.folder*' -exec sed -i '/^uuid: /d' {} \;
  fi

  # Remove _core and default_config_hash from all configs.
  log_info "Removing ${GREEN}_core${NC} keys from configuration files"
  if [[ "$OSTYPE" == "darwin"* ]]; then
    find "${config_dir}" -type f -exec sed -i '' -e '/_core:/,+1d' {} \;
  else
    find "${config_dir}" -type f -exec sed -i '/_core:/,+1d' {} \;
  fi
  #====================================End Remove==============================
  log_success "Exported Drupal configuration"
}

# Export site content to artifact directory
# Uses Drush site:export, extracts content, and cleans up temporary files.
# Output:
#   Content files in content/
export_site_content() {
  # Currently there's no generic way to export only content, so we export the
  # entire site and then move the content directory to the artifact root.
  # This is not ideal but allows us to use the existing site:export command
  # without modification.

  # @todo Implement a content-only export command in drupal_cms_helper and replace
  # this logic with a direct content export once available.
  local export_temp="${ARTIFACT_DIR}/recipes/site_export"

  # Export entire site to temporary location
  execute_drush_command site:export --destination="${export_temp}"

  # Move only content to artifact root
  if [[ -d "${export_temp}/content" ]]; then
    mv "${export_temp}/content" "${ARTIFACT_DIR}/"
    log_success "Exported site content"
  else
    log_warning "No content directory found in site export"
  fi

  # Clean up temporary export directory
  rm -rf "${export_temp}"
}

# Import site content from artifact directory
# Arguments:
#   $1 - Path to content directory
# Exit codes:
#   1 if content directory doesn't exist
import_site_content() {
  local content_dir="$1"

  if [[ ! -d "${content_dir}" ]]; then
    log_error "Content directory not found: ${GREEN}${content_dir}${NC}"
    exit 1
  fi

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
