<?php

declare(strict_types=1);

namespace Drupal\Tests\nodeaccess\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Nodeaccess admin configuration form.
 */
#[Group('nodeaccess')]
#[RunTestsInSeparateProcesses]
class NodeaccessConfigFormTest extends BrowserTestBase {

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
    $this->adminUser = $this->drupalCreateUser([
      'administer nodeaccess',
      'access administration pages',
      'administer content types',
      'administer nodes',
      'access content',
      'bypass node access',
    ]);
  }

  /**
   * Tests access to the admin configuration page.
   */
  public function testAdminPageAccess(): void {
    // Anonymous should not have access.
    $this->drupalGet('/admin/structure/nodeaccess');
    $this->assertSession()->statusCodeEquals(403);

    // Admin user should have access.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/nodeaccess');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Nodeaccess');
  }

  /**
   * Tests the role map rebuild functionality.
   */
  public function testRoleMapRebuild(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/nodeaccess');

    // The role map table should show current roles.
    $this->assertSession()->pageTextContains('Anonymous user');
    $this->assertSession()->pageTextContains('Authenticated user');

    // Check the rebuild checkbox and submit.
    $this->submitForm(['rebuild_map' => TRUE], 'Save configuration');
    $this->assertSession()->pageTextContains('The role map has been rebuilt.');
  }

  /**
   * Tests that unauthenticated users cannot access admin routes.
   */
  public function testAdminRoutePermissions(): void {
    $unprivileged = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($unprivileged);

    $this->drupalGet('/admin/structure/nodeaccess');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests the admin page is linked from Structure menu.
   */
  public function testAdminMenuLink(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Nodeaccess');
  }

}
