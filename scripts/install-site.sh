#!/usr/bin/env bash

#===============================================================================
# Install Site Script
#===============================================================================
# Installs a Drupal site from an existing artifact with configuration and
# content by:
# 1. Validating prerequisites and dependencies
# 2. Generating a new site UUID
# 3. Creating a hash salt
# 4. Installing with existing configuration
# 5. Importing site content
# 6. Verifying the installation
#
# Usage: bash scripts/install-site.sh
#===============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/_includes.sh"
source "${SCRIPT_DIR}/_validation.sh"
source "${SCRIPT_DIR}/_drupal.sh"

#-------------------------------------------------------------------------------
# Cleanup Handler
#-------------------------------------------------------------------------------

cleanup_on_failure() {
  if [[ $? -ne 0 ]]; then
    log_error "Installation failed. Check error messages above."
  fi
}

register_cleanup cleanup_on_failure

#-------------------------------------------------------------------------------
# Main Installation Process
#-------------------------------------------------------------------------------

main() {
  # Switch to artifact directory
  cd "${ARTIFACT_DIR}" || {
    log_error "Artifact directory not found: ${GREEN}${ARTIFACT_DIR}${NC}"
    log_info "Run build-artifact.sh first to create the artifact."
    exit 1
  }

  # Validate all prerequisites
  print_heading "Validating prerequisites"
  validate_drush_executable
  validate_installation_prerequisites

  # Generate and set a new site UUID
  print_heading "Configuring site UUID"
  local uuid
  uuid="$(uuidgen)"
  set_site_uuid "${uuid}"

  # Generate hash salt for security
  print_heading "Generating hash salt"
  generate_hash_salt

  # Install Drupal with existing configuration
  print_heading "Installing Drupal site from existing configurations"
  install_site_with_config

  # Import content from artifact
  print_heading "Importing site content"
  import_site_content "${ARTIFACT_DIR}/content"

  # Verify installation status
  print_heading "Verifying installation"
  verify_installation

  print_heading "Installation Complete"
  log_success "Site installed successfully"
}

#-------------------------------------------------------------------------------
# Installation Verification
#-------------------------------------------------------------------------------

# Check Drupal watchdog logs for critical errors (currently disabled)
# This function is commented out until core installation errors are resolved.
verify_drupal_log_messages() {
  local log_output
  log_output=$(execute_drush_command ws --severity-min=4 2>&1)

  if ! echo "${log_output}" | grep -q "No log messages available"; then
    log_warning "Errors found in Drupal logs after installation:"
    echo "${log_output}"
    exit 1
  else
    log_success "No critical errors in Drupal logs"
  fi
}


# Verify that the site is installed and working
verify_installation() {
  if ! execute_drush_command status; then
    log_error "Drush status check failed"
    exit 1
  fi

  # Currently installation from existing config generates expected errors that
  # would cause this check to fail incorrectly.
  # @todo Re-enable log message check; after https://www.drupal.org/i/3564735 is fixed.
  # verify_drupal_log_messages
}
# Run main installation process
main
