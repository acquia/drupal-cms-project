# Drupical

Drupical is a Drupal module that displays upcoming Drupal community events from
the official Drupal.org events feed directly on your Drupal dashboard or any
page on your site.

It provides a clean, modern interface for browsing DrupalCons, Camps, training
sessions, and other community events, helping site administrators and community
members stay informed about upcoming Drupal events worldwide.

For a full description of the module, visit the 
[project page](https://www.drupal.org/project/drupical).  
Submit bug reports and feature suggestions in the  
[issue queue](https://www.drupal.org/project/issues/drupical?component=drupical+module).

## Table of contents

- Introduction
- Features
- Requirements
- Recommended modules
- Installation
- Configuration
- Troubleshooting
- FAQ
- Maintainers
- Supporting Organization 

## Introduction

Drupical fetches and displays upcoming Drupal community events from the official
Drupal.org events feed at  
[Drupal.org Events API](https://www.drupal.org/api-d7/node.json?type=event).

The module can be used on the Drupal dashboard or embedded on any page, allowing
Drupal site administrators and community members to stay up to date with events
around the globe.

## Features

- **Automatic event fetching**  
  Pulls the latest events directly from the Drupal.org API.

- **Smart caching**  
  Efficiently caches event data with configurable (in config) expiration times to minimize
  external requests.

- **Load more**  
  AJAX-powered pagination for browsing additional events without reloading the
  page.

- **Permission control**  
  Configurable access permissions for different user roles.

- **Dashboard integration**  
  Seamlessly integrates with the Drupal administrative [dashboard](https://www.drupal.org/project/dashboard) which is used in Drupal CMS.

## Requirements

This module requires no modules outside of Drupal core.

## Recommended modules

- [Dashboard](https://www.drupal.org/project/dashboard) for a centralized interface to access key information and essential tools after logging into the system

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see  
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

- No configuration screen yet
- Define access permissions at the `/admin/people/permissions/module/drupical`
- Cache duration or display-related settings are only configurable in the config file
- Place the "Events Feed" wherever you want your users to spot it

## Troubleshooting

Please report problems over at the moulde 
[issue queue](https://www.drupal.org/project/issues/drupical?component=drupical+module)
- API requests fail or return empty results
please check if [Drupal.org Events API](https://www.drupal.org/api-d7/node.json?type=event) 
is accessible from your server.

## FAQ

**Q: Where does Drupical get its event data from?**  
**A:** Drupical uses the official Drupal.org Events API.

**Q: Can I limit which events are displayed?**  
**A:** Currently not. We have this as a feature request and will work on it, 
to have a config page for the block to filter event types.

## Maintainers
Dejan Lacmanovic ([dejan0](https://www.drupal.org/u/dejan0))
Nico Grienauer ([grienauer](https://www.drupal.org/u/grienauer))

## Supporting Organization 
by ACOLONO GmbH  

[mail](mailto:hello@acolono.com)
[chat](https://drupal.slack.com/archives/C0A45Q52CUB)
[drupal](https://www.drupal.org/acolono-gmbh)  
[www](https://www.acolono.com)

<pre>
  ┌───────┐
  │       │
  │  A:O  │  ACOLONO.com
  │       │
  └───────┘
</pre>