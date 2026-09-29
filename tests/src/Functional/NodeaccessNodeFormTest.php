<?php

declare(strict_types=1);

namespace Drupal\Tests\nodeaccess\Functional;

use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests nodeaccess integration on node add/edit forms.
 */
#[Group('nodeaccess')]
#[RunTestsInSeparateProcesses]
class NodeaccessNodeFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'nodeaccess',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with admin permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a content type.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    $this->adminUser = $this->drupalCreateUser([
      'administer nodeaccess',
      'administer content types',
      'administer nodes',
      'access content',
      'bypass node access',
      'create page content',
      'edit any page content',
      'grant node permissions',
    ]);
  }

  /**
   * Tests that nodeaccess form does NOT appear when not enabled for type.
   */
  public function testNodeFormWithoutNodeaccess(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/node/add/page');
    $this->assertSession()->statusCodeEquals(200);

    // Nodeaccess section should not appear because it's not enabled.
    $this->assertSession()->pageTextNotContains('Engage nodeaccess control for this node');
  }

  /**
   * Tests that nodeaccess form appears when enabled for the content type.
   */
  public function testNodeFormWithNodeaccess(): void {
    // Enable nodeaccess for the page content type.
    $type = NodeType::load('page');
    $type->setThirdPartySetting('nodeaccess', 'nodeaccess_grant_tab_page', TRUE);
    $type->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/node/add/page');
    $this->assertSession()->statusCodeEquals(200);

    // Nodeaccess section should appear.
    $this->assertSession()->pageTextContains('Engage nodeaccess control for this node');
  }

  /**
   * Tests nodeaccess settings on node type edit form.
   */
  public function testNodeTypeEditForm(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/types/manage/page');
    $this->assertSession()->statusCodeEquals(200);

    // Nodeaccess settings should be present.
    $this->assertSession()->pageTextContains('Nodeaccess');
    $this->assertSession()->pageTextContains('Show grant tab for this node type.');
  }

  /**
   * Tests enabling the grant tab through the node type edit form.
   */
  public function testEnableGrantTabViaNodeTypeForm(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/types/manage/page');

    // Enable the grant tab.
    $this->submitForm(['enabled' => TRUE], 'Save');

    // Verify it was saved.
    $type = NodeType::load('page');
    $this->assertTrue(
      (bool) $type->getThirdPartySetting('nodeaccess', 'nodeaccess_grant_tab_page', FALSE),
      'Grant tab was enabled for the content type.'
    );

    // Now the node form should show the nodeaccess section.
    $this->drupalGet('/node/add/page');
    $this->assertSession()->pageTextContains('Engage nodeaccess control for this node');
  }

  /**
   * Tests that role checkboxes appear based on allowed roles.
   */
  public function testRoleCheckboxesOnNodeForm(): void {
    // Enable nodeaccess for page.
    $type = NodeType::load('page');
    $type->setThirdPartySetting('nodeaccess', 'nodeaccess_grant_tab_page', TRUE);
    $type->save();

    // Give authenticated role the permission to appear in grants.
    $authenticated = Role::load('authenticated');
    $authenticated->grantPermission('include in nodeaccess grants');
    $authenticated->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/node/add/page');

    // Authenticated role should appear in the form.
    $this->assertSession()->pageTextContains('Authenticated user');
  }

  /**
   * Tests creating a node with nodeaccess grants enabled.
   */
  public function testCreateNodeWithGrants(): void {
    // Enable nodeaccess for page.
    $type = NodeType::load('page');
    $type->setThirdPartySetting('nodeaccess', 'nodeaccess_grant_tab_page', TRUE);
    $type->save();

    // Give authenticated role the permission.
    $authenticated = Role::load('authenticated');
    $authenticated->grantPermission('include in nodeaccess grants');
    $authenticated->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/node/add/page');

    // Fill in the node form with nodeaccess enabled.
    $edit = [
      'title[0][value]' => 'Test Node',
      'nodeaccess_enable' => TRUE,
    ];
    $this->submitForm($edit, 'Save');

    // Verify node was created.
    $this->assertSession()->pageTextContains('Test Node');

    // Verify grants were saved in the database.
    $db = \Drupal::database();
    $nodes = $db->select('nodeaccess_nodes_enabled', 'ne')
      ->fields('ne')
      ->execute()
      ->fetchAll();
    $this->assertNotEmpty($nodes, 'Node was recorded in nodeaccess_nodes_enabled.');
  }

  /**
   * Tests the node type add form includes nodeaccess settings.
   */
  public function testNodeTypeAddForm(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/types/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Nodeaccess');
  }

}
