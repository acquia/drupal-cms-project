<?php

namespace Drupal\ai_seo\Controller;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\Response\AiStreamedResponse;
use Drupal\ai_seo\AiSeoAnalyzer;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams an AI SEO/GEO analysis as Server-Sent Events.
 *
 * The browser opens an EventSource to this endpoint. Chunks are emitted as
 * they arrive from the AI provider, so the user sees progress rather than
 * staring at a blank spinner for 60 seconds. After the final chunk the
 * controller converts the accumulated markdown to HTML, saves the report, and
 * emits a {"type":"done","redirect":"..."} event so JS can redirect.
 */
class StreamAnalysisController extends ControllerBase {

  public function __construct(
    protected AiSeoAnalyzer $analyzer,
    protected PrivateTempStoreFactory $tempStoreFactory,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ai_seo.service'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * SSE streaming endpoint.
   */
  public function stream(NodeInterface $node, Request $request): AiStreamedResponse|Response {
    // Validate CSRF token manually (same seed used in AnalyzeNodeForm::buildForm).
    if (!\Drupal::csrfToken()->validate($request->query->get('token', ''), 'ai-seo-stream')) {
      return new Response('Invalid or missing CSRF token.', Response::HTTP_FORBIDDEN);
    }

    $report_type = $request->query->get('report_type', 'full');
    $revision_id = ($v = $request->query->get('revision_id')) ? (int) $v : NULL;
    $langcode = $request->query->get('langcode') ?: NULL;
    $request_as_anonymous = (bool) $request->query->get('request_as_anonymous', 1);

    $options = [
      'request_as_anonymous' => $request_as_anonymous,
      'report_type' => $report_type,
    ];

    try {
      $prepared = $this->analyzer->prepareEntityAnalysis(
        $report_type,
        'node',
        (int) $node->id(),
        $revision_id,
        'full',
        $langcode,
        $options,
      );
    }
    catch (\Exception $e) {
      $this->getLogger('ai_seo')->error('Entity analysis preparation failed: @message', ['@message' => $e->getMessage()]);
      return new Response(
        'data: ' . json_encode(['type' => 'error', 'message' => $e->getMessage()]) . "\n\n",
        Response::HTTP_OK,
        ['Content-Type' => 'text/event-stream; charset=UTF-8'],
      );
    }

    $analyzer = $this->analyzer;
    $logger = $this->getLogger('ai_seo');
    $redirect_url = Url::fromRoute('entity.node.seo_analyzer', ['node' => $node->id()])->toString();
    $entity_id = (int) $node->id();

    $callback = function () use ($prepared, $analyzer, $logger, $redirect_url, $entity_id, $revision_id, $langcode, $options) {
      $full_text = '';

      try {
        $provider = $prepared['provider'];
        $provider->setChatSystemRole($prepared['system_prompt']);

        $messages = new ChatInput([
          new ChatMessage('user', $prepared['prompt']),
          new ChatMessage('user', $prepared['cleaned_html']),
        ]);
        $messages->setStreamedOutput(TRUE);

        $normalized = $provider->chat($messages, $prepared['model'])->getNormalized();

        if ($normalized instanceof StreamedChatMessageIteratorInterface) {
          foreach ($normalized as $chunk) {
            $text = $chunk->getText();
            if ($text !== '') {
              $full_text .= $text;
              echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $text]) . "\n\n";
              flush();
            }
          }
        }
        else {
          // Provider returned a non-streamed response (fallback).
          $full_text = $normalized->getText();
          echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $full_text]) . "\n\n";
          flush();
        }

        $full_text = $analyzer->stripCodeBlockWrapper($full_text);

        $converter = new CommonMarkConverter();
        $html_result = trim($converter->convert($full_text));

        if (!empty($html_result)) {
          $analyzer->persistReport(
            $html_result,
            $prepared['prompt'],
            $prepared['cleaned_html'],
            NULL,
            'node',
            $entity_id,
            $revision_id,
            $langcode,
            $options,
          );
        }

