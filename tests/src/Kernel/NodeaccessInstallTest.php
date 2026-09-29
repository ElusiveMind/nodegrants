<?php

declare(strict_types=1);

namespace Drupal\Tests\nodeaccess\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests nodeaccess installation and schema setup.
 */
#[Group('nodeaccess')]
#[RunTestsInSeparateProcesses]
class NodeaccessInstallTest extends KernelTestBase {

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

    $this->drupalCreateUser();
  }

  /**
   * Tests that the nodeaccess schema tables are created.
   */
  public function testSchemaTablesExist(): void {
    $schema = \Drupal::database()->schema();
    $this->assertTrue($schema->tableExists('nodeaccess'), 'nodeaccess table exists.');
    $this->assertTrue($schema->tableExists('nodeaccess_nodes_enabled'), 'nodeaccess_nodes_enabled table exists.');
  }

  /**
   * Tests that install sets up third-party settings on content types.
   */
  public function testInstallSetsDefaults(): void {
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Run install.
    \Drupal::moduleHandler()->loadInclude('nodeaccess', 'install');
    nodeaccess_install();

    $type = NodeType::load('article');

    // Anonymous should have view access if it has 'access content' permission.
    $anonymous = Role::load('anonymous');
    $expected_view = (int) $anonymous->hasPermission('access content');
    $actual = $type->getThirdPartySetting('nodeaccess', 'nodeaccess_article_anonymous_view', NULL);
    $this->assertEquals($expected_view, $actual, 'Anonymous view default matches permission.');

    // Author defaults should all be 1.
    $this->assertEquals(1, $type->getThirdPartySetting('nodeaccess', 'nodeaccess_article_author_view'));
    $this->assertEquals(1, $type->getThirdPartySetting('nodeaccess', 'nodeaccess_article_author_update'));
    $this->assertEquals(1, $type->getThirdPartySetting('nodeaccess', 'nodeaccess_article_author_delete'));
  }

  /**
   * Tests that the nodeaccess table has the correct columns.
   */
  public function testNodeaccessTableSchema(): void {
    $db = \Drupal::database();

    // Insert a row to verify all columns exist.
    $db->insert('nodeaccess')
      ->fields([
        'nid' => 999,
        'gid' => 1,
        'realm' => 'nodeaccess_rid',
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ])
      ->execute();

    $result = $db->select('nodeaccess', 'n')
      ->fields('n')
      ->condition('nid', 999)
      ->execute()
      ->fetchAssoc();

    $this->assertEquals(999, $result['nid']);
    $this->assertEquals(1, $result['gid']);
    $this->assertEquals('nodeaccess_rid', $result['realm']);
    $this->assertEquals(1, $result['grant_view']);
    $this->assertEquals(0, $result['grant_update']);
    $this->assertEquals(0, $result['grant_delete']);
  }

  /**
   * Tests the primary key constraint on nodeaccess table.
   */
  public function testNodeaccessPrimaryKey(): void {
    $db = \Drupal::database();

    $db->insert('nodeaccess')
      ->fields([
        'nid' => 100,
        'gid' => 1,
        'realm' => 'nodeaccess_rid',
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ])
      ->execute();

    // Inserting a duplicate should fail.
    $this->expectException(\Exception::class);
    $db->insert('nodeaccess')
      ->fields([
        'nid' => 100,
        'gid' => 1,
        'realm' => 'nodeaccess_rid',
        'grant_view' => 0,
        'grant_update' => 1,
        'grant_delete' => 1,
      ])
      ->execute();
  }

}
