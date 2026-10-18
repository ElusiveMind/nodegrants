# Nodegrants - Per Node Grant Management for Drupal

## Introduction

This module allows you to manage permissions for individual nodes by role and
user. You can restrict access to any node without having to use taxonomy by
assigning view, edit, or delete permissions per node to specific roles or users.

Grants are configured directly on the node add/edit form rather than on a
separate tab. Default grants are defined per content type on each content type's
edit page.

## Requirements

- Drupal 11/12
- PHP 8.3+

## Installation

Install via Composer:

```
composer require drupal/nodegrants
```

Then enable the module:

```
drush en nodegrants
```

> **Note:** This package conflicts with `drupal/nodeaccess` (the legacy
> Drupal.org version). You cannot have both installed at the same time.

## Configuration

1. **Enable per content type:** Go to **Administration > Structure > Content
   types**, edit a content type, and check "Show grant tab for this node type"
   under the Nodegrants section. Set default role permissions for view, edit, and
   delete here as well.

   Unchecking it later turns nodegrants off for that content type. Grants
   already saved on its nodes are kept but ignored, so those nodes fall back to
   Drupal's normal content permissions; checking it again brings the saved
   grants back. After either change Drupal asks you to rebuild content access
   permissions, which applies the change to existing nodes.

2. **Assign role visibility:** Go to **Administration > People > Permissions**
   and grant the "Include in Nodegrants grants" permission to roles that should
   appear in per-node grant forms. If the "Authenticated user" role has this
   permission, all non-anonymous roles are automatically included.

3. **Per-node grants:** When creating or editing a node (for an enabled content
   type), expand the Nodegrants section and set the role and user-specific
   permissions for that node. The boxes start from the content type defaults.
   Saving the node stores them and puts the node under nodegrants control;
   there is no separate on/off switch per node.

4. **Admin page:** Visit **Administration > Structure > Nodegrants** to view or
   rebuild the role-to-grant-ID mapping.

## Drush

The module provides one Drush command (Drush 13.7 or later):

```
drush nodegrants:rebuild
drush ng:rebuild
```

It rebuilds Drupal's content access permissions (the `node_access` table), the
same as the "Rebuild permissions" button on the status report. Run it after
turning nodegrants on or off for a content type, or whenever Drupal reports
that content access permissions need to be rebuilt. The rebuild runs in one
process rather than a batch, so on sites with a very large number of nodes it
may take a while.

## Permissions

| Permission | Description |
|---|---|
| Administer Nodegrants | Access the Nodegrants administration pages (restricted) |
| Grant Node Permissions | Allow granting permissions on individual nodes |
| Include in Nodegrants grants | Determines if a role appears in per-node grant forms |

## Testing

PHPUnit tests are located in `tests/src/`. To run them from the Drupal root:

```
vendor/bin/phpunit web/modules/drupal/nodegrants/tests/
```

Kernel tests cover grant records, access checks, role map management, and
installation. Functional tests cover the admin configuration form and node form
integration.
