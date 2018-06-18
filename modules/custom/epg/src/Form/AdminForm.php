<?php

namespace Drupal\epg\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\epg\Controller\epgController;

class AdminForm extends ConfigFormBase {

    /**
     * Gets the configuration names that will be editable.
     *
     * @return array
     *   An array of configuration object names that are editable if called in
     *   conjunction with the trait's config() method.
     */
    protected function getEditableConfigNames()
    {
        return [
            'epg.adminsettings',
        ];
    }

    /**
     * Returns a unique string identifying the form.
     *
     * @return string
     *   The unique string identifying the form.
     */
    public function getFormId()
    {
        return 'epg_admin_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $config = $this->config('epg.adminsettings');

        $epgController = new epgController();
        $text = 'Nope';
        $path = $epgController->getFeeds();
        if($path !== false) {
            $text = print_r($path, true);
        }

        $form['welcome_message'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Welcome message2'),
            '#description' => $this->t('Welcome message display to users when they login'),
            '#default_value' => $text,
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
//        parent::submitForm($form, $form_state);
//
//        $this->config('epg.adminsettings')
//            ->set('welcome_message', $form_state->getValue('welcome_message'))
//            ->save();
        $epgController = new epgController();
//        $epgController->importProviderData();
//        $epgController->importFeed();
        $epgController->createXML();

    }
}