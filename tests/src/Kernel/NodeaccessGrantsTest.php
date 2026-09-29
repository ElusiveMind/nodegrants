<?php

declare(strict_types=1);

namespace Drupal\Tests\nodeaccess\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests nodeaccess grant records and grant logic.
 */
#[Group('nodeaccess')]
#[RunTestsInSeparateProcesses]
class NodeaccessGrantsTest extends KernelTestBase {

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

    // Run the nodeaccess install hook to set up role_map and defaults.
    \Drupal::moduleHandler()->loadInclude('nodeaccess', 'install');
    nodeaccess_install();
  }

  /**
   * Tests that default grants are returned for a node without custom grants.
   */
  public function testDefaultGrantsForPublishedNode(): void {
    $node = $this->drupalCreateNode(['type' => 'page', 'status' => 1]);

    $grants = nodeaccess_node_access_records($node);

    $this->assertNotEmpty($grants, 'Grants were returned for a published node.');

    // All grants should have the nodeaccess_rid realm by default.
    foreach ($grants as $grant) {
      $this->assertEquals('nodeaccess_rid', $grant['realm']);
    }
  }

  /**
   * Tests that a node with custom grants enabled uses its own grants.
   */
  public function testCustomGrantsForEnabledNode(): void {
    $node = $this->drupalCreateNode(['type' => 'page', 'status' => 1]);
    $nid = $node->id();

    $config = \Drupal::configFactory()->get('nodeaccess.settings');
    $role_map = $config->get('role_map');

    // Enable nodeaccess for this node.
    $this->database->insert('nodeaccess_nodes_enabled')
      ->fields(['nid' => $nid])
      ->execute();

    // Insert a custom grant for the authenticated role.
    $authenticated_gid = $role_map[RoleInterface::AUTHENTICATED_ID];
    $this->database->insert('nodeaccess')
      ->fields([
        'nid' => $nid,
        'gid' => $authenticated_gid,
        'realm' => 'nodeaccess_rid',
        'grant_view' => 1,
        'grant_update' => 1,
        'grant_delete' => 0,
      ])
      ->execute();

    $grants = nodeaccess_node_access_records($node);

    $this->assertNotEmpty($grants, 'Custom grants were returned.');

    // Find the grant for authenticated role.
    $found = FALSE;
    foreach ($grants as $grant) {
      if ($grant['gid'] == $authenticated_gid && $grant['realm'] == 'nodeaccess_rid') {
        $this->assertEquals(1, $grant['grant_view']);
        $this->assertEquals(1, $grant['grant_update']);
        $this->assertEquals(0, $grant['grant_delete']);
        $found = TRUE;
      }
    }
    $this->assertTrue($found, 'Custom grant for authenticated role was found.');
  }

  /**
   * Tests that user-specific grants are stored and returned.
   */
  public function testUserSpecificGrants(): void {
    $user = $this->drupalCreateUser(['access content']);
    $node = $this->drupalCreateNode(['type' => 'page', 'status' => 1]);
    $nid = $node->id();

    // Enable nodeaccess for this node.
    $this->database->insert('nodeaccess_nodes_enabled')
      ->fields(['nid' => $nid])
      ->execute();

    // Insert a user-specific grant.
    $this->database->insert('nodeaccess')
      ->fields([
        'nid' => $nid,
        'gid' => $user->id(),
        'realm' => 'nodeaccess_uid',
        'grant_view' => 1,
        'grant_update' => 1,
        'grant_delete' => 1,
      ])
      ->execute();

    $grants = nodeaccess_node_access_records($node);

    // Find the user-specific grant.
    $found = FALSE;
    foreach ($grants as $grant) {
      if ($grant['gid'] == $user->id() && $grant['realm'] == 'nodeaccess_uid') {
        $this->assertEquals(1, $grant['grant_view']);
        $this->assertEquals(1, $grant['grant_update']);
        $this->assertEquals(1, $grant['grant_delete']);
        $found = TRUE;
      }
    }
    $this->assertTrue($found, 'User-specific grant was returned.');
  }

  /**
   * Tests that author grants are applied from content type defaults.
   */
  public function testAuthorGrants(): void {
    $author = $this->drupalCreateUser(['access content']);
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
      'uid' => $author->id(),
    ]);
    $nid = $node->id();

    // Enable nodeaccess for this node and add a role grant so the node
    // has grants of its own.
    $this->database->insert('nodeaccess_nodes_enabled')
      ->fields(['nid' => $nid])
      ->execute();

    $config = \Drupal::configFactory()->get('nodeaccess.settings');
    $role_map = $config->get('role_map');
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

    $grants = nodeaccess_node_access_records($node);

    // Find the author grant.
    $found = FALSE;
    foreach ($grants as $grant) {
      if ($grant['realm'] == 'nodeaccess_author' && $grant['gid'] == $author->id()) {
        $found = TRUE;
        // Author defaults are set to 1 for all in nodeaccess_install().
        $this->assertEquals(1, $grant['grant_view']);
        $this->assertEquals(1, $grant['grant_update']);
        $this->assertEquals(1, $grant['grant_delete']);
      }
    }
    $this->assertTrue($found, 'Author grant was applied.');
  }

  /**
   * Tests that the author grant goes to the node's current owner.
   */
  public function testAuthorGrantFollowsOwner(): void {
    $old_owner = $this->drupalCreateUser(['access content']);
    $new_owner = $this->drupalCreateUser(['access content']);
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
      'uid' => $old_owner->id(),
    ]);
    $nid = $node->id();

    // Enable nodeaccess with the author row the node form stores, which holds
    // the owner at the time the grants were saved.
    $this->database->insert('nodeaccess_nodes_enabled')
      ->fields(['nid' => $nid])
      ->execute();
    $this->database->insert('nodeaccess')
      ->fields([
        'nid' => $nid,
        'gid' => $old_owner->id(),
        'realm' => 'nodeaccess_author',
        'grant_view' => 1,
        'grant_update' => 1,
        'grant_delete' => 1,
      ])
      ->execute();

    $node->setOwnerId($new_owner->id());
    $author_gids = [];
    foreach (nodeaccess_node_access_records($node) as $grant) {
      if ($grant['realm'] == 'nodeaccess_author') {
        $author_gids[] = $grant['gid'];
      }
    }
    $this->assertEquals([$new_owner->id()], $author_gids, 'Only the current owner gets the author grant.');
  }

  /**
   * Tests that nodes owned by the anonymous user get no author grant.
   */
  public function testNoAuthorGrantForAnonymousOwner(): void {
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
      'uid' => 0,
    ]);
    $this->database->insert('nodeaccess_nodes_enabled')
      ->fields(['nid' => $node->id()])
      ->execute();

    foreach (nodeaccess_node_access_records($node) as $grant) {
      $this->assertNotEquals('nodeaccess_author', $grant['realm'], 'Anonymous-owned node has no author grant.');
    }
  }

  /**
   * Tests that anonymous visitors are not placed in the author realm.
   */
  public function testAnonymousHasNoAuthorRealm(): void {
    $grants = nodeaccess_node_grants(new AnonymousUserSession(), 'view');
    $this->assertArrayNotHasKey('nodeaccess_author', $grants);
    $this->assertArrayHasKey('nodeaccess_rid', $grants);
  }

  /**
   * Tests that grants have all required default keys.
   */
  public function testGrantDefaultKeys(): void {
    $node = $this->drupalCreateNode(['type' => 'page', 'status' => 1]);

    $grants = nodeaccess_node_access_records($node);

    $required_keys = ['nid', 'gid', 'realm', 'grant_view', 'grant_update', 'grant_delete'];
    foreach ($grants as $grant) {
      foreach ($required_keys as $key) {
        $this->assertArrayHasKey($key, $grant, "Grant is missing required key: $key");
      }
    }
  }

  /**
   * Tests nodeaccess_node_grants() returns correct realms and gids.
   */
  public function testNodeGrants(): void {
    $user = $this->drupalCreateUser(['access content']);
    $grants = nodeaccess_node_grants($user, 'view');

    $this->assertArrayHasKey('nodeaccess_rid', $grants);
    $this->assertArrayHasKey('nodeaccess_uid', $grants);
    $this->assertArrayHasKey('nodeaccess_author', $grants);

    // User should have their uid in the uid and author realms.
    $this->assertContains($user->id(), $grants['nodeaccess_uid']);
    $this->assertContains($user->id(), $grants['nodeaccess_author']);

    // User should have role gids in the rid realm.
    $config = \Drupal::configFactory()->get('nodeaccess.settings');
    $role_map = $config->get('role_map');
    foreach ($user->getRoles() as $role) {
      $this->assertContains($role_map[$role], $grants['nodeaccess_rid']);
    }
  }

  /**
   * Tests that grants are cleaned up when a node is deleted.
   */
  public function testNodeDeleteCleansUpGrants(): void {
    $node = $this->drupalCreateNode(['type' => 'page', 'status' => 1]);
    $nid = $node->id();

    // Insert grants for this node.
    $this->database->insert('nodeaccess_nodes_enabled')
      ->fields(['nid' => $nid])
      ->execute();
    $this->database->insert('nodeaccess')
      ->fields([
        'nid' => $nid,
        'gid' => 1,
        'realm' => 'nodeaccess_rid',
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ])
      ->execute();

    // Verify grants exist.
    $count = $this->database->select('nodeaccess', 'n')
      ->condition('nid', $nid)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $count);

    $enabled_count = $this->database->select('nodeaccess_nodes_enabled', 'ne')
      ->condition('nid', $nid)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $enabled_count);

    // Delete the node.
    $node->delete();

    // Verify grants were cleaned up.
    $count = $this->database->select('nodeaccess', 'n')
      ->condition('nid', $nid)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(0, $count, 'Grants were removed from nodeaccess table.');

    $enabled_count = $this->database->select('nodeaccess_nodes_enabled', 'ne')
      ->condition('nid', $nid)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(0, $enabled_count, 'Node was removed from nodeaccess_nodes_enabled table.');
  }

  /**
   * Tests that node type deletion cleans up config.
   */
  public function testNodeTypeDeletion(): void {
    $config = \Drupal::configFactory()->getEditable('nodeaccess.settings');

    // Set up some allowed_types config.
    $config->set('allowed_types', ['page' => 1])->save();

    // Delete the node type.
    $type = NodeType::load('page');
    $type->delete();

    // Verify config was cleaned up.
    $config = \Drupal::configFactory()->get('nodeaccess.settings');
    $allowed_types = $config->get('allowed_types');
    $this->assertArrayNotHasKey('page', $allowed_types ?? []);
  }

}
