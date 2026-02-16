#!/usr/bin/env bash

#===============================================================================
# Build Artifact Script
#===============================================================================
# Creates a production-ready Drupal artifact by:
# 1. Copying project files to a clean artifact directory
# 2. Installing production dependencies
# 3. Installing Drupal and exporting configuration/content
# 4. Cleaning up development files and creating deployment artifact.
#
# Usage: bash scripts/build-artifact.sh
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
    log_error "Build failed. Artifact directory may be incomplete: ${GREEN}${ARTIFACT_DIR}${NC}"
  fi
}

register_cleanup cleanup_on_failure

#-------------------------------------------------------------------------------
# Main Build Process
#-------------------------------------------------------------------------------

main() {
  # Validate system dependencies first
  validate_system_dependencies

  # Create and prepare artifact directory
  print_heading "Preparing artifact directory"
  mkdir -p "${ARTIFACT_DIR}"
  chmod 755 "${ARTIFACT_DIR}"
  log_info "Created artifact directory at ${GREEN}${ARTIFACT_DIR}${NC}"

  # Copy project files to artifact (excluding VCS and scripts)
  print_heading "Copying project files to artifact"
  if rsync -a \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='scripts' \
    "${PROJECT_DIR}/" "${ARTIFACT_DIR}"; then
    log_success "Project files copied successfully"
  else
    log_error "Failed to copy project files"
    exit 1
  fi

  # Switch to artifact directory for all operations
  cd "${ARTIFACT_DIR}" || exit 1

  # Install production dependencies
  print_heading "Installing production dependencies"
  if composer install --no-dev --prefer-dist --optimize-autoloader; then
    log_success "Dependencies downloaded successfully"
  else
    log_error "Composer install failed"
    exit 1
  fi

  # Updates the private file path configuration for CI environment.
  # For now we are using script to set correct private files directory path
  # for CI env.
  # @todo Remove after https://acquia.atlassian.net/browse/ONR-30 is fixed.
  #====================================Start Remove============================
  print_heading "Updating private file path configuration"
  local settings_file="vendor/acquia/drupal-recommended-settings/settings/ci.settings.php"

  # Replace hardcoded private path with dynamic EnvironmentDetector call
  perl -0777 -i -pe 's/\$dir = dirname\(DRUPAL_ROOT\);\n\$settings\[\x27file_private_path\x27\] = \$dir \. \x27\/files-private\x27;\n/\$settings[\x27file_private_path\x27] = \\Acquia\\Drupal\\RecommendedSettings\\Helpers\\EnvironmentDetector::getRepoRoot\(\) . \x27\/files-private\/\x27 . \\Acquia\\Drupal\\RecommendedSettings\\Helpers\\EnvironmentDetector::getSiteName\(\$site_path\);\n/g' \
    "${ARTIFACT_DIR}/${settings_file}"

  # Verify the replacement succeeded
  if ! grep -q "EnvironmentDetector::getRepoRoot" "${ARTIFACT_DIR}/${settings_file}"; then
    log_error "Failed to replace private file path in ${GREEN}${settings_file}${NC}"
    exit 1
  fi
  log_success "Updated private file path configuration in ${GREEN}${settings_file}${NC}"
  #====================================End Remove============================

  # Install Drupal to generate exportable config and content
  print_heading "Installing Drupal CMS"
  install_fresh_site "Drupal CMS"

  # Reinstall dependencies to ensure clean state
  print_heading "Re-installing dependencies"
  composer install --no-dev --prefer-dist --optimize-autoloader

  # Validate critical .htaccess files exist
  print_heading "Validating file structure"
  validate_htaccess_files

  # Reset site UUID for generic config export
  print_heading "Preparing configuration for export"
  execute_drush_command cset system.site uuid 'NULL' --yes
  log_info "Set site UUID to NULL for generic config export"

  # Export configuration and content
  print_heading "Exporting Drupal configuration"
  export_drupal_configuration

  print_heading "Exporting site content"
  export_site_content

  # Clean up artifact for deployment
  print_heading "Sanitizing artifact for deployment"
  sanitize_artifact_for_deployment

  print_heading "Build Complete"
  log_success "Artifact ready at ${GREEN}${ARTIFACT_DIR}${NC}"
  log_info "Review the artifact before deployment"
}

#-------------------------------------------------------------------------------
# Artifact Cleanup Functions
#-------------------------------------------------------------------------------

# Remove development files and rebuild .gitignore for deployment
sanitize_artifact_for_deployment() {
  # Remove salt and existing .gitignore
  rm -f "${ARTIFACT_DIR}/salt.txt" "${ARTIFACT_DIR}/.gitignore"

  # Remove all .gitignore files from docroot, vendor and recipes folder.
  find "${ARTIFACT_DIR}/docroot" "${ARTIFACT_DIR}/recipes" "${ARTIFACT_DIR}/vendor" \
    -type f -name '.gitignore' -delete
  log_success "Removed .gitignore files from docroot, recipes, and vendor"

  # Remove Drupal core text files under docroot/core (except LICENSE.txt)
  find "${ARTIFACT_DIR}/docroot/core" -type f -name "*.txt" ! -name "LICENSE.txt" \
    -delete
  log_info "Removed text files from docroot/core"

  # Remove any nested .git directories
  find "${ARTIFACT_DIR}/docroot" "${ARTIFACT_DIR}/vendor" "${ARTIFACT_DIR}/recipes" \
    -type d -name ".git" -prune -exec rm -rf {} \;
  log_info "Removed nested .git directories"

  # Remove common documentation files from docroot
  find "${ARTIFACT_DIR}/docroot" -type f \( \
    -name "AUTHORS.md" -o -name "AUTHORS.txt" -o \
    -name "CHANGELOG.md" -o -name "CHANGELOG.txt" -o \
    -name "CONDUCT.md" -o -name "CONDUCT.txt" -o \
    -name "CONTRIBUTING.md" -o -name "CONTRIBUTING.txt" -o \
    -name "INSTALL.md" -o -name "INSTALL.txt" -o \
    -name "MAINTAINERS.md" -o -name "MAINTAINERS.txt" -o \
    -name "PATCHES.md" -o -name "PATCHES.txt" -o \
    -name "TESTING.md" -o -name "TESTING.txt" -o \
    -name "UPDATE.md" -o -name "UPDATE.txt" \
  \) -delete
  log_info "Removed documentation files from docroot"

  # Create deployment-specific .gitignore
  create_deployment_gitignore

  log_success "Artifact cleaned for deployment"
}

# Create a deployment-specific .gitignore file
# This ensures file directories are tracked but their contents are ignored,
# except for critical .htaccess files.
create_deployment_gitignore() {
  cat > "${ARTIFACT_DIR}/.gitignore" << 'EOF'
# Deployment Artifact .gitignore
# This file is automatically generated during artifact builds.

# Include files directories but ignore their contents except .htaccess
docroot/sites/*/files/*
!docroot/sites/*/files/.htaccess
files-private/default/*
!files-private/default/.htaccess

# Exclude example and development files
docroot/sites/*/example.*
docroot/*lint*
docroot/*example*
docroot/example.gitignore
docroot/sites/*.settings.local*

# Include critical core files
!docroot/.htaccess
!docroot/.ht.router.php
!docroot/robots.txt
!docroot/update.php

# This .gitignore should not be in the deployed artifact
.gitignore
EOF

  log_info "Created deployment .gitignore"
}

# Run main build process
main
