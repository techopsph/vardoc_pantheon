<?php

namespace Drupal\Tests\taxonomy_access_fix\Functional\Update;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\RequirementsPageTrait;

// @todo: Remove once Drupal 8.6 has been unsupported.
if (!trait_exists('\Drupal\Tests\RequirementsPageTrait')) {
  class_alias('\Drupal\Tests\taxonomy_access_fix\Traits\TaxonomyAccessFixFallbackRequirementsPageTrait', '\Drupal\Tests\RequirementsPageTrait');
}

/**
 * Provides an abstract base class to test Taxonomy Access Fix update hooks.
 */
abstract class UpdateTestBase extends BrowserTestBase {

  use RequirementsPageTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['taxonomy_access_fix'];

  /**
   * The user used to run the update.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The update URL.
   *
   * @var string
   */
  protected $updateUrl;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    require_once $this->root . '/core/includes/update.inc';
    $this->user = $this->drupalCreateUser(['administer software updates', 'access site in maintenance mode']);
    $this->updateUrl = Url::fromRoute('system.db_update');
  }

  /**
   * Tests that the update hook is run properly.
   */
  abstract public function testUpdateHook();

  /**
   * Applies an update hook and runs basic schema assertions.
   *
   * @param int $previous_schema
   *   Schema version to reset to before the update.
   * @param int $target_schema
   *   Schema version to assert during and after the update.
   * @param string $raw_message
   *   Raw message to assert on update selection screen. Usually the comment
   *   used for the update hook. Some characters may be HTML encoded.
   */
  protected function runUpdate(int $previous_schema, int $target_schema, string $raw_message) {
    // Set schema to previous schema.
    drupal_set_installed_schema_version('taxonomy_access_fix', $previous_schema);
    $this->assertIdentical((int) drupal_get_installed_schema_version('taxonomy_access_fix', TRUE), $previous_schema, new FormattableMarkup('Schema of taxonomy_access_fix is @schema', [
      '@schema' => $previous_schema,
    ]));

    // Login and call update.php. Go to available updates step.
    $this->drupalLogin($this->user);
    $this->drupalGet($this->updateUrl, ['external' => TRUE]);
    $this->updateRequirementsProblem();
    $this->clickLink(t('Continue'));

    // Assert target update is available.
    $this->assertRaw('taxonomy_access_fix module');
    $this->assertRaw((string) $target_schema . ' - ');
    $this->assertRaw($raw_message);

    // Run the update hooks.
    $this->clickLink(t('Apply pending updates'));
    $this->checkForMetaRefresh();

    // Ensure schema has changed.
    $this->assertIdentical((int) drupal_get_installed_schema_version('taxonomy_access_fix', TRUE), $target_schema, new FormattableMarkup('Schema of taxonomy_access_fix is @schema', [
      '@schema' => $target_schema,
    ]));
  }

}
