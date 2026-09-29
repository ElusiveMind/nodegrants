<?php

declare(strict_types=1);

namespace Drupal\Tests\nodeaccess\Kernel;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests nodeaccess role map management when roles are added/removed.
 */
#[Group('nodeaccess')]
#[RunTestsInSeparateProcesses]
class NodeaccessRoleManagementTest extends KernelTestBase {

  use NodeCreationTrait {
    getNodeByTitle as drupalGetNodeByTitle;
    createNode as drupalCreateNode;
  }
  use UserCreationTrait {
    createUser as drupalCreateUser;
    createRole as drupalCreateRole;
    createAdminRole as drupalCreateAdminRole;
  }
  use ContentTypeCreationTrait {
    createContentType as drupalCreateContentType;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'nodeaccess',
    'datetime',
    'user',
    'system',
    'filter',
    'field',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('node', 'node_access');
    $this->installSchema('nodeaccess', ['nodeaccess', 'nodeaccess_nodes_enabled']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['filter', 'node', 'user']);

    // Create user 1.
    $this->drupalCreateUser();

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    // Run nodeaccess install.
    \Drupal::moduleHandler()->loadInclude('nodeaccess', 'install');
    nodeaccess_install();
  }

  /**
   * Tests that creating a new role adds it to the role_map.
   */
  public function testNewRoleAddsToRoleMap(): void {
    $config = \Drupal::configFactory()->get('nodeaccess.settings');
    $role_map_before = $config->get('role_map');

    // Create a new role.
    $role = Role::create([
      'id' => 'editor',
      'label' => 'Editor',
    ]);
    $role->save();

    $config = \Drupal::configFactory()->get('nodeaccess.settings');
    $role_map_after = $config->get('role_map');

    $this->assertArrayHasKey('editor', $role_map_after, 'New role was added to role_map.');
    $this->assertGreaterThan(count($role_map_before), count($role_map_after));
  }

  /**
   * Tests that deleting a role removes it from the role_map.
   */
  public function testDeletedRoleRemovedFromRoleMap(): void {
    // Create a role first.
    $role = Role::create([
      'id' => 'editor',
      'label' => 'Editor',
    ]);
    $role->save();

    $config = \Drupal::configFactory()->get('nodeaccess.settings');
    $role_map = $config->get('role_map');
    $this->assertArrayHasKey('editor', $role_map);

    // Delete the role.
    $role->delete();

    $config = \Drupal::configFactory()->get('nodeaccess.settings');
    $role_map = $config->get('role_map');
    $this->assertArrayNotHasKey('editor', $role_map, 'Deleted role was removed from role_map.');
  }

  /**
   * Tests the role_map is correctly populated on install.
   */
  public function testRoleMapOnInstall(): void {
    $config = \Drupal::configFactory()->get('nodeaccess.settings');
    $role_map = $config->get('role_map');

    $this->assertNotEmpty($role_map, 'Role map is not empty after install.');
    $this->assertArrayHasKey('anonymous', $role_map);
    $this->assertArrayHasKey('authenticated', $role_map);

    // Values should be sequential integers.
    $values = array_values($role_map);
    sort($values);
    $this->assertEquals(range(0, count($values) - 1), $values, 'Role map gids are sequential integers.');
  }

  /**
   * Tests nodeaccess_get_allowed_roles() with permission assignment.
   */
  public function testAllowedRoles(): void {
    // Create a custom role and give it the permission.
    $role = Role::create([
      'id' => 'editor',
      'label' => 'Editor',
    ]);
    $role->grantPermission('include in nodeaccess grants');
    $role->save();

    $allowed = nodeaccess_get_allowed_roles();
    $this->assertTrue($allowed['editor'] ?? FALSE, 'Role with permission is allowed.');
  }

  /**
   * Tests that granting authenticated role permission includes all roles.
   */
  public function testAuthenticatedRoleGrantsAllRoles(): void {
    $authenticated = Role::load('authenticated');
    $authenticated->grantPermission('include in nodeaccess grants');
    $authenticated->save();

    $allowed = nodeaccess_get_allowed_roles();

    // All roles should be allowed (including anonymous because the code
    // iterates all roles and sets TRUE when all_non_anonymous_roles is TRUE).
    $roles = Role::loadMultiple();
    foreach ($roles as $id => $role) {
      $this->assertTrue($allowed[$id] ?? FALSE, "Role $id should be allowed when authenticated has the permission.");
    }
  }

}
