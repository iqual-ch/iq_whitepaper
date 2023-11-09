<?php

namespace Drupal\iq_whitepaper\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;
use Drupal\iq_group\Service\IqGroupUserManager;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Whitepaper Form.
 */
class WhitepaperForm extends FormBase {

  /**
   * A config object for the iq_group settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a new WhitepaperForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\iq_group\Service\IqGroupUserManager $userManager
   *   The iq group user manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected IqGroupUserManager $userManager,
  ) {
    $this->config = $config_factory->get('iq_group.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('iq_group.user_manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'iq_whitepaper_whitepaper_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $default_preferences = [];
    $group_id = $this->config->get('general_group_id');
    if ($group_id) {
      $group = Group::load($this->config->get('general_group_id'));
      $group_role_storage = $this->entityTypeManager->getStorage('group_role');
      $groupRoles = $group_role_storage->loadByUserAndGroup($this->currentUser, $group);
      $groupRoles = array_keys($groupRoles);
      if ($this->currentUser->isAnonymous()) {
        $form['mail'] = [
          '#type' => 'email',
          '#title' => $this->t('Email address'),
          '#required' => !$this->currentUser->getEmail(),
          '#default_value' => $this->currentUser->getEmail(),
        ];
        $form['name'] = [
          '#type' => 'hidden',
          '#title' => $this->t('Username'),
          '#maxlength' => UserInterface::USERNAME_MAX_LENGTH,
          '#description' => $this->t("Some special characters are allowed, such as space, dot (.), hyphen (-), apostrophe ('), underscore(_) and the @ character."),
          '#required' => FALSE,
          '#attributes' => [
            'class' => ['username'],
            'autocorrect' => 'off',
            'autocapitalize' => 'off',
            'spellcheck' => 'false',
          ],
        ];
        $termsAndConditions = $this->config->get('terms_and_conditions') ?: "";
        $form['data_privacy'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('I have read the <a href="@terms_and_conditions" target="_blank">terms and conditions</a> and data protection regulations and I agree.', ['@terms_and_conditions' => $termsAndConditions]),
          '#default_value' => FALSE,
          '#weight' => 100,
          '#required' => TRUE,
        ];
      }
      else {
        if (in_array('subscription-lead', $groupRoles) || in_array('subscription-subscriber', $groupRoles)) {
          $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
          $selected_preferences = $user->get('field_iq_group_preferences')->getValue();
          foreach ($selected_preferences as $value) {
            // If it is not the general group, add it.
            if ($value['target_id'] != $this->config->get('general_group_id')) {
              $default_preferences = [...$default_preferences, $value['target_id']];
            }
          }
        }
      }
      $result = $this->entityTypeManager->getStorage('group')->loadMultiple();
      $options = [];
      /**
       * @var  int $key
       * @var  \Drupal\group\Entity\Group $group
       */
      foreach ($result as $group) {
        // If it is not the general group, add it.
        if ($group->id() != $this->config->get('general_group_id')) {
          $options[$group->id()] = $group->label();
        }
      }
      $form['preferences'] = [
        '#type' => 'checkboxes',
        '#options' => $options,
        '#multiple' => TRUE,
        '#default_value' => $default_preferences,
        '#title' => $this->t('Preferences'),
      ];
      $form['destination'] = [
        '#type' => 'hidden',
        '#default_value' => '',
      ];
      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#button_type' => 'primary',
      ];
      if (isset($form['data_privacy'])) {
        $form['actions']['submit']['#states'] = [
          'disabled' => [
            ':input[name="data_privacy"]' => [
              'checked' => FALSE,
            ],
          ],
        ];
      }
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $destination = NULL;
    $project_name = $this->config->get('project_name') != NULL ? $this->config->get('project_name') : "";
    if ($this->currentUser->isAnonymous()) {
      $result = \Drupal::entityQuery('user')
        ->accessCheck(TRUE)
        ->condition('mail', $form_state->getValue('mail'), 'LIKE')
        ->execute();
      // If the user exists, send an email to login.
      if ((is_countable($result) ? count($result) : 0) > 0) {
        $user = $this->entityTypeManager->getStorage('user')->load(reset($result));

        if ($form_state->getValue('destination') != "") {
          $destination = $form_state->getValue('destination');
        }
        else {
          // @todo Set a destination if it is a signup form or not?
          // $destination = \Drupal\Core\Url::fromRoute('<current>')->toString();
        }
        $renderable = [
          '#theme' => 'whitepaper_template',
          '#EMAIL_TITLE' => $this->t('Whitepaper download'),
          '#EMAIL_PREVIEW_TEXT' => $this->t("Download your @project_name whitepaper now", ['@project_name' => $project_name]),
          '#EMAIL_PROJECT_NAME' => $project_name,
        ];
        $this->userManager->createMember(['id' => $user->id()], $renderable, $destination, FALSE);
      }
      // If the user does not exist.
      else {
        if ($form_state->getValue('name') != NULL) {
          $name = $form_state->getValue('name');
        }
        else {
          $name = $form_state->getValue('mail');
        }
        $user_data = [
          'mail' => $form_state->getValue('mail'),
          'name' => $name,
          'status' => 1,
        ];
        if ($form_state->getValue('preferences') != NULL) {
          $user_data['field_iq_group_preferences'] = $form_state->getValue('preferences');
        }
        if ($form_state->getValue('destination') != "") {
          $destination = $form_state->getValue('destination');
        }
        else {
          // @todo Set a destination if it is a signup form or not?
          // $destination = \Drupal\Core\Url::fromRoute('<current>')->toString();
        }
        $renderable = [
          '#theme' => 'whitepaper_template',
          '#EMAIL_TITLE' => 'Whitepaper Download',
          '#EMAIL_PREVIEW_TEXT' => 'Whitepaper Download',
          '#EMAIL_PROJECT_NAME' => $project_name,
        ];
        $this->userManager->createMember($user_data, $renderable, $destination);
      }
      \Drupal::messenger()->addMessage($this->t('Thank you very much for your interest. You will shortly receive an e-mail with a link to the desired whitepaper.'));
    }
    else {
      $user = User::load($this->currentUser->id());
      if ($form_state->getValue('preferences') != NULL) {
        $user->set('field_iq_group_preferences', $form_state->getValue('preferences'));
      }
      $user->save();
      // Redirect if needed.
    }

  }

}
