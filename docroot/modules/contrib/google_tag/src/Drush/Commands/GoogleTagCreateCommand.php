<?php

declare(strict_types=1);

namespace Drupal\google_tag\Drush\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\google_tag\Entity\TagContainer;
use Drupal\google_tag\GoogleTagEventManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates a Google Tag container and makes it the default container.
 *
 * Drush 13.7 and 14 discover this command and build it with create(). Older
 * versions of Drush do not discover Console commands.
 */
#[AsCommand(
  name: self::NAME,
  description: 'Create a Google Tag container and make it the default container.',
)]
final class GoogleTagCreateCommand extends Command {

  /**
   * The command name.
   */
  public const NAME = 'google_tag:create';

  /**
   * Constructs a GoogleTagCreateCommand object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\google_tag\GoogleTagEventManager $eventManager
   *   The Google Tag event plugin manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The Drush logger.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly GoogleTagEventManager $eventManager,
    private readonly LoggerInterface $logger,
  ) {
    parent::__construct();
  }

  /**
   * Creates the command.
   *
   * Written out rather than taken from the Drush AutowireTrait, which later
   * versions of Drush remove, so that the class has no Drush dependency.
   *
   * @param \Psr\Container\ContainerInterface $container
   *   The Drush container, which also provides the Drupal services.
   *
   * @return self
   *   The command.
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('plugin.manager.google_tag_event'),
      $container->get(LoggerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setHelp('The container gets the same defaults as one added through the user interface: every event is enabled with its default settings, and no insertion conditions are set. The machine name of the new container is printed. The command fails if another container already uses the tag ID, unless --force is given.')
      ->addOption('id', NULL, InputOption::VALUE_REQUIRED, 'The Google Tag ID, for example GTM-XXXXXXX or G-XXXXXXXX.')
      ->addOption('label', NULL, InputOption::VALUE_REQUIRED, 'The container label. Defaults to the tag ID.')
      ->addOption('force', NULL, InputOption::VALUE_NONE, 'Create the container even if another container already uses the tag ID.')
      ->addUsage('--id=GTM-XXXXXXX --label="Default Container"');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $tag_id = trim((string) $input->getOption('id'));
    if ($tag_id === '') {
      $this->logger->error('You must provide a Google Tag ID, for example --id=GTM-XXXXXXX.');
      return self::FAILURE;
    }
    // Match the tag ID field on the container form: the whole value must
    // match the pattern, and it is limited to 20 characters.
    if (strlen($tag_id) > 20 || !preg_match('/^(?:' . TagContainer::GOOGLE_TAG_MATCH . ')$/', $tag_id)) {
      $this->logger->error(sprintf('"%s" is not a valid Google Tag ID. It must be in the form of GTM-xxxxxxx, G-xxxxxxxx, AW-xxxxxxxxx, DC-xxxxxxxx, GT-xxxxxxxx or UA-xxxxx-yy.', $tag_id));
      return self::FAILURE;
    }
    $storage = $this->entityTypeManager->getStorage('google_tag_container');
    // Running the command again, for example from a provisioning script,
    // would otherwise add another container and make it the default.
    if (!$input->getOption('force')) {
      foreach ($storage->loadMultiple() as $existing) {
        $existing_ids = array_map(static fn ($id) => trim((string) $id), $existing->get('tag_container_ids') ?? []);
        if (in_array($tag_id, $existing_ids, TRUE)) {
          $this->logger->error(sprintf('The Google Tag ID "%s" is already used by the container "%s" (%s). Use --force to create another container for it.', $tag_id, $existing->label(), $existing->id()));
          return self::FAILURE;
        }
      }
    }
    $label = trim((string) $input->getOption('label'));

    $events = [];
    foreach (array_keys($this->eventManager->getDefinitions()) as $event_id) {
      /** @var \Drupal\google_tag\Plugin\GoogleTag\Event\GoogleTagEventInterface $event */
      $event = $this->eventManager->createInstance($event_id);
      $events[$event_id] = $event->getConfiguration();
    }

    // Generate the machine name the same way TagContainerForm does.
    $container = $storage->create([
      'id' => uniqid($tag_id . '.', TRUE),
      'label' => $label !== '' ? $label : $tag_id,
      'tag_container_ids' => [$tag_id],
      'events' => $events,
    ]);
    $container->save();

    $this->configFactory->getEditable('google_tag.settings')
      ->set('default_google_tag_entity', $container->id())
      ->save();

    $this->logger->notice(sprintf('Created Google Tag container "%s" (%s) and set it as the default container.', $container->label(), $container->id()));
    // Print only the machine name, so scripts can capture it.
    $output->writeln((string) $container->id());
    return self::SUCCESS;
  }

}
