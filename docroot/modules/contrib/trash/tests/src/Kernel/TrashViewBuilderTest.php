<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\trash\TrashViewBuilder;

/**
 * Tests the dynamically built trash listing views.
 *
 * @group trash
 */
class TrashViewBuilderTest extends TrashKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views', 'language'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add a second language so the (translatable) user entity can hold more
    // than one row in users_field_data.
    ConfigurableLanguage::createFromLangcode('fr')->save();
  }

  /**
   * Tests that the owner column lists each entity exactly once.
   */
  public function testOwnerColumnDoesNotDuplicateRows(): void {
    // Ensure the anonymous user (uid 0) account exists, as it does in a real
    // site, then create an account with a translation so that users_field_data
    // holds two rows for that account.
    $this->setUpCurrentUser();
    $account = $this->createUser([], 'author');
    $account->addTranslation('fr', ['name' => 'auteur'])->save();

    // Trash a node owned by the translated account and one owned by anonymous.
    $translated_owner_node = $this->createNode(['type' => 'article', 'uid' => $account->id()]);
    $translated_owner_node->delete();
    $anonymous_owner_node = $this->createNode(['type' => 'article', 'uid' => 0]);
    $anonymous_owner_node->delete();

    // Build the dynamic trash listing for nodes and execute it.
    $executable = $this->container->get(TrashViewBuilder::class)
      ->buildView($this->entityTypeManager->getDefinition('node'));
    $executable->execute('default');

    // The owner is rendered from the base table's reference field rather than a
    // join to users_field_data, which holds one row per user translation. Both
    // trashed nodes must therefore appear exactly once, including the one owned
    // by the translated account and the one owned by anonymous.
    $listed_ids = array_map(static fn ($row) => $row->_entity->id(), $executable->result);
    $this->assertEqualsCanonicalizing([
      $translated_owner_node->id(),
      $anonymous_owner_node->id(),
    ], $listed_ids);
  }

}
