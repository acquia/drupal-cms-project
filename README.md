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

## Branches
This repo ships with two branches: `main` and `dist`.

### main
The `main` branch is used for development. When you're ready to deploy a feature or update, you will build an artifact from the main branch using ACLI.

### dist
The `dist` branch is a prebuilt deployment artifact. You can use it to get an application up and running quickly without building an artifact. You must generate a unique salt hash in the `dist` branch before using it. You can use the Drupal Recommended Settings provided Drush command to do this: `drush drupal:hash-salt:init`. This artifact uses the latest PHP version available on Acquia, currently 8.5.

### Workflow
#### Quick-start
Use the pre-built `dist` branch to get an application up and running quickly.
1. Check out the `dist` branch.
    ```
    $ git checkout dist
    ```
2. Generate a unique hash salt.
    ```
    $ vendor/bin/drush drupal:hash-salt:init
    ```
3. Add the unique values to git.
    ```
    $ git add -A
    $ git commit -m "Unique"
    $ git push origin dist
    ```
4. Install from the prebuilt artifact's configuration on cloud.
    ```
    $ /usr/local/bin/acli remote:drush @<YOUR_AH_SITEGROUP>.dev -- site:install --existing-config --yes
    ```
5. Import the default content provided by Drupal CMS.
    ```
    $ /usr/local/bin/acli remote:drush @<YOUR_AH_SITEGROUP>.dev -- content:import ../recipes/drupal_cms_starter/content --yes
    ```

#### Develop and deploy a feature
1. Create a feature branch from `main`
    ```
    $ git checkout -b <feature-branch-name> main
    ```
2. Do your work on the feature branch. Once it's reviewed and ready, merge it back into main.
3. Create a deployment artifact from `main`.
    ```
    $ acli push:artifact --destination-git-urls="<Your Acquia Git URK>" --destination-git-branch=dist--feature-name
    ```

## License
Copyright (C) 2026 Acquia, Inc.

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
