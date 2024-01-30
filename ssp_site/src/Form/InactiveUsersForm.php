<?php

namespace Drupal\ssp_site\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\RoleStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The form for setting deactivate user options.
 */
class InactiveUsersForm extends ConfigFormBase {

  /**
   * The role storage used for exclude role config.
   *
   * @var \Drupal\user\RoleStorageInterface
   */
  protected $roleStorage;

  /**
   * Constructs a \Drupal\ssp_site\Form\InactiveUsersForm object.
   *
   * @param \Drupal\user\RoleStorageInterface $role_storage
   *   The role storage.
   */
  public function __construct(RoleStorageInterface $role_storage) {
    $this->roleStorage = $role_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('user_role')
    );
  }

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return ['ssp_site.inactive_users'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ssp_site_inactive_users_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ssp_site.inactive_users');
    $config
      ->set('email_template', $form_state->getValue('email_template'))
      ->set('stages', $form_state->getValue('stages'))
      ->set('exclude_roles', array_filter($form_state->getValue('exclude_roles')))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ssp_site.inactive_users');

    $form['stages'] = [
      '#type' => 'textarea',
      '#title'  => $this->t('Stages'),
      '#default_value'  => $config->get('stages'),
      '#description'  => $this->t('Please enter each stage to separate line in format @number|start|end|@inactive-period|@remaining-period like "1|18|21|1 year and 6 months|6 months"'),
    ];
    $form['email_template'] = [
      '#type' => 'textarea',
      '#title'  => $this->t('Email template'),
      '#default_value'  => $config->get('email_template'),
      '#description'  => $this->t('You can use @name, @inactive-period, @remaining-period and @site-url tokens in this textarea.'),
    ];
    $roles = [];
    foreach ($this->roleStorage->loadMultiple() as $rid => $role) {
      $roles[$rid] = $role->label();
    }
    $form['exclude_roles'] = [
      '#type' => 'checkboxes',
      '#options' => $roles,
      '#title'  => $this->t('Exclude user roles'),
      '#default_value'  => $config->get('exclude_roles'),
      '#description'  => $this->t('You can select user roles for exclude from auto-deactivation process.'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
