<?php

namespace Drupal\opigno_dashboard\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * About Opigno block.
 *
 * @Block(
 *  id = "opigno_about_block",
 *  admin_label = @Translation("About Opigno block"),
 *  category = @Translation("Opigno"),
 * )
 *
 * @package Drupal\opigno_dashboard\Plugin\Block
 */
class AboutOpignoBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountInterface $account, ...$default) {
    parent::__construct(...$default);
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('current_user'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ($this->account->isAnonymous()) {
      return [
        '#cache' => ['max-age' => -1],
      ];
    }

    $build = [
      '#cache' => [
        'tags' => $this->getCacheTags(),
        'contexts' => $this->getCacheContexts(),
      ],
    ];

    // The block should be accessible only for admin users.
    if (!$this->account->hasPermission('access administration pages')) {
      return $build;
    }

    $logo = theme_get_setting('logo_path_anonymous');
    if (!is_file($logo)) {
      $logo = \Drupal::service('extension.list.theme')->getPath('aristotle') . '/assets/' . $logo;
    }

    $options = [
      'attributes' => ['target' => '_blank'],
    ];
    $url = Url::fromUri('https://www.opigno.org', $options);

    return [
      '#theme' => 'opigno_about_block',
      '#logo' => file_exists($logo) ? \Drupal::service('file_url_generator')->transformRelative(\Drupal::service('file_url_generator')->generateAbsoluteString($logo)) : '',
      '#texts' => [
        $this->t('Opigno™ is a Trademark of Connect-i Sàrl, based in Préverenges, Switzerland.'),
        $this->t('For more information regarding Opigno™ please consult the website @link.', [
          '@link' => Link::fromTextAndUrl('www.opigno.org', $url)->toString(),
        ]),
      ],
      '#version' => function_exists('opigno_lms_get_current_opigno_lms_release') ? opigno_lms_get_current_opigno_lms_release() : '',
    ] + $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['user' . (int) $this->account->id()]);
  }

}
