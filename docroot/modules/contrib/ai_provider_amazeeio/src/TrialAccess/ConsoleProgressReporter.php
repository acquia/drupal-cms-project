<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\TrialAccess;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

/**
 * Shows progress on the console.
 *
 * @internal
 */
final class ConsoleProgressReporter implements ProgressReporterInterface {

  /**
   * The console output.
   */
  private ?ConsoleOutputInterface $output = NULL;

  /**
   * The console section output.
   */
  private ?ConsoleSectionOutput $section = NULL;

  /**
   * The progress bar.
   */
  private ?ProgressBar $bar = NULL;

  /**
   * {@inheritdoc}
   */
  public function start(string $message): void {
    $this->output = new ConsoleOutput();
    $this->section = $this->output->section();

    $this->section->writeln($message);

    $this->bar = new ProgressBar($this->section, 100);

    // Keep the output stable and readable.
    $this->bar->setBarCharacter('=');
    $this->bar->setProgressCharacter('>');
    $this->bar->setEmptyBarCharacter('-');
    $this->bar->setFormat(' %percent%% [%bar%] %message%');
    $this->bar->setMessage('Waiting for API response...');

    $this->bar->start();
  }

  /**
   * {@inheritdoc}
   */
  public function advance(int $steps = 1): void {
    if ($steps < 1) {
      $steps = 1;
    }

    // Don't update the progress bar if it's already at 100%.
    if ($this->bar && $this->bar->getProgress() < 100) {
      $this->bar->advance($steps);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function finish(string $message = ''): void {
    if ($this->bar) {
      $this->bar->finish();
    }

    if ($this->section) {
      $this->section->writeln('');
      if ($message !== '') {
        $this->section->writeln('<info>' . $message . '</info>');
      }
    }

    $this->bar = NULL;
    $this->section = NULL;
    $this->output = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function info(string $message): void {
    if (!$this->output) {
      $this->output = new ConsoleOutput();
    }
    $this->output->writeln('<info>' . $message . '</info>');
  }

  /**
   * {@inheritdoc}
   */
  public function error(string $message): void {
    if (!$this->output) {
      $this->output = new ConsoleOutput();
    }
    $this->output->writeln('<error>' . $message . '</error>');
  }

}
