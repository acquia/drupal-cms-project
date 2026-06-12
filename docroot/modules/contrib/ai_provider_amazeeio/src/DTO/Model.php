<?php

namespace Drupal\ai_provider_amazeeio\DTO;

/**
 * An amazee.ai Model with information about which features are supported.
 */
final class Model {

  /**
   * Construct a Model object.
   *
   * @param string $name
   *   The name of the model.
   * @param bool $supportsSystemMessages
   *   Whether the model supports system messages.
   * @param bool $supportsResponseSchema
   *   Whether the model supports response schema.
   * @param bool $supportsVision
   *   Whether the model supports vision.
   * @param bool $supportsFunctionCalling
   *   Whether the model supports function calling.
   * @param bool $supportsToolChoice
   *   Whether the model supports tool choice.
   * @param bool $supportsAssistantPrefill
   *   Whether the model supports assistant prefill.
   * @param bool $supportsPromptCaching
   *   Whether the model supports prompt caching.
   * @param bool $supportsAudioInput
   *   Whether the model supports audio input.
   * @param bool $supportsAudioOutput
   *   Whether the model supports audio output.
   * @param bool $supportsPdfInput
   *   Whether the model supports PDF input.
   * @param bool $supportsEmbeddingImageInput
   *   Whether the model supports embedding image input.
   * @param bool $supportsNativeStreaming
   *   Whether the model supports native streaming.
   * @param bool $supportsWebSearch
   *   Whether the model supports web search.
   * @param bool $supportsUrlContext
   *   Whether the model supports URL context.
   * @param bool $supportsReasoning
   *   Whether the model supports reasoning.
   * @param bool $supportsComputerUse
   *   Whether the model supports computer use.
   * @param bool $supportsEmbeddings
   *   Whether the model supports embeddings.
   * @param bool $supportsChat
   *   Whether the model supports chat.
   * @param bool $supportsImageGeneration
   *   Whether the model supports image generation.
   * @param bool $supportsModeration
   *   Whether the model supports moderation.
   * @param string[] $supportedOpenAiParams
   *   The OpenAI compatible params supported by this model.
   */
  public function __construct(
    public string $name,
    public bool $supportsSystemMessages,
    public bool $supportsResponseSchema,
    public bool $supportsVision,
    public bool $supportsFunctionCalling,
    public bool $supportsToolChoice,
    public bool $supportsAssistantPrefill,
    public bool $supportsPromptCaching,
    public bool $supportsAudioInput,
    public bool $supportsAudioOutput,
    public bool $supportsPdfInput,
    public bool $supportsEmbeddingImageInput,
    public bool $supportsNativeStreaming,
    public bool $supportsWebSearch,
    public bool $supportsUrlContext,
    public bool $supportsReasoning,
    public bool $supportsComputerUse,
    public bool $supportsEmbeddings,
    public bool $supportsChat,
    public bool $supportsImageGeneration,
    public bool $supportsModeration,
    public array $supportedOpenAiParams,
  ) {
  }

  /**
   * Create a Model from an API response object.
   *
   * @param \stdClass $response
   *   The object returned by the API from model info.
   *
   * @return self
   *   A model constructed from the API response.
   */
  public static function createFromResponse(\stdClass $response): self {
    $model_info = $response->model_info;

    return new self(
      name: $response->model_name,
      supportsSystemMessages: $model_info->supports_system_messages ?? FALSE,
      supportsResponseSchema: $model_info->supports_response_schema ?? FALSE,
      supportsVision: $model_info->supports_vision ?? FALSE,
      supportsFunctionCalling: $model_info->supports_function_calling ?? FALSE,
      supportsToolChoice: $model_info->supports_tool_choice ?? FALSE,
      supportsAssistantPrefill: $model_info->supports_assistant_prefill ?? FALSE,
      supportsPromptCaching: $model_info->supports_prompt_caching ?? FALSE,
      supportsAudioInput: $model_info->supports_audio_input ?? FALSE,
      supportsAudioOutput: $model_info->supports_audio_output ?? FALSE,
      supportsPdfInput: $model_info->supports_pdf_input ?? FALSE,
      supportsEmbeddingImageInput: $model_info->supports_embedding_image_input ?? FALSE,
      supportsNativeStreaming: $model_info->supports_native_streaming ?? FALSE,
      supportsWebSearch: $model_info->supports_web_search ?? FALSE,
      supportsUrlContext: $model_info->supports_url_context ?? FALSE,
      supportsReasoning: $model_info->supports_reasoning ?? FALSE,
      supportsComputerUse: $model_info->supports_computer_use ?? FALSE,
      supportsEmbeddings: ($model_info->mode ?? NULL) === 'embedding',
      supportsChat: ($model_info->mode ?? NULL) === 'chat',
      supportsImageGeneration: ($model_info->mode ?? NULL) === 'image_generation',
      supportsModeration: $model_info->supports_moderation ?? FALSE,
      supportedOpenAiParams: $model_info->supported_openai_params ?? [],
    );
  }

}
