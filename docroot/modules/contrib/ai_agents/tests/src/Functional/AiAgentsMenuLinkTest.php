<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the AI Agents link appears under the Tools & Automation section.
 *
 * @group ai_agents
 */
#[RunTestsInSeparateProcesses]
final class AiAgentsMenuLinkTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai_agents'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The AI Agents link is listed on the Tools & Automation page.
   */
  public function testAgentsLinkUnderToolsAndAutomation(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer ai',
      'modeler api collection ai_agents_agent',
    ]));

    // The AI Agents menu link's parent is resolved dynamically by
    // modeler_api against already-persisted menu tree data. When ai,
    // modeler_api and ai_agents are all installed together in a single
    // pass, that lookup can run before the "Tools & Automation" parent
    // link has been written to storage, leaving the derived link
    // mis-parented. A follow-up rebuild re-derives it correctly.
    \Drupal::service('router.builder')->rebuild();
    \Drupal::service('plugin.manager.menu.link')->rebuild();

    $this->drupalGet('admin/config/ai/tools-automation');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('AI Agents');
    $this->assertSession()->linkByHrefExists('/admin/config/ai/tools-automation/agents');
  }

}
