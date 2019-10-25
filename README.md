![Kinobi logo](https://github.com/mondada/kinobi/blob/master/docs/images/kinobi.png)

# Kinobi

Kinobi is an external patch server (or *patch source*) for Jamf Pro. It provides a simple interface for creating and editing patch definitions, as well as the appropriate endpoints for Jamf Pro to connect to.

![Kinobi screenshot](https://github.com/mondada/kinobi/blob/master/docs/images/kinobi_screenshot.png)

## Downloading

The latest release of Kinobi is available as a **.run** package via the [Releases](https://github.com/mondada/kinobi/releases) page.

## Documentation

For full documentation and installation guide, please see the [Kinobi Knowledge Base](http://docs.kinobi.io).

## Requirements
### Standalone
Kinobi 1.2 or later, can run as a stand-alone installation.
Supported operating systems:
* Ubuntu LTS Server 14.04 or later (18.04 recommended)
* Red Hat Enterprise Linux (RHEL) 6.4 or later
* CentOS 6.4 or later

System requirements:
* 20 GB of disk space available
* 1 GB of RAM

Optional:
* MySQL Community Server 5.6 or later, for MySQL database support

### NetSUS
Kinobi may also be installed on [NetSUS 4.1.0 or later](https://github.com/jamf/NetSUS). Refer to NetSUS [system requirements](https://github.com/jamf/NetSUS#requirements) for details.

**NetSUS 5 is recommended. If you are running NetSUS 5.0 or later, you need to install Kinobi 1.1 or later.**

## Migrating from NetSUS
**Kinobi stores the user accounts in its database, when the database is restored from an earlier version, no user's will exist**
Kinobi 1.2 has a new backup format and database schema, as such to migrate from NetSUS to a Standalone installation, a few additional steps are required.
Migration steps:
* Perform a backup of the database, and download it (this may be required for rollback)
* Upgrade the Kinobi installation on NetSUS to version 1.2
* Perform a fresh backup (in the new format), download this file (sql.gz)
* Install Kinobi on a new system
* Perform an initial setup
* Go to Settings > Restore
* Upload the backup in the new format
* Restore the backup, and log out
* Re-run the setup assistant and re-create the new user account

## Getting Help
Discussion regarding Kinobi can be found on the `#kinobi-dev` channel on the [MacAdmins](https://macadmins.herokuapp.com) Slack group.

Issues can be filed [directly on GitHub](https://github.com/mondada/kinobi/issues), but **please ensure the issue has not already been reported** before doing so.
