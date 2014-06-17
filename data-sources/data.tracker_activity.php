<?php

require_once(TOOLKIT . '/class.datasource.php');
require_once(EXTENSIONS . '/tracker/lib/class.tracker.php');

Class datasourcetracker_activity extends Datasource{

    public $dsParamROOTELEMENT = 'tracker-activity';

    // Use these properties to adjust the DS output
    public $dsParamORDER = 'desc';
    public $dsParamLIMIT = '20';
    public $dsParamSORT = 'timestamp';

    // Use this property to specify Sections to include (includes all by default)
    // Example: public $dsParamSECTIONS = array(1,2,3);
    public $dsParamSECTIONS = NULL;

    // Use this property to specify what actions to include
    // (choose from 'created', 'updated', and 'deleted')
    public $dsParamACTIONS = array('created','updated');

    public function __construct(&$parent, $env=NULL, $process_params=true)
    {
        parent::__construct($parent, $env, $process_params);
        $this->_dependencies = array();
    }

    public function about()
    {
        return array(
                 'name' => 'Tracker Activity',
                 'author' => array(
                        'name' => 'Craig Zheng',
                        'email' => 'craig@symphony-cms.com'),
                 'version' => '0.9',
                 'release-date' => '2010-08-08T00:00:00+00:00');
    }

    public function getSource()
    {
        return NULL;
    }

    public function allowEditorToParse()
    {
        return false;
    }

    public function grab(&$param_pool=NULL)
    {
        $result = new XMLElement($this->dsParamROOTELEMENT);
        $param_output = array();

        $filters = array();

        if (!empty($this->dsParamSECTIONS)) {
            $filters['item_type'] = $this->dsParamSECTIONS;
        } else {
            $filters['item_type'] = 'REGEXP "[[:digit:]]+"';
        }

        if (!empty($this->dsParamACTIONS)) {
            $filters['action_type'] = $this->dsParamACTIONS;
        }

        // Fetch the activity
        $activities = Tracker::fetchActivities(
            $filters,
            $this->dsParamLIMIT,
            0,
            $this->dsParamSORT,
            $this->dsParamORDER
        );

        // Build the XML
        foreach ($activities as $activity) {

            // Capture the section and entry ID for output params
            $param_output[$activity['item_type']][] = $activity['item_id'];

            // Break the fallback description into useful bits
            $activity['fallback_description'] = explode(
                ':::',
                $activity['fallback_description']
            );

            // Build the <activity> element
            $item = new XMLElement('activity', NULL, array(
                'type' => $activity['action_type'],
                'entry-id' => $activity['item_id']
            ));

            // Append Section info
            $item->appendChild(
                new XMLElement('section', $activity['fallback_description'][1], array(
                    'id' => $activity['item_type']
                ))
            );

            // Append Author info
            $item->appendChild(
                new XMLElement('author', $activity['fallback_username'], array(
                    'id' => $activity['user_id']
                ))
            );

            // Append Date info
            $item->appendChild(
                new XMLElement('date', DateTimeObj::get('Y-m-d',strtotime($activity['timestamp'] . ' GMT')), array(
                    'time' => DateTimeObj::get('H:i',strtotime($activity['timestamp'] . ' GMT')),
                    'weekday' => DateTimeObj::get('N',strtotime($activity['timestamp'] . ' GMT'))
                ))
            );

            $result->appendChild($item);
        }

        // Build output params
        foreach ($param_output as $section => $ids) {
            $param_pool['ds-' . $this->dsParamROOTELEMENT . '-' . $section] = implode(', ', $ids);
        }

        return $result;
    }
}
