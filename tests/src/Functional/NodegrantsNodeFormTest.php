<?php

declare(strict_types=1);

namespace Drupal\Tests\nodegrants\Functional;

use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests nodegrants integration on node add/edit forms.
 */
#[Group('nodegrants')]
#[RunTestsInSeparateProcesses]
class NodegrantsNodeFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'nodegrants',
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
      'administer nodegrants',
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
   * Tests that nodegrants form does NOT appear when not enabled for type.
   */
  public function testNodeFormWithoutNodegrants(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/node/add/page');
    $this->assertSession()->statusCodeEquals(200);

    // Nodegrants section should not appear because it's not enabled.
    $this->assertSession()->fieldNotExists('table[user][page_user]');
  }

  /**
   * Tests that nodegrants form appears when enabled for the content type.
   */
  public function testNodeFormWithNodegrants(): void {
    // Enable nodegrants for the page content type.
    $type = NodeType::load('page');
    $type->setThirdPartySetting('nodegrants', 'nodegrants_grant_tab_page', TRUE);
    $type->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/node/add/page');
    $this->assertSession()->statusCodeEquals(200);

    // Nodegrants section should appear, with no per-node on/off toggle.
    $this->assertSession()->fieldExists('table[user][page_user]');
    $this->assertSession()->fieldNotExists('nodegrants_enable');
  }

  /**
   * Tests nodegrants settings on node type edit form.
   */
  public function testNodeTypeEditForm(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/types/manage/page');
    $this->assertSession()->statusCodeEquals(200);

    // Nodegrants settings should be present.
    $this->assertSession()->pageTextContains('Nodegrants');
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
      (bool) $type->getThirdPartySetting('nodegrants', 'nodegrants_grant_tab_page', FALSE),
      'Grant tab was enabled for the content type.'
    );

    // Changing the switch on an existing type flags a node access rebuild.
    $this->assertTrue(
      (bool) \Drupal::state()->get('node.node_access_needs_rebuild'),
      'Turning nodegrants on for the type flagged a node access rebuild.'
    );

    // Now the node form should show the nodegrants section.
    $this->drupalGet('/node/add/page');
    $this->assertSession()->fieldExists('table[user][page_user]');
  }

  /**
   * Tests that role checkboxes appear based on allowed roles.
   */
  public function testRoleCheckboxesOnNodeForm(): void {
    // Enable nodegrants for page.
    $type = NodeType::load('page');
    $type->setThirdPartySetting('nodegrants', 'nodegrants_grant_tab_page', TRUE);
    $type->save();

    // Give authenticated role the permission to appear in grants.
    $authenticated = Role::load('authenticated');
    $authenticated->grantPermission('include in nodegrants grants');
    $authenticated->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/node/add/page');

    // Authenticated role should appear in the form.
    $this->assertSession()->pageTextContains('Authenticated user');
  }

  /**
   * Tests creating a node with nodegrants grants enabled.
   */
  public function testCreateNodeWithGrants(): void {
    // Enable nodegrants for page.
    $type = NodeType::load('page');
    $type->setThirdPartySetting('nodegrants', 'nodegrants_grant_tab_page', TRUE);
    $type->save();

    // Give authenticated role the permission.
    $authenticated = Role::load('authenticated');
    $authenticated->grantPermission('include in nodegrants grants');
    $authenticated->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/node/add/page');

    // Saving through the pane always puts the node under nodegrants control.
    $edit = [
      'title[0][value]' => 'Test Node',
    ];
    $this->submitForm($edit, 'Save');

    // Verify node was created.
    $this->assertSession()->pageTextContains('Test Node');

    // Verify grants were saved in the database.
    $db = \Drupal::database();
    $nodes = $db->select('nodegrants_nodes_enabled', 'ne')
      ->fields('ne')
      ->execute()
      ->fetchAll();
    $this->assertNotEmpty($nodes, 'Node was recorded in nodegrants_nodes_enabled.');
  }

  /**
   * Tests the node type add form includes nodegrants settings.
   */
  public function testNodeTypeAddForm(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/types/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Nodegrants');
  }

}
