<?php

declare(strict_types=1);

namespace Drupal\Tests\recipe_locale\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\locale\LocaleDefaultOptions;
use Drupal\locale\LocaleFetch;
use Drupal\locale\LocaleProjectRepository;
use Drupal\recipe_locale\RecipeTracker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that Locale downloads a recipe's translation from the right URL.
 */
#[Group('recipe_locale')]
#[RunTestsInSeparateProcesses]
final class TranslationDownloadTest extends RecipeLocaleKernelTestBase {

  /**
   * The queue of canned responses.
   */
  private MockHandler $responses;

  /**
   * The requests that were made, as Guzzle's history middleware records them.
   *
   * @var array<int, array{request: \Psr\Http\Message\RequestInterface}>
   */
  private array $history = [];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $this->responses ??= new MockHandler();
    $stack = HandlerStack::create($this->responses);
    $stack->push(Middleware::history($this->history));
    $container->set('http_client_factory', new TestClientFactory($stack));
    $container->set('http_client', new Client(['handler' => $stack]));
  }

  /**
   * Tests the check and download of one recipe translation.
   */
  public function testDownload(): void {
    $this->config(RecipeTracker::CONFIG_NAME)
      ->set('recipes', [
        'my_recipe' => ['package' => 'drupal/my_recipe', 'version' => '1.x-dev', 'label' => 'My recipe'],
      ])
      ->save();
    $this->container->get(LocaleProjectRepository::class)->buildProjects();

    // The remote check is a HEAD request, the download a GET request.
    $this->responses->append(
      new Response(200, ['Last-Modified' => 'Mon, 01 Sep 2026 10:00:00 GMT']),
      new Response(200, [], "msgid \"\"\nmsgstr \"\"\n"),
    );

    $fetch = $this->container->get(LocaleFetch::class);
    $options = LocaleDefaultOptions::updateOptions();
    $context = [];
    $fetch->batchStatusCheck('my_recipe', 'de', $options, $context);
    $fetch->batchDownload('my_recipe', 'de', $context);

    $expected = 'https://ftp.drupal.org/files/translations/all/my_recipe/my_recipe-1.x.de.po';
    $this->assertCount(2, $this->history);
    $this->assertSame('HEAD', $this->history[0]['request']->getMethod());
    $this->assertSame($expected, (string) $this->history[0]['request']->getUri());
    $this->assertSame('GET', $this->history[1]['request']->getMethod());
    $this->assertSame($expected, (string) $this->history[1]['request']->getUri());
    $this->assertFileExists($this->translationsDirectory . '/my_recipe-1.x.de.po');
    $this->assertArrayNotHasKey('failed_files', $context['results'] ?? []);
  }

}
