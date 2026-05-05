<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Build;

use Drupal\automatic_updates\ConsoleUpdateSandboxManager;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Tests updating core using the command-line auto-update utility.
 */
#[Group('automatic_updates')]
final class ConsoleCoreUpdateTest extends CoreUpdateTestBase {

  /**
   * Tests updating via the console directly.
   */
  public function testConsoleUpdate(): void {
    $this->createTestProject('RecommendedProject');

    $command = [
      (new PhpExecutableFinder())->find(),
      $this->getWebRoot() . '/core/scripts/auto-update',
      '--verbose',
    ];
    // BEGIN: DELETE FROM CORE MERGE REQUEST
    // Use the `auto-update` command proxy that Composer puts into `vendor/bin`,
    // just to prove that it works.
    $bin_dir = $this->runComposer('composer config bin-dir --absolute', 'project');
    $command[1] = trim($bin_dir) . '/auto-update';
    // END: DELETE FROM CORE MERGE REQUEST

    $process = (new Process($command))
      ->setWorkingDirectory($this->getWorkingPath('project'))
      // Give the update process as much time as it needs to run.
      ->setTimeout(NULL);

    $output = $process->mustRun()->getOutput();
    $this->assertStringContainsString('Updating Drupal core to 9.8.1. This may take a while.', $output);
    $this->assertStringContainsString('Running post-apply tasks and final clean-up...', $output);
    $this->assertStringContainsString('Drupal core was successfully updated to 9.8.1!', $output);
    $this->assertStringContainsString('Deleting unused stage directories...', $output);
    $this->assertUpdateSuccessful('9.8.1');
    $this->assertExpectedStageEventsFired(ConsoleUpdateSandboxManager::class);

    $pattern = '/^Unused stage directory deleted: (.+)$/m';
    $matches = [];
    preg_match($pattern, $output, $matches);
    $this->assertCount(2, $matches, $output);
    $this->assertDirectoryDoesNotExist($matches[1]);

    // Rerunning the command should exit with a message that no newer version
    // is available.
    $output = $process->mustRun()->getOutput();
    $this->assertStringContainsString("There is no Drupal core update available.", $output);
    // Any defunct stage directories should still be cleaned up (even though
    // there aren't any left).
    $this->assertStringContainsString('Deleting unused stage directories...', $output);
    $this->assertDoesNotMatchRegularExpression($pattern, $output);
  }

}
