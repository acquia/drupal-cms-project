<?php

namespace Drupal\Tests\altcha_obfuscate\FunctionalJavascript;

use Drupal\entity_test\Entity\EntityTest;

/**
 * Test the obfuscate telephone formatter.
 */
class ObfuscateTelephoneFormatterTest extends ObfuscateFormatterTestBase {

  const FIELD_TYPE = 'telephone';
  const FORMATTER_ID = 'altcha_obfuscated_telephone';

  /**
   * Test the obfuscate widget with default formatter settings.
   */
  public function testDefaultSettings(): void {
    $field_config = $this->createField(static::FIELD_TYPE, static::FORMATTER_ID);
    $entity = EntityTest::create([$field_config->getName() => '+32012345678']);
    $entity->save();

    $this->drupalGet($entity->toUrl());
    $this->validateObfuscateWidgetOnPage();
    $this->assertSession()->elementTextEquals('xpath', '//altcha-widget', 'Click to reveal');
    $this->assertSession()->pageTextNotContains('+32012345678');
  }

  /**
   * Test the obfuscate widget with different text override formatter settings.
   */
  public function testTextOverrideSettings(): void {
    $field_config = $this->createField(static::FIELD_TYPE, static::FORMATTER_ID);
    $entity = EntityTest::create([$field_config->getName() => '+32012345678']);
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

    $this->updateField(static::FORMATTER_ID, ['reveal_text_override' => 'Reveal telephone number']);

    // Test formatter override.
    $this->drupalGet($entity->toUrl());
    $this->validateObfuscateWidgetOnPage();
    $this->assertSession()->elementTextNotContains('xpath', '//altcha-widget', 'Click to reveal');
    $this->assertSession()->elementTextNotContains('xpath', '//altcha-widget', 'Global override');
    $this->assertSession()->elementTextEquals('xpath', '//altcha-widget', 'Reveal telephone number');
  }

}
