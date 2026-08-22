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

    $this->drupalGet('admin/config/ai/tools-automation');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('AI Agents');
    $this->assertSession()->linkByHrefExists('/admin/config/ai/tools-automation/agents');
  }

}
