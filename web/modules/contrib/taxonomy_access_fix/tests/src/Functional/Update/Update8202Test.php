<?php

namespace Drupal\Tests\taxonomy_access_fix\Functional\Update;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

// @todo: Remove once Drupal 8.7 has been unsupported.
if (!trait_exists('\Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait')) {
  class_alias('\Drupal\Tests\taxonomy\Functional\TaxonomyTestTrait', '\Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait');
}

/**
 * Tests update hook 8202.
 *
 * @group Update
 */
class Update8202Test extends UpdateTestBase {

  use TaxonomyTestTrait;

  /**
   * Tests that update hook 8202 is properly run.
   */
  public function testUpdateHook() {
    $assert_session = $this->assertSession();

    // Create vocabularies.
    $vocabularies = [
      $this->createVocabulary(),
      $this->createVocabulary(),
    ];

    // Create roles.
    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('user_role');
    $roles_create_access = [
      'administrator' => [
        $vocabularies[0]->id() => TRUE,
        $vocabularies[1]->id() => TRUE,
      ],
      'first_vocabulary' => [
        $vocabularies[0]->id() => TRUE,
        $vocabularies[1]->id() => FALSE,
      ],
      'second_vocabulary' => [
        $vocabularies[0]->id() => FALSE,
        $vocabularies[1]->id() => TRUE,
      ],
      'no_permissions' => [
        $vocabularies[0]->id() => FALSE,
        $vocabularies[1]->id() => FALSE,
      ],
    ];
    foreach ($roles_create_access as $role_id => $create_access) {
      $role = $storage->create([
        'id' => $role_id,
        'label' => $role_id,
        'is_admin' => $role_id === 'administrator',
      ]);
      foreach ($create_access as $vocabulary_id => $has_access) {
        if (!$has_access) {
          continue;
        }
        $role->grantPermission('add terms in ' . $vocabulary_id);
      }
      $role->save();
    }
    $storage->resetCache();

    // Assert expected permissions.
    $roles = $storage->loadMultiple();
    foreach ($roles_create_access as $role_id => $create_access) {
      foreach ($create_access as $vocabulary_id => $has_access) {
        if ($has_access) {
          $this->assertTrue($roles[$role_id]->hasPermission('add terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has permission to add terms in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
        }
        else {
          $this->assertFalse($roles[$role_id]->hasPermission('add terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has no permission to add terms in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
        }
      }
    }

    // Run update.
    $this->runUpdate(8201, 8202, "Migrate from &#039;Add terms in %vocabulary&#039; permission to Drupal Core permission.");
    $storage->resetCache();

    // Assert expected permissions.
    $roles = $storage->loadMultiple();
    foreach ($roles_create_access as $role_id => $create_access) {
      foreach ($create_access as $vocabulary_id => $has_access) {
        if ($has_access) {
          $this->assertTrue($roles[$role_id]->hasPermission('create terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has permission to create terms in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
        }
        else {
          $this->assertFalse($roles[$role_id]->hasPermission('create terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has no permission to create terms in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
        }
        if ($roles[$role_id]->isAdmin()) {
          continue;
        }
        $this->assertFalse($roles[$role_id]->hasPermission('add terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has no permission to add terms in @vocabulary_id', [
          '@role_id' => $role_id,
          '@vocabulary_id' => $vocabulary_id,
        ]));
      }
    }
  }

}
