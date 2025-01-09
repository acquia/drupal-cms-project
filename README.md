# Acquia Drupal CMS Project
Project template for building Drupal CMS tailored for Acquia hosting.

## Example workflow using [Acquia CLI](https://docs.acquia.com/acquia-cloud-platform/add-ons/acquia-cli/install)
1. Clone repo
   ```
   git clone git@github.com:acquia/drupal-cms-project.git
   ```

2. Build codebase
   ```
   cd drupal-cms-project && composer install
   ```

3. Commit changes from building codebase
   ```
   git add -A && git commit -m "initial build"
   ```

4. Build artifact and push to cloud
   ```
   /usr/local/bin/acli push:artifact --destination-git-urls=<YOUR_ACQUIA_GIT_REPO_URL> --destination-git-branch=artifact--dcms-scaffold --quiet
   ```

5. Checkout the new branch on cloud
   ```
   /usr/local/bin/acli app:task-wait "$(/usr/local/bin/acli api:environments:code-switch <YOUR_AH_SITEGROUP>.dev artifact--dcms-scaffold)"
   ```

6. Drop database on cloud if you have previously installed a site and want to see the Drupal CMS installer
   ```
   /usr/local/bin/acli remote:drush @<YOUR_AH_SITEGROUP>.dev sql:drop
   ```

7. Visit your site!

# License
Copyright (C) 2025 Acquia, Inc.

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
