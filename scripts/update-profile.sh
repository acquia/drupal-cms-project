#!/usr/bin/env bash
set -e

PROFILE_DIR="docroot/profiles/custom/drupal_cms_installer"
ARCHIVE_URL="https://git.drupalcode.org/project/drupal_cms/-/archive/1.x/drupal_cms-1.x.tar.gz?path=project_template/web/profiles/drupal_cms_installer"
ARCHIVE_NAME="drupal_cms_installer.tar.gz"

wget "$ARCHIVE_URL" -O "$ARCHIVE_NAME"
tar -xf "$ARCHIVE_NAME" -C docroot/profiles/custom/

rm -rf "$PROFILE_DIR"
mv docroot/profiles/custom/drupal_cms-1.x-project_template-web-profiles-drupal_cms_installer/project_template/web/profiles/drupal_cms_installer "$PROFILE_DIR"

rm -rf docroot/profiles/custom/drupal_cms-1.x-project_template-web-profiles-drupal_cms_installer
rm "$ARCHIVE_NAME"
