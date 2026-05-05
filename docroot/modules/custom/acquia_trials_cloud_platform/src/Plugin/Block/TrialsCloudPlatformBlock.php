<?php

namespace Drupal\acquia_trials_cloud_platform\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides the Acquia Trials Cloud Platform block.
 */
#[Block(
  id: 'acquia_trials_cloud_platform',
  admin_label: new TranslatableMarkup('Acquia Trials Cloud Platform'),
  category: new TranslatableMarkup('Acquia Trials'),
)]
class TrialsCloudPlatformBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $features = [
      [
        'title' => $this->t('Deploy with Confidence'),
        'description' => $this->t('Push code, databases, and files between environments seamlessly.'),
        'icon' => 'deploy',
      ],
      [
        'title' => $this->t('Secure & Back Up'),
        'description' => $this->t('Automated daily backups and on-demand database snapshots.'),
        'icon' => 'secure',
      ],
      [
        'title' => $this->t('Enterprise Governance'),
        'description' => $this->t('Manage SSH keys, SSL certificates, and HIPAA/GDPR compliance.'),
        'icon' => 'governance',
      ],
    ];

    $subscription_id = getenv('AH_APPLICATION_UUID') ?: '';
    $cta_url = $subscription_id !== ''
      ? 'https://cloud.acquia.com/a/applications/' . $subscription_id
      : 'https://cloud.acquia.com';

    return [
      '#theme' => 'acquia_trials_cloud_platform',
      '#features' => $features,
      '#cta_url' => $cta_url,
      '#attached' => [
        'library' => ['acquia_trials_cloud_platform/cloud-platform'],
      ],
    ];
  }

}