        echo 'data: ' . json_encode(['type' => 'done', 'redirect' => $redirect_url]) . "\n\n";
        flush();
      }
      catch (\Exception $e) {
        $logger->error('Entity analysis failed: @message', ['@message' => $e->getMessage()]);
        echo 'data: ' . json_encode(['type' => 'error', 'message' => 'Analysis failed. Please try again.']) . "\n\n";
        flush();
      }
    };

    return new AiStreamedResponse($callback, 200, [
      'Content-Type' => 'text/event-stream; charset=UTF-8',
    ]);
  }

  /**
   * SSE streaming endpoint for focused single-field SEO/GEO analysis.
   *
   * Expects a tempstore key written by ai_seo_analyze_field_ajax(). Streams
   * a concise, field-specific analysis and emits {"type":"done_draft"} on
   * completion — no save, no redirect, the modal stays open for reading.
   */
  public function streamField(Request $request): AiStreamedResponse|Response {
    if (!\Drupal::csrfToken()->validate($request->query->get('token', ''), 'ai-seo-field-stream')) {
      return new Response('Invalid or missing CSRF token.', Response::HTTP_FORBIDDEN);
    }

    $key = $request->query->get('key', '');
    if (empty($key)) {
      return new Response('Missing key parameter.', Response::HTTP_BAD_REQUEST);
    }

    $tempStore = $this->tempStoreFactory->get('ai_seo');
    $data = $tempStore->get($key);

    if (empty($data)) {
      return new Response(
        'data: ' . json_encode(['type' => 'error', 'message' => 'Analysis data expired. Please try again.']) . "\n\n",
        Response::HTTP_OK,
        ['Content-Type' => 'text/event-stream; charset=UTF-8'],
      );
    }

    $tempStore->delete($key);

    $analyzer = $this->analyzer;

    try {
      $prepared = $this->analyzer->prepareFieldAnalysis($data);
    }
    catch (\Exception $e) {
      return new Response(
        'data: ' . json_encode(['type' => 'error', 'message' => $e->getMessage()]) . "\n\n",
        Response::HTTP_OK,
        ['Content-Type' => 'text/event-stream; charset=UTF-8'],
      );
    }

    $callback = function () use ($prepared, $analyzer) {
      try {
        $provider = $prepared['provider'];
        $provider->setChatSystemRole($prepared['system_prompt']);

        $messages = new ChatInput([
          new ChatMessage('user', $prepared['prompt']),
        ]);
        $messages->setStreamedOutput(TRUE);

        $normalized = $provider->chat($messages, $prepared['model'])->getNormalized();

        if ($normalized instanceof StreamedChatMessageIteratorInterface) {
          foreach ($normalized as $chunk) {
            $text = $chunk->getText();
            if ($text !== '') {
              echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $text]) . "\n\n";
              flush();
            }
          }
        }
        else {
          $full_text = $normalized->getText();
          echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $full_text]) . "\n\n";
          flush();
        }

        echo 'data: ' . json_encode(['type' => 'done_draft']) . "\n\n";
        flush();
      }
      catch (\Exception $e) {
        echo 'data: ' . json_encode(['type' => 'error', 'message' => 'Analysis failed. Please try again.']) . "\n\n";
        flush();
      }
    };

    return new AiStreamedResponse($callback, 200, [
      'Content-Type' => 'text/event-stream; charset=UTF-8',
    ]);
  }

  /**
   * SSE streaming endpoint for draft (unsaved) content analysis.
   *
   * Expects a tempstore key written by the AJAX callback in ai_seo.module.
   * Streams analysis chunks, then emits {"type":"done_draft"} — no redirect,
   * the modal stays open so the user can read the report in place.
   */
  public function streamDraft(Request $request): AiStreamedResponse|Response {
    if (!\Drupal::csrfToken()->validate($request->query->get('token', ''), 'ai-seo-draft-stream')) {
      return new Response('Invalid or missing CSRF token.', Response::HTTP_FORBIDDEN);
    }

    $key = $request->query->get('key', '');
    if (empty($key)) {
      return new Response('Missing key parameter.', Response::HTTP_BAD_REQUEST);
    }

    $tempStore = $this->tempStoreFactory->get('ai_seo');
    $html = $tempStore->get($key);

    if (empty($html)) {
      return new Response(
        'data: ' . json_encode(['type' => 'error', 'message' => 'Draft data expired. Please try again.']) . "\n\n",
        Response::HTTP_OK,
        ['Content-Type' => 'text/event-stream; charset=UTF-8'],
      );
    }

    $tempStore->delete($key);

    $analyzer = $this->analyzer;
    $logger = $this->getLogger('ai_seo');

    try {
      $prepared = $this->analyzer->prepareHtmlAnalysis($html);
    }
    catch (\Exception $e) {
      $logger->error('Draft analysis preparation failed: @message', ['@message' => $e->getMessage()]);
      return new Response(
        'data: ' . json_encode(['type' => 'error', 'message' => $e->getMessage()]) . "\n\n",
        Response::HTTP_OK,
        ['Content-Type' => 'text/event-stream; charset=UTF-8'],
      );
    }

    $callback = function () use ($prepared, $analyzer, $logger) {
      try {
        $provider = $prepared['provider'];
        $provider->setChatSystemRole($prepared['system_prompt']);

        $messages = new ChatInput([
          new ChatMessage('user', $prepared['prompt']),
          new ChatMessage('user', $prepared['cleaned_html']),
        ]);
        $messages->setStreamedOutput(TRUE);

        $normalized = $provider->chat($messages, $prepared['model'])->getNormalized();

        if ($normalized instanceof StreamedChatMessageIteratorInterface) {
          foreach ($normalized as $chunk) {
            $text = $chunk->getText();
            if ($text !== '') {
              echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $text]) . "\n\n";
              flush();
            }
          }
        }
        else {
          $full_text = $normalized->getText();
          echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $full_text]) . "\n\n";
          flush();
        }

        // Draft analysis is intentionally NOT persisted.
        echo 'data: ' . json_encode(['type' => 'done_draft']) . "\n\n";
        flush();
      }
      catch (\Exception $e) {
        $logger->error('Draft analysis failed: @message', ['@message' => $e->getMessage()]);
        echo 'data: ' . json_encode(['type' => 'error', 'message' => 'Analysis failed. Please try again.']) . "\n\n";
        flush();
      }
    };

    return new AiStreamedResponse($callback, 200, [
      'Content-Type' => 'text/event-stream; charset=UTF-8',
    ]);
  }

}
