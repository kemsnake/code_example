<?php

namespace Drupal\ssp_site\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Removed old users.
 *
 * @QueueWorker(
 *   id = "old_users_queue",
 *   title = @Translation("Old users queue"),
 *   cron = {"time" = 90}
 * )
 */
class OldUsersQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The user's data.
   *
   * @var \Drupal\user\UserData
   */
  protected $userData;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->userData = $container->get('user.data');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $uid = $data->uid;
    $userStorage = $this->entityTypeManager->getStorage('user');
    if ($user = $userStorage->load($uid)) {
      // Block user entity.
      $user->block();
      $user->save();
      // Set flag for user with info about auto-blocking.
      $this->userData->set('ssp_site', $uid, 'inactive_user_auto_blocked', 1);
    }
  }

}
