<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\ai_agents\Entity\AiAgent;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the close control of the tool settings modal on the agent edit form.
 *
 * @group ai_agents
 */
#[RunTestsInSeparateProcesses]
final class AiAgentToolSettingsModalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai_agents'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The tool settings modal renders a close control that the CSS makes visible.
   */
  public function testToolSettingsModalHasVisibleCloseControl(): void {
    AiAgent::create([
      'id' => 'test_agent',
      'label' => 'Test agent',
      'description' => 'Agent that selects a single tool.',
      'system_prompt' => 'Test agent instructions.',
      'secured_system_prompt' => '[ai_agent:agent_instructions]',
      'tools' => ['ai_agent:html_to_markdown' => TRUE],
    ])->save();

    $this->drupalLogin($this->drupalCreateUser([
      'modeler api edit ai_agents_agent',
    ]));

    $this->drupalGet('admin/config/ai/tools-automation/agents/test_agent/edit/form');
    $this->assertSession()->statusCodeEquals(200);

    // Check the close button is present.
    $this->assertSession()->elementExists('css', '#tool-ai_agent_html_to_markdown .ai-modal__header a.ai-modal__close[data-micromodal-close]');
  }

}
