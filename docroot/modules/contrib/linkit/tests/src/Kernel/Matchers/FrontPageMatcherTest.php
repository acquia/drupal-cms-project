<?php

namespace Drupal\Tests\linkit\Kernel\Matchers;

use Drupal\Tests\linkit\Kernel\LinkitKernelTestBase;

/**
 * Tests frontpage matcher.
 *
 * @group linkit
 */
class FrontPageMatcherTest extends LinkitKernelTestBase {

  /**
   * The matcher manager.
   *
   * @var \Drupal\linkit\MatcherManager
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->manager = $this->container->get('plugin.manager.linkit.matcher');
  }

  /**
   * Tests frontpage matcher.
   */
  public function testMatcher() {
    /** @var \Drupal\linkit\MatcherInterface $plugin */
    $plugin = $this->manager->createInstance('front_page', []);

    // Search on / should result in the frontpage as suggested path.
    $suggestions = $plugin->execute('/');
    $this->assertCount(1, $suggestions->getSuggestions());
    $this->assertEquals('/', $suggestions->getSuggestions()[0]->getPath());
    $this->assertEquals('Front page', $suggestions->getSuggestions()[0]->getLabel());

    // Make sure <front> also works.
    $suggestions = $plugin->execute('<front>');
    $this->assertCount(1, $suggestions->getSuggestions());
    $this->assertEquals('/', $suggestions->getSuggestions()[0]->getPath());
    $this->assertEquals('Front page', $suggestions->getSuggestions()[0]->getLabel());

    // Test that query parameters are preserved in the suggested path.
    $suggestions = $plugin->execute('/?test=true');
    $this->assertCount(1, $suggestions->getSuggestions());
    $this->assertEquals('/?test=true', $suggestions->getSuggestions()[0]->getPath());
    $this->assertEquals('Front page', $suggestions->getSuggestions()[0]->getLabel());

    // Test that a fragment is preserved in the suggested path.
    $suggestions = $plugin->execute('/#test');
    $this->assertCount(1, $suggestions->getSuggestions());
    $this->assertEquals('/#test', $suggestions->getSuggestions()[0]->getPath());
    $this->assertEquals('Front page', $suggestions->getSuggestions()[0]->getLabel());

    // Test that query parameters and a fragment are both preserved in the
    // suggested path.
    $suggestions = $plugin->execute('/?test=true#test');
    $this->assertCount(1, $suggestions->getSuggestions());
    $this->assertEquals('/?test=true#test', $suggestions->getSuggestions()[0]->getPath());
    $this->assertEquals('Front page', $suggestions->getSuggestions()[0]->getLabel());
  }

}
