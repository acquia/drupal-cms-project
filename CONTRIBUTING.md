# Contributing to Drupal CMS - Acquia Hosting Tailored Version

## External Contributions

Like all open source software, you are permitted and encouraged to extend this project as much as you like - via version control and build systems you maintain.

We accept suggestions for the project through our Github issue queue. You may submit suggestions through our issue queue on Drupal.org.

## Internal Acquia Contributions

Contributing to this project requires the ability to push branches to the repository, since we can not use forks. If you need access, speak to Acquia engineering leaders of this project.

git checkout develop
git pull
git checkout -b ACMS-123/event-content-type
Once the branch is open, you can make as many commits to it as you like. All commit messages must be prefixed by the ticket number. For example: ACMS-123: Add the event content type (note the lack of a period at the end of the commit message) is a good example of a commit message.

When you're ready for your work to be reviewed, open a pull request to merge your branch into the develop branch. You should also periodically sync and rebase against develop.
