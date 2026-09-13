<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Drush\Commands;

use Drupal\Core\DefaultContent\Exporter;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drupal_cms_helper\ContentLoader;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @api
 *   The `content:export:all` command is part of Drupal CMS's developer-facing
 *   API and may be relied upon.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
#[AsCommand(
  name: 'content:export:all',
  description: "Exports all content to a directory.",
  hidden: TRUE,
)]
final class ExportAllContentCommand extends Command {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    private readonly ClassResolverInterface $classResolver,
    private readonly Exporter $exporter,
  ) {
    parent::__construct();
  }

  #[\Override]
  protected function configure(): void {
    $this->addArgument(
      'dir',
      InputArgument::REQUIRED,
      'The path of the content directory, either absolute or relative to the Drupal root.',
    );
  }

  #[\Override]
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $directory = $input->getArgument('dir');

    $loader = $this->classResolver->getInstanceFromDefinition(ContentLoader::class);
    foreach ($loader as $entity) {
      $this->exporter->exportWithDependencies($entity, $directory);
    }

    $message = (string) $this->t('All content was exported to @directory.', [
      '@directory' => $directory,
    ]);
    $output->writeln($message);
    return self::SUCCESS;
  }

}
