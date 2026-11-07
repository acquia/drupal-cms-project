# How Recipe Interface Translation Integration works

[Why track recipes at all](why.md) answers the two objections that come up
first.

The module has three parts. Each has its own page, which says what the part
does, why it is needed, and why it cannot be left out.

1. [Recording applied recipes](recording-recipes.md): what is stored when a
   recipe is applied, and where the version comes from.
2. [Recipes as translation projects](locale-projects.md): how the recorded
   recipes get into Locale's project list, and what Locale then does with them.
3. [The configuration recipes ship](shipped-config.md): why Locale needs a copy
   of that configuration, what is stored, and how Locale gets it.

The short version: Locale knows modules and themes. It finds their name and
version in their info file, and the original copy of their configuration in
their `config/install` directory. Recipes have neither. This module records
both things at the moment a recipe is applied, when they are still there, and
keeps them in configuration, so they are exported and deployed with the site.
