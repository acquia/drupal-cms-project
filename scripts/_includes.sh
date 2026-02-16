#!/usr/bin/env bash

#===============================================================================
# Shared Utilities and Configuration
#===============================================================================
# This file provides core functionality used across all artifact build and
# installation scripts.
#
# Usage: source "${SCRIPT_DIR}/_includes.sh"
#===============================================================================

# Resolve this script's directory even when called from elsewhere
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Fail fast on errors, undefined variables, and pipe failures
set -euo pipefail
IFS=$'\n\t'

#-------------------------------------------------------------------------------
# Path Configuration
#-------------------------------------------------------------------------------
# Base directory of the project (parent of scripts/)
PROJECT_DIR="$(dirname "${SCRIPT_DIR}")"

# Temporary artifact directory for builds
ARTIFACT_DIR="${TMPDIR:-/tmp/}artifact"

# Drush executable paths within the artifact
DRUSH="${ARTIFACT_DIR}/vendor/bin/drush"
DRUSH_PHP="${ARTIFACT_DIR}/vendor/bin/drush.php"

#-------------------------------------------------------------------------------
# Color and Formatting Configuration
#-------------------------------------------------------------------------------
# Initialize color codes and text formatting for terminal output.

BOLD='\033[1m'
RED='\033[31m'
GREEN='\033[32m'
YELLOW='\033[33m'
CYAN='\033[36m'
NC='\033[0m'
UNDERLINE="$(tput smul 2>/dev/null || echo $'\033[4m')"
NO_UNDERLINE="$(tput rmul 2>/dev/null || echo $'\033[24m')"

#-------------------------------------------------------------------------------
# Logging Functions
#-------------------------------------------------------------------------------

# Display an informational message
# Arguments:
#   $1 - Message text
# Output:
#   Formatted info message to stdout
log_info() {
  printf '%b\n' "${BOLD}${CYAN}[info]${NC} $1"
}

# Display an error message
# Arguments:
#   $1 - Error message text
# Output:
#   Formatted error message to stderr
log_error() {
  printf '%b\n' "${BOLD}${RED}[error]${NC} $1" >&2
}

# Display a success message
# Arguments:
#   $1 - Success message text
# Output:
#   Formatted success message to stdout
log_success() {
  printf '%b\n' "${BOLD}${GREEN}[success]${NC} $1"
}

# Display a warning message
# Arguments:
#   $1 - Warning message text
# Output:
#   Formatted warning message to stderr
log_warning() {
  printf '%b\n' "${BOLD}${YELLOW}[warning]${NC} $1" >&2
}

# Display a section heading
# Arguments:
#   $1 - Heading text
# Output:
#   Underlined, colored heading with newline spacing
print_heading() {
  local text="$1"
  printf '\n%b:\n' "${YELLOW}${UNDERLINE}${text}${NO_UNDERLINE}${NC}"
}

#-------------------------------------------------------------------------------
# Utility Functions
#-------------------------------------------------------------------------------

# Execute Drush commands with consistent PHP memory settings
# Arguments:
#   $@ - Drush command and arguments
# Output:
#   Drush command output
# Exit codes:
#   Propagates Drush exit code
execute_drush_command() {
  php -d memory_limit=512M "${DRUSH_PHP}" "$@"
}

# Check if a command exists in PATH
# Arguments:
#   $1 - Command name
# Returns:
#   0 if command exists, 1 otherwise
command_exists() {
  command -v "$1" >/dev/null 2>&1
}

# Verify a file exists
# Arguments:
#   $1 - File path
#   $2 - (Optional) Custom error message
# Exit codes:
#   1 if file does not exist
require_file() {
  local file="$1"
  local msg="${2:-Required file not found: ${GREEN}${file}${NC}}"

  if [[ ! -f "${file}" ]]; then
    log_error "${msg}"
    exit 1
  fi
}

#-------------------------------------------------------------------------------
# Cleanup and Trap Handlers
#-------------------------------------------------------------------------------

# Register a cleanup function to run on script exit
# Arguments:
#   $1 - Cleanup function name
register_cleanup() {
  local cleanup_fn="$1"
  trap "${cleanup_fn}" EXIT INT TERM
}
