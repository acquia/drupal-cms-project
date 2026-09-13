<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_email_override\Kernel;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\easy_email_override\Service\MailManager;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

#[Group('easy_email_override')]
class ServicesTest extends KernelTestBase {

  /**
   * @inheritDoc
   */
  protected static $modules = ['easy_email_override'];

  /**
   * Tests the mail manager decorator.
   */
  #[TestWith([MailManagerInterface::class])]
  #[TestWith(['plugin.manager.mail'])]
  public function testMailManagerDecorator(string $service_id): void {
    $manager = $this->container->get($service_id);
    $this->assertInstanceOf(MailManager::class, $manager);
    // This core-provided mail backend should be available, which confirms that
    // the decorated plugin manager's discovery is preserved.
    $this->assertTrue($manager->hasDefinition('php_mail'));
  }

}
