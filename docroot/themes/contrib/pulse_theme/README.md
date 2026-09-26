# Setup Drupal CMS

## Install DDEV
The following instructions to setup DDEV for working with Drupal links to the DDEV documentation.

1. **Check requirements**
Confirm that your system meets the requirements listed in the [DDEV system requirements](https://ddev.readthedocs.io/en/stable/).

2. **Install Docker**
Go to [Docker installation](https://ddev.readthedocs.io/en/stable/users/install/docker-installation/), select your environment and follow the instructions. If [installing DDEV on Docker Engine on WSL2 on Windows](https://ddev.readthedocs.io/en/stable/), the DDEV installation script will handle Docker installation.

3. **Install DDEV**
Go to [Docker installation](https://ddev.readthedocs.io/en/stable/users/install/docker-installation/), select your environment and follow the instructions.

## Install Drupal CMS

To install Drupal CMS follow the instructions at https://docs.ddev.com/en/stable/users/quickstart/#drupal-drupal-cms

## Check in Drupal UI

1. In the toolbar, click **"Drupal Canvas"** and start by creating your first Canvas Page.
2. Check the components those are available at _site_domain/admin/appearance/component
3. If it shows up without problems, then all good for now.

# Setup theme
* From terminal navigate to `cd web/themes/` and create custom directory `mkdir contrib`
* Now clone repository inside `contrib` directory
  * `cd contrib` then run `git clone git@git.drupal.org:project/pulse_theme.git`
* Enable the theme `ddev drush theme:enable pulse_theme`
* Set the theme as default `ddev drush config:set system.theme default pulse_theme -y`
* Navigate to `web/themes/contrib/pulse_theme` theme directory and run below command:
  * `npm install` (Node version should be: v20.11.0 or > v20.11.0)
  * `npm run build` which will generate CSS files inside each components directory from `.pcss` file if exists.
  * `npm run watch` continues watch of `.pcss` file and compile into `.css` file. (Mostly required at the time of development)
  * `npm run dev:storybook` start storybook. (Mostly required at the time of development) 
  Note - Right now storybook is WIP(work in progress) state so you might face some issues.
* Clear cache once `ddev drush cr`
* Use `ddev launch` to check the site OR use `ddev drush uli` to login into Drupal dashboard.
* On Canvas, components that begin with the name **"Space"** are part of the space design system. For instance, the _Space Image Card_ is ready for use! 🥳
