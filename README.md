# Drupal CMS - Acquia Hosting Tailored Version
Project template for building [Drupal CMS](https://drupal.org/drupal-cms) tailored for Acquia hosting. This project follows the official Drupal CMS releases and includes Acquia-specific additions and integrations.

## Example workflow using [Acquia CLI](https://docs.acquia.com/acquia-cloud-platform/add-ons/acquia-cli/install)
1. Create project
   ```
   composer create-project acquia/drupal-cms-project
   ```

2. Initialize repo and commit
   ```
   cd drupal-cms-project && git init && git add -A && git commit -m "initial build"
   ```

3. Build artifact and push to cloud
   ```
   /usr/local/bin/acli push:artifact --destination-git-urls=<YOUR_ACQUIA_GIT_REPO_URL> --destination-git-branch=dist --quiet
   ```

4. Checkout the new branch on cloud
   ```
   /usr/local/bin/acli app:task-wait "$(/usr/local/bin/acli api:environments:code-switch <YOUR_AH_SITEGROUP>.dev dist)"
   ```

5. Drop database on cloud if you have previously installed a site and want to see the Drupal CMS installer
   ```
   /usr/local/bin/acli remote:drush @<YOUR_AH_SITEGROUP>.dev sql:drop
   ```

6. Visit your site!

## License
Copyright (C) 2026 Acquia, Inc.

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
