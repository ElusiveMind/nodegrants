# 1.0.0

1. Completely re-worked the interface. Grants tab is gone and grants are placed on the node edit page for each new and existing node so that permissions can be defined at the time of node creation.
2. Default node permissions are defined on the node-type edit page.
3. Autocomplete of users works regardless of permissions to user profiles (accesses UID and name only for search purposes).
4. Massive coding overhaul without overall loss of compatibility with legacy Nodeaccess module (on Drupal.org).
5. Addition of permissions for allowing role types to be part of the per node configuration. This is handled on the permissions tab now and not part of a separate configuration,
6. Other minor changes to leverage Drupal.org's existing interface components and keep more in line with the "Drupal Way".
7. Added the `drush nodegrants:rebuild` (alias `ng:rebuild`) command, which rebuilds the content access permissions from the command line. Requires Drush 13.7 or later.