# AI Image Alt Text

AI-generated alt text for images using vision models with human-in-the-loop
verification.

## Features

- **AI Vision Models**: Generate alt text using AI vision providers
- **Human-in-the-loop**: Manual verification before saving
- **Multi-language Support**: Alt text in entity's language when available
- **Widget Integration**: Works with image widgets including Focal Point and
  ImageWidget Crop
- **Permission Control**: Role-based access to AI generation

## Requirements

- [AI](https://www.drupal.org/project/ai) module with vision model provider
- Compatible AI providers: OpenAI, Anthropic, Fireworks AI

## Installation

```bash
composer require drupal/ai_image_alt_text
drush en ai_image_alt_text
```

## Configuration

1. Configure AI provider with vision capabilities at
   `/admin/config/ai/providers`
2. Set permissions at `/admin/people/permissions`
3. Configure module settings at `/admin/config/ai/ai_image_alt_text`
4. Customize prompts and image preprocessing options

## Usage

1. Navigate to any content with image fields
2. Upload or select an image
3. Click "Generate Alt Text" button (appears for users with permissions)
4. Review and edit the generated alt text
5. Save the content

## SEO Benefits

- Improves accessibility compliance
- Enhances search engine optimization
- Better image search rankings
- Consistent alt text quality across site
- Faster content publishing workflow

## Similar Projects

- [Automatic Alternative Text](https://www.drupal.org/project/auto_alter) - Uses Azure
  Computer Vision with automatic saving
