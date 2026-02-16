#!/usr/bin/env bash

#===============================================================================
# Validation Functions
#===============================================================================
# This file provides validation utilities.
#
# Usage: source "${SCRIPT_DIR}/_validation.sh"
#===============================================================================

# Validate that Drush is executable and available
# Exit codes:
#   1 if Drush is not found or not executable
validate_drush_executable() {
  if [[ ! -x "${DRUSH}" ]]; then
    log_error "Drush not found or not executable at ${GREEN}${DRUSH}${NC}."
    log_info "Ensure dependencies are installed with 'composer install'."
    exit 1
  fi
}

# Validate that required .htaccess files exist in file directories
# This ensures proper security configuration for public and private files.
# Exit codes:
#   1 if any required .htaccess file is missing
validate_htaccess_files() {
  local private_htaccess="${ARTIFACT_DIR}/files-private/default/.htaccess"
  local public_htaccess="${ARTIFACT_DIR}/docroot/sites/default/files/.htaccess"

  if [[ ! -f "${private_htaccess}" ]]; then
    log_error "Missing .htaccess in private files directory: ${GREEN}${private_htaccess}${NC}"
    exit 1
  fi

  if [[ ! -f "${public_htaccess}" ]]; then
    log_error "Missing .htaccess in public files directory: ${GREEN}${public_htaccess}${NC}"
    exit 1
  fi

  log_success "Validated .htaccess files in file directories"
}

# Validate prerequisites before site installation
# Checks for files and required configuration.
# Exit codes:
#   1 if validation fails
validate_installation_prerequisites() {
  # Salt file should not exist before installation
  if [[ -f "${ARTIFACT_DIR}/salt.txt" ]]; then
    log_error "File ${GREEN}salt.txt${NC} already exists at ${GREEN}${ARTIFACT_DIR}${NC}."
    log_info "Remove it before running installation."
    exit 1
  fi

  # System site configuration must exist
  local system_site_yml="${ARTIFACT_DIR}/config/default/system.site.yml"
  if [[ ! -f "${system_site_yml}" ]]; then
    log_error "Missing ${GREEN}system.site.yml${NC} at ${GREEN}${system_site_yml}${NC}"
    exit 1
  fi

  # UUID must be null or not exist.
  # This ensures a new UUID is generated during installation rather than
  # using a pre-set value.
  local uuid_value
  uuid_value=$(awk -F': ' '/^uuid: /{print $2; exit}' "${system_site_yml}" 2>/dev/null || echo "")

  if [[ -n "${uuid_value}" && "${uuid_value}" != "null" ]]; then
    log_error "Site UUID must be null or absent in ${GREEN}system.site.yml${NC}, found: ${RED}${uuid_value}${NC}"
    log_info "Run 'drush cset system.site uuid NULL --yes' to reset it."
    exit 1
  fi

  log_success "Installation prerequisites validated"
}

# Validate that required dependencies are installed
# Checks for composer, drush, perl, rsync, and uuidgen.
# Exit codes:
#   1 if any required command is missing
validate_system_dependencies() {
  local required_commands=("composer" "perl" "rsync" "uuidgen")
  local missing_commands=()

  for cmd in "${required_commands[@]}"; do
    if ! command_exists "${cmd}"; then
      missing_commands+=("${cmd}")
    fi
  done

  if [[ ${#missing_commands[@]} -gt 0 ]]; then
    log_error "Missing required commands: ${RED}${missing_commands[*]}${NC}"
    log_info "Install missing dependencies and try again."
    exit 1
  fi

  log_success "All system dependencies are available"
}
