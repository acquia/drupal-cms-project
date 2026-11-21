<?php

namespace Drupal\ai_image_alt_text\Controller;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai_image_alt_text\ProviderHelper;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Generates alt text for an image file using an AI provider.
 */
class GenerateAltText extends ControllerBase {

  /**
   * Maximum length of the file name that is passed into the prompt.
   */
  const MAX_FILENAME_LENGTH = 100;

  /**
   * AI module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $aiConfig;

  /**
   * AI image alt text configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $altTextConfig;

  /**
   * AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * Twig engine.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected TwigEnvironment $twig;

  /**
   * The AI provider helper.
   *
   * @var \Drupal\ai_image_alt_text\ProviderHelper
   */
  protected ProviderHelper $providerHelper;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->languageManager = $container->get('language_manager');
    $instance->aiConfig = $container->get('config.factory')->get('ai.settings');
    $instance->altTextConfig = $container->get('config.factory')->get('ai_image_alt_text.settings');
    $instance->aiProviderManager = $container->get('ai.provider');
    $instance->twig = $container->get('twig');
    $instance->providerHelper = $container->get('ai_image_alt_text.provider');
    $instance->logger = $container->get('logger.factory')->get('ai_image_alt_text');
    return $instance;
  }

  /**
   * Create an ai image alt text.
   *
   * @param \Drupal\file\FileInterface|null $file
   *   File entity.
   * @param string $lang_code
   *   Language code for alt text generation.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function generate(?FileInterface $file = NULL, $lang_code = 'en') {
    // Check that the user has access to the file.
    if (!$file || !$file->access('view')) {
      return $this->error($this->t('The file does not exist or you do not have access to it.'), 403);
    }
    // Check that the file is an image.
    if (!$file->getMimeType() || strpos($file->getMimeType(), 'image/') !== 0) {
      return $this->error($this->t('The file is not an image.'), 400);
    }

    // Check if there is a preferred model.
    $data = $this->providerHelper->getSetProvider();
    if (!$data) {
      return $this->error($this->t('No AI provider found.'), 500);
    }
    $provider = $data['provider_id'];
    $model = $data['model_id'];

    // Get the configuration.
    $prompt = (string) $this->altTextConfig->get('prompt');
    $image_style_name = $this->altTextConfig->get('image_style');

    try {
      $image = $this->buildImage($file, $image_style_name);
    }
    catch (\Throwable $e) {
      $this->logger->error('Could not prepare image @fid for alt text generation: @message', [
        '@fid' => $file->id(),
        '@message' => $e->getMessage(),
      ]);
      return $this->error($this->t('The image could not be prepared for alt text generation.'), 500);
    }

    // Only accept language codes known to the site. Anything else falls back
    // to the default language, so the request cannot inject text into the
    // prompt through the URL.
    $language = $this->languageManager->getLanguage($lang_code) ?? $this->languageManager->getDefaultLanguage();

    $prompt_text = $this->twig->renderInline($prompt, [
      'entity_lang_name' => $language->getName(),
      'filename' => $this->sanitizeFilename($file->getFilename()),
    ]);

    $input = new ChatInput([
      new ChatMessage('user',
        (string) $prompt_text,
        [$image]
      ),
    ]);
    try {
      $output = $provider->chat($input, $model, ['ai_image_alt_text']);
      $alt_text = $output->getNormalized()->getText();
      return new JsonResponse([
        'alt_text' => trim((string) $alt_text),
      ]);
    }
    catch (\Throwable $e) {
      // Provider exceptions may contain request details, endpoints or parts
      // of API responses. Log them and give the client a generic message.
      $this->logger->error('AI alt text generation failed for file @fid: @message', [
        '@fid' => $file->id(),
        '@message' => $e->getMessage(),
      ]);
      return $this->error($this->t('The AI provider could not generate an alt text. Please try again later.'), 500);
    }
  }

  /**
   * Builds the image that is sent to the provider.
   *
   * @param \Drupal\file\FileInterface $file
   *   The original file.
   * @param string|null $image_style_name
   *   The image style to apply, or empty to send the original.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile
   *   The image.
   */
  protected function buildImage(FileInterface $file, ?string $image_style_name): ImageFile {
    $image = new ImageFile();
    if (!$image_style_name) {
      $image->setFileFromFile($file);
      return $image;
    }

    $image_style = $this->entityTypeManager->getStorage('image_style')->load($image_style_name);
    if (!$image_style instanceof ImageStyleInterface) {
      throw new \RuntimeException(sprintf('Image style "%s" does not exist.', $image_style_name));
    }
    $image_uri = $file->getFileUri();
    $scaled_image_uri = $image_style->buildUri($image_uri);
    if (!file_exists($scaled_image_uri) && !$image_style->createDerivative($image_uri, $scaled_image_uri)) {
      throw new \RuntimeException('The image derivative could not be created.');
    }
    $binary = file_get_contents($scaled_image_uri);
    if ($binary === FALSE) {
      throw new \RuntimeException('The image derivative could not be read.');
    }
    $image->setBinary($binary);
    $image->setMimeType((string) mime_content_type($scaled_image_uri));
    $image->setFilename(basename($scaled_image_uri));
    return $image;
  }

  /**
   * Reduces the user supplied file name to a short, single line string.
   *
   * The file name is chosen by whoever uploaded the file, and it is passed
   * into the prompt. Removing control characters and limiting the length
   * reduces the room for prompt injection.
   *
   * @param string $filename
   *   The file name.
   *
   * @return string
   *   The sanitized file name.
   */
  protected function sanitizeFilename(string $filename): string {
    $filename = preg_replace('/[\p{C}]+/u', ' ', $filename) ?? '';
    return Unicode::truncate(trim($filename), static::MAX_FILENAME_LENGTH, FALSE, TRUE);
  }

  /**
   * Builds a JSON error response.
   *
   * @param string|\Stringable $message
   *   The message.
   * @param int $status
   *   The HTTP status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  protected function error(string|\Stringable $message, int $status): JsonResponse {
    return new JsonResponse(['error' => (string) $message], $status);
  }

}
