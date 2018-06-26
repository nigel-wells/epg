<?php

namespace Drupal\epg\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\epg\Controller\epgController;
use Drupal\epg\Model\Content\programmeFilter;
use Drupal\epg\Model\Content\series;
use Drupal\epg\Provider\OMDB\omDb;
use Drupal\epg\Provider\TVDB\tvdb;
use Drupal\epg\Provider\TVMaze\tvMaze;

class locateDataForm extends ConfigFormBase {

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
        return 'epg_locate_data_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $validFilter = false;
        $node = intval(\Drupal::routeMatch()->getParameter('node'));
        // You can get nid and anything else you need from the node object.
        $programmeFilter = new programmeFilter($node);
        if($programmeFilter->nid) {
            $validFilter = true;
            $form['intro'] = [
                '#markup' => '<h2>' . $programmeFilter->getTitle() . '</h2>'
            ];
            $form['omdb_id'] = [
                '#type' => 'number',
                '#title' => $this->t('OMDB ID'),
                '#description' => $this->t('ID from <a href="http://www.omdbapi.com/" target="_blank">www.omdbapi.com</a>'),
            ];
            $form['tvdb_id'] = [
                '#type' => 'number',
                '#title' => $this->t('TVDB ID'),
                '#description' => $this->t('ID from <a href="http://www.thetvdb.com/" target="_blank">www.thetvdb.com</a>'),
            ];
            $form['tvmaze_id'] = [
                '#type' => 'number',
                '#title' => $this->t('TV Maze ID'),
                '#description' => $this->t('ID from <a href="http://www.tvmaze.com/search?q=' . rawurlencode($programmeFilter->getSearchTitle()) . '" target="_blank">www.tvmaze.com</a>'),
            ];
        }
        if(!$validFilter) {
            $form['intro'] = [
                '#markup' => 'Not a valid programme filter'
            ];
            unset($form['actions']['submit']);
        }
        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $node = intval(\Drupal::routeMatch()->getParameter('node'));
        // You can get nid and anything else you need from the node object.
        $programmeFilter = new programmeFilter($node);
        if($programmeFilter->nid) {
            $series = new series();
            $omdb_id = $form_state->getValue('omdb_id');
            $tvdb_id = $form_state->getValue('tvdb_id');
            $tvmaze_id = $form_state->getValue('tvmaze_id');
            if ($omdb_id) {
                $omdb = new omDb();
            } elseif ($tvdb_id) {
                $tvdb = new tvdb();
            } elseif ($tvmaze_id) {
                $tvMaze = new tvMaze();
                if ($dataSeries = $tvMaze->getSeries($tvmaze_id)) {
                    $series->setTvMazeId($dataSeries->getId());
                    $series->checkForExistingNodeTvMaze();
                    if (!$series->nid) {
                        $series->update();
                        $series->checkForUpdates();
                    }
                }
            }
            $programmeFilter->setSeries($series->nid);
            $programmeFilter->update();
            $messenger = \Drupal::messenger();
            $messenger->addMessage('Updated to match: ' . $series->getTitle());
        }
    }
}