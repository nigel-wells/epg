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
            $form['feed_title'] = [
                '#markup' => '<h2>Available XML Feeds</h2>'
            ];
            foreach($path as $index => $xmlFile) {
                $form['feed_' . $index] = [
                    '#markup' => '<li>' . substr($xmlFile, strrpos($xmlFile, '/') + 1) . '</li>',
                ];
            }
        }
        $form['trigger_title'] = [
            '#markup' => '<h2>Triggers to run</h2>'
        ];
        $form['import_feeds'] = [
            '#type' => 'checkbox',
            '#title' => 'Feeds (channels &amp; programmes)'
        ];
        $form['update_filters'] = [
            '#type' => 'checkbox',
            '#title' => 'Match up programme filters'
        ];
        $form['create_xml'] = [
            '#type' => 'checkbox',
            '#title' => 'Create XMLTV file'
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        ini_set('memory_limit', '1024M');
        $import_feeds = $form_state->getValue('import_feeds');
        $update_filters = $form_state->getValue('update_filters');
        $create_xml = $form_state->getValue('create_xml');
        $epgController = new epgController();
        if($import_feeds) {
            $epgController->importFeed();
        }

        if($update_filters) {
            $epgController->importProviderData();
        }
        if($create_xml) {
            $epgController->createXML();
        }

    }
}