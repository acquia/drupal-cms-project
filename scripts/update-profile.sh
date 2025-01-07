#!/usr/bin/env bash
wget https://git.drupalcode.org/project/drupal_cms/-/archive/1.x/drupal_cms-1.x.tar.gz -O drupal_cms_installer.tar.gz
tar -xf drupal_cms_installer.tar.gz -C docroot/profiles/custom/
rm -rf docroot/profiles/custom/drupal_cms_installer
mv docroot/profiles/custom/drupal_cms-1.x docroot/profiles/custom/drupal_cms_installer
rm drupal_cms_installer.tar.gz
