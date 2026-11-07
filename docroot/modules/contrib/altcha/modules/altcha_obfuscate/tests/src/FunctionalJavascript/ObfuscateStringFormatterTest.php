<?php

namespace Drupal\Tests\altcha_obfuscate\FunctionalJavascript;

use Drupal\entity_test\Entity\EntityTest;

/**
 * Test the obfuscate string formatter.
 */
class ObfuscateStringFormatterTest extends ObfuscateFormatterTestBase {

  const FIELD_TYPE = 'string';
  const FORMATTER_ID = 'altcha_obfuscated_string';

  /**
   * Test the obfuscate widget with default formatter settings.
   */
  public function testDefaultSettings(): void {
    $field_config = $this->createField(static::FIELD_TYPE, static::FORMATTER_ID);
    $entity = EntityTest::create([$field_config->getName() => 'top secret']);
    $entity->save();

    $this->drupalGet($entity->toUrl());
    $this->validateObfuscateWidgetOnPage();
    $this->assertSession()->elementTextEquals('xpath', '//altcha-widget', 'Click to reveal');
    $this->assertSession()->pageTextNotContains('top secret');
  }

  /**
   * Test the obfuscate widget with different text override formatter settings.
   */
  public function testTextOverrideSettings(): void {
    $field_config = $this->createField(static::FIELD_TYPE, static::FORMATTER_ID);
    $entity = EntityTest::create([$field_config->getName() => 'top secret']);
    $entity->save();

    // Test default text.
    $this->drupalGet($entity->toUrl());
    $this->validateObfuscateWidgetOnPage();
    $this->assertSession()->elementTextEquals('xpath', '//altcha-widget', 'Click to reveal');

    $this->config('altcha.settings')->set('obfuscate_reveal_text', 'Global override')->save();

    // Test the globally configured override.
    $this->drupalGet($entity->toUrl());
    $this->validateObfuscateWidgetOnPage();
    $this->assertSession()->elementTextNotContains('xpath', '//altcha-widget', 'Click to reveal');
    $this->assertSession()->elementTextEquals('xpath', '//altcha-widget', 'Global override');

    $this->updateField(static::FORMATTER_ID, ['reveal_text_override' => 'Reveal me!']);

    // Test formatter override.
    $this->drupalGet($entity->toUrl());
    $this->validateObfuscateWidgetOnPage();
    $this->assertSession()->elementTextNotContains('xpath', '//altcha-widget', 'Click to reveal');
    $this->assertSession()->elementTextNotContains('xpath', '//altcha-widget', 'Global override');
    $this->assertSession()->elementTextEquals('xpath', '//altcha-widget', 'Reveal me!');
  }

}
