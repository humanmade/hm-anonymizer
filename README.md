humanmade/hm-anonymizer
=======================

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

When setting up a project locally, it is common to take a copy of the production database. Whilst this provides an accurate snapshot of the current site state, it can contain personally identifiable data that not desirable to keep locally. This includes but is not limited to

* WordPress users, whether only employees or public registrations.
* Comments
* Gravity Forms submissions

In order to ensure we only retain the least amount of data necessary, this plugin provides some CLI commands to easily anonymize or delete this personally identifiable data .

## Installing

Installing this package requires WP-CLI v1.1.0 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with:

    wp package install git@github.com:humanmade/hm-anonymizer.git

Or

    wp plugin install https://github.com/humanmade/hm-anonymizer/archive/refs/heads/main.zip && wp plugin activate hm-anonymizer --network

## Using

Install and activate the plugin, then run WP CLI some of the provided commands as necessary to delete or anonymize user data from a WordPress site.

It is recommended that you update your project documentation for setting the site up from a production database to include these steps to ensure that nobody has any personally identifiable information from the production site locally. You can also make the anonymized database available for other developers.

Here is an example of usage on a large WordPress multisite using Gravity forms with many submissions. Adjust as necessary for you project.

Run all of the following commands:

```console
# Import the database
wp db import production-2024-04-01-079a3cd.sql

# Search replace commands
wp search-replace my-production-url.com my-local-url.dev --network

# Install and activate HM Anonymizer plugin
wp plugin install https://github.com/humanmade/hm-anonymizer/archive/refs/heads/main.zip && wp plugin activate hm-anonymizer --network

# Anonymize users, comments, cleanup pending users and force delete any gravity forms data network wide.
wp anonymizer anonymize-users
wp site list --field=url | xargs -n1 -I % wp --url=% anonymizer anonymize-comments
wp anonymizer delete-pending-users
wp anonymizer force-delete-gravity-forms-entries-network-wide

# Flush cache
wp cache flush

# Delete production database.
rm production-2024-04-01-079a3cd.sql;
```

Make sure you also delete any other copies of the production database, such as zip/tar archives immediately.

## Commands

### Anonymize users

Replace all core user fields with anonymized data e.g. "Hazy Hippopotamus". This includes names, email addresses, URLs. Passwords are also regenerated.

The following fields are also cleared: Description, all registered user contact methods (including any customisations).

User Meta. Custom user meta fields are not anonymized. You can use the filter `hm_anoymizer.user_data` to modify user data and include any user meta fields.

```php
add_filter( 'hm_anoymizer.user_data', function( $user_data ) {
	// Add custom user meta data.
	$user_data['meta_input']['custom-meta'] = 'supercalifragilisticexpialidocious';
	return $user_data;
} );
```

**Args:**

* **exclude** Comma separated list of user IDs to skip.

```
wp anonymizer anonymize-users --exclude=1,2,3
```


### Anonymize comments.

Anonymize comment data. If a user is associated with the comment, update comment data with user data, so this command is is intended to be run after you have anonymized users.

```console
## For one site only.
wp anonymizer anonymize-comments --url="%s"

## Network wide
wp site list --field=url | xargs -n1 -I % wp --url=% anonymizer anonymize-comments
```

### Delete Pending Users

On a WordPress Multisite, user data is stored in the signups table before the user is activated.

```console
wp anonymizer delete-gravity-forms-entries
```

### Delete Gravity Forms Entries

Deletes all entries across all forms on a site.

```console
wp anonymizer delete-gravity-forms-entries
```

### Force Delete Gravity Forms Entries Network Wide

Force delete entries by removing them from all Gravity Forms database tables directly. This affects all known gravity forms tables in the network (with the current site prefix). It will also empty legacy tables.

The advantage of this command is that it is much faster for sites with very large numbers of form submissions. In addition it removes legacy data and will catch any tables that for old sites that have been deleted but for some reason tables  remain in the database.

The disadvantage is that it doesn't call Gravity Forms functions directly, so no associated actions are fired and some things may not be cleaned up e.g. File uploads. Gravity form extensions may store data elsewhere and this will not be removed using this command so make sure to check.

```console
wp anonymizer force-delete-gravity-forms-entries-network-wide
```

### Force delete WP Stream plugin records.

The WP stream plugin can store records in the database, and some records can contain user data such as users names. This command just purges the database tables that store the records.

```console
wp anonymizer force-delete-stream-records
```

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/humanmade/hm-anonymizer/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/humanmade/hm-anonymizer/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/humanmade/hm-anonymizer/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

## Support

Github issues aren't for general support questions, but there are other venues you can try: https://wp-cli.org/#support

*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
