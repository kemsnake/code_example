<?php

namespace Drupal\ssp_site\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Remove inactive users.
 *
 * @QueueWorker(
 *   id = "inactive_users_queue",
 *   title = @Translation("Inactive users queue"),
 *   cron = {"time" = 90}
 * )
 */
class InactiveUsersQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The users data.
   *
   * @var \Drupal\user\UserData
   */
  protected $userData;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('ssp_inactive_users');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->configFactory = $container->get('config.factory');
    $instance->mailManager = $container->get('plugin.manager.mail');
    $instance->requestStack = $container->get('request_stack');
    $instance->userData = $container->get('user.data');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $uid = $data->uid;
    $notify_number = $data->notify_number;

    // Send notification for current user from item.
    $mail = $this->sendNotification($uid, $notify_number);
    if ($mail['result']) {
      $this->userData->set('ssp_site', $uid, 'inactive_user_sent_email', $notify_number + 1);
    }
    // Added item back if email didn't send.
    else {
      // Recreate queue item if email not sent.
      throw new RequeueException();
    }
  }

  /**
   * Helper function for send notifications.
   *
   * @param int $uid
   *   User id.
   * @param string $status
   *   Status of notification stage.
   *
   * @return mixed
   *   Result of mail send to user.
   */
  protected function sendNotification($uid, $status) {
    // Get info about current stage from config.
    $stagesConf = $this->configFactory->get('ssp_site.inactive_users')->get('stages');
    $stages = explode("\r\n", $stagesConf);
    $stage = explode("|", $stages[$status]);

    $userStorage = $this->entityTypeManager->getStorage('user');
    $account = $userStorage->load($uid);
    $template = $this->configFactory->get('ssp_site.inactive_users')->get('email_template');
    // Prepare email template from config and replace tokens.
    $placeholders = [
      '@name' => $account->getAccountName(),
      '@inactive-period' => $stage[3],
      '@remaining-period' => $stage[4],
      '@site-url' => $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost(),
    ];

    if ($template !== NULL) {
      foreach ($placeholders as $placeholder => $value) {
        $template = str_replace($placeholder, $value, $template);
      }
    }

    $params['message'] = $template;
    $params['subject'] = $this->t('Your account will expire in @period', ['@period' => $placeholders['@remaining-period']]);
    $langcode = $account->getPreferredLangcode();
    $siteMail = $this->configFactory->get('system.site')->get('mail');
    return $this->mailManager->mail('ssp_site', 'inactive_user_notify', $account->getEmail(), $langcode, $params, $siteMail);
  }

}
