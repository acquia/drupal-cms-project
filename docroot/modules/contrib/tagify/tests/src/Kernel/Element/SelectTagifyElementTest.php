<?php

declare(strict_types=1);

namespace Drupal\Tests\tagify\Kernel\Element;

use Drupal\Tests\tagify\Kernel\TagifyKernelTestBase;

/**
 * Tests the select_tagify form element.
 *
 * @group tagify
 */
final class SelectTagifyElementTest extends TagifyKernelTestBase {

  /**
   * Tests a multiple element without #default_value builds without errors.
   *
   * Without a #default_value, the form builder falls back to '' for #value,
   * which processSelectTagify() must not treat as an array of selected
   * values.
   */
  public function testMultipleWithoutDefaultValue(): void {
    $form = $this->container->get('form_builder')->getForm(SelectTagifyTestForm::class);

    $this->assertSame(['a' => 'A', 'b' => 'B'], $form['tags']['#options']);
  }

}
