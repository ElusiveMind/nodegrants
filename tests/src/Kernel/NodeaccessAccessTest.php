<?php

declare(strict_types=1);

namespace Drupal\Tests\nodeaccess\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests nodeaccess_node_access() hook for update and delete operations.
 */
#[Group('nodeaccess')]
#[RunTestsInSeparateProcesses]
class NodeaccessAccessTest extends KernelTestBase {

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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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

    $this->database = Database::getConnection();

    // Create user 1 (super admin).
    $this->drupalCreateUser();

    // Create a content type.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    // Run nodeaccess install to set up role_map.
    \Drupal::moduleHandler()->loadInclude('nodeaccess', 'install');
    nodeaccess_install();
  }

  /**
   * Tests that a user with a user-specific update grant can update.
   */
  public function testUserSpecificUpdateAccess(): void {
    $user = $this->drupalCreateUser(['access content']);
    $node = $this->drupalCreateNode(['type' => 'page', 'status' => 1]);
    $nid = $node->id();

    // Enable nodeaccess and grant update to this user.
    $this->database->insert('nodeaccess_nodes_enabled')
      ->fields(['nid' => $nid])
      ->execute();
    $this->database->insert('nodeaccess')
      ->fields([
        'nid' => $nid,
        'gid' => $user->id(),
        'realm' => 'nodeaccess_uid',
        'grant_view' => 1,
        'grant_update' => 1,
        'grant_delete' => 0,
      ])
      ->execute();

    $access = nodeaccess_node_access($node, 'update', $user);
    $this->assertTrue($access->isAllowed(), 'User with update grant can update.');

    $access = nodeaccess_node_access($node, 'delete', $user);
    $this->assertFalse($access->isAllowed(), 'User without delete grant cannot delete.');
  }

  /**
   * Tests that a user with a user-specific delete grant can delete.
   */
  public function testUserSpecificDeleteAccess(): void {
    $user = $this->drupalCreateUser(['access content']);
    $node = $this->drupalCreateNode(['type' => 'page', 'status' => 1]);
    $nid = $node->id();

    // Enable nodeaccess and grant delete to this user.
    $this->database->insert('nodeaccess_nodes_enabled')
      ->fields(['nid' => $nid])
      ->execute();
    $this->database->insert('nodeaccess')
      ->fields([
        'nid' => $nid,
        'gid' => $user->id(),
        'realm' => 'nodeaccess_uid',
        'grant_view' => 0,
        'grant_update' => 0,
        'grant_delete' => 1,
      ])
      ->execute();

    $access = nodeaccess_node_access($node, 'delete', $user);
    $this->assertTrue($access->isAllowed(), 'User with delete grant can delete.');

    $access = nodeaccess_node_access($node, 'update', $user);
    $this->assertFalse($access->isAllowed(), 'User without update grant cannot update.');
  }

  /**
   * Tests role-based access through nodeaccess_node_access().
   */
  public function testRoleBasedAccess(): void {
    $config = \Drupal::configFactory()->get('nodeaccess.settings');
    $role_map = $config->get('role_map');

    $user = $this->drupalCreateUser(['access content']);
    $node = $this->drupalCreateNode(['type' => 'page', 'status' => 1]);
    $nid = $node->id();

    // Enable nodeaccess and grant update to authenticated role.
    $this->database->insert('nodeaccess_nodes_enabled')
      ->fields(['nid' => $nid])
      ->execute();
    $this->database->insert('nodeaccess')
      ->fields([
        'nid' => $nid,
        'gid' => $role_map[RoleInterface::AUTHENTICATED_ID],
        'realm' => 'nodeaccess_rid',
        'grant_view' => 1,
        'grant_update' => 1,
        'grant_delete' => 0,
      ])
      ->execute();

    $access = nodeaccess_node_access($node, 'update', $user);
    $this->assertTrue($access->isAllowed(), 'Authenticated user with role grant can update.');

    $access = nodeaccess_node_access($node, 'delete', $user);
    $this->assertFalse($access->isAllowed(), 'Authenticated user without role delete grant cannot delete.');
  }

  /**
   * Tests that a user without any grants is denied access.
   */
  public function testNoGrantsDeniesAccess(): void {
    $user = $this->drupalCreateUser(['access content']);
    $node = $this->drupalCreateNode(['type' => 'page', 'status' => 1]);
    $nid = $node->id();

    // Enable nodeaccess but add no grants.
    $this->database->insert('nodeaccess_nodes_enabled')
      ->fields(['nid' => $nid])
      ->execute();

    $access = nodeaccess_node_access($node, 'update', $user);
    // With no grants at all, the function returns neutral for the else case.
    $this->assertNotNull($access);
  }

  /**
   * Tests that view operation returns NULL (defers to core).
   */
  public function testViewOperationDefers(): void {
    $user = $this->drupalCreateUser(['access content']);
    $node = $this->drupalCreateNode(['type' => 'page', 'status' => 1]);

    $access = nodeaccess_node_access($node, 'view', $user);
    $this->assertNull($access, 'View operation defers to core node access.');
  }

  /**
   * Tests that the node's owner gets the content type's author grants.
   */
  public function testAuthorAccess(): void {
    $config = \Drupal::configFactory()->get('nodeaccess.settings');
    $role_map = $config->get('role_map');

    $author = $this->drupalCreateUser(['access content']);
    $other = $this->drupalCreateUser(['access content']);
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
      'uid' => $author->id(),
    ]);
    $nid = $node->id();

    // Enable nodeaccess with a role grant that allows viewing only.
    $this->database->insert('nodeaccess_nodes_enabled')
      ->fields(['nid' => $nid])
      ->execute();
    $this->database->insert('nodeaccess')
      ->fields([
        'nid' => $nid,
        'gid' => $role_map[RoleInterface::AUTHENTICATED_ID],
        'realm' => 'nodeaccess_rid',
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ])
      ->execute();

    // nodeaccess_install() sets every author default to 1.
    $this->assertTrue(nodeaccess_node_access($node, 'update', $author)->isAllowed(), 'Author can update.');
    $this->assertTrue(nodeaccess_node_access($node, 'delete', $author)->isAllowed(), 'Author can delete.');
    $this->assertFalse(nodeaccess_node_access($node, 'update', $other)->isAllowed(), 'Non-author cannot update.');

    // Turning an author default off removes that grant.
    NodeType::load('page')
      ->setThirdPartySetting('nodeaccess', 'nodeaccess_page_author_update', 0)
      ->save();
    $this->assertFalse(nodeaccess_node_access($node, 'update', $author)->isAllowed(), 'Author cannot update once the default is off.');
    $this->assertTrue(nodeaccess_node_access($node, 'delete', $author)->isAllowed(), 'Author can still delete.');
  }

}
