<?php

require_once(EXTENSIONS . '/tracker/lib/class.tracker.php');

class Extension_Tracker extends Extension
{
    public function fetchNavigation()
    {
        return array(
            array(
                'location'	=> __('System'),
                'name'		=> __('Tracker Activity'),
                'link'		=> '/',
                'limit'		=> 'developer'
            )
        );
    }

    public function getSubscribedDelegates()
    {
        return array(

            // Extension setup
            array(
                'page' => '/system/preferences/',
                'delegate' => 'AddCustomPreferenceFieldsets',
                'callback' => 'appendPreferences'
            ),
            array(
                'page' => '/system/preferences/',
                'delegate' => 'CustomActions',
                'callback' => 'savePreferences'
            ),

            // Entry activity tracking
            array(
                'page' 		=> '/publish/new/',
                'delegate'	=> 'EntryPostCreate',
                'callback'	=> 'parseEntryAction'
            ),
            array(
                'page' 		=> '/publish/edit/',
                'delegate'	=> 'EntryPostEdit',
                'callback'	=> 'parseEntryAction'
            ),
            array(
                'page' 		=> '/publish/',
                'delegate'	=> 'EntryPostDelete',
                'callback'	=> 'parseEntryAction'
            ),
            array(
                'page'		=> '/frontend/',
                'delegate'	=> 'EventPostSaveFilter',
                'callback'	=> 'parseEventSave'
            ),

            // Blueprints activity tracking
            array(
                'page'		=> '/blueprints/pages/',
                'delegate'	=> 'PagePostCreate',
                'callback'	=> 'parsePageAction'
            ),
            array(
                'page'		=> '/blueprints/pages/',
                'delegate'	=> 'PagePostEdit',
                'callback'	=> 'parsePageAction'
            ),
            array(
                'page'		=> '/blueprints/pages/',
                'delegate'	=> 'PagePreDelete',
                'callback'	=> 'parsePageAction'
            ),
            array(
                'page'		=> '/blueprints/events/',
                'delegate'	=> 'EventPostCreate',
                'callback'	=> 'parseEventAction'
            ),
            array(
                'page'		=> '/blueprints/events/',
                'delegate'	=> 'EventPostEdit',
                'callback'	=> 'parseEventAction'
            ),
            array(
                'page'		=> '/blueprints/events/',
                'delegate'	=> 'EventPreDelete',
                'callback'	=> 'parseEventAction'
            ),
            array(
                'page'		=> '/blueprints/datasources/',
                'delegate'	=> 'DatasourcePostCreate',
                'callback'	=> 'parseDatasourceAction'
            ),
            array(
                'page'		=> '/blueprints/datasources/',
                'delegate'	=> 'DatasourcePostEdit',
                'callback'	=> 'parseDatasourceAction'
            ),
            array(
                'page'		=> '/blueprints/datasources/',
                'delegate'	=> 'DatasourcePreDelete',
                'callback'	=> 'parseDatasourceAction'
            ),
            array(
                'page'		=> '/blueprints/utilities/',
                'delegate'	=> 'UtilityPostCreate',
                'callback'	=> 'parseUtilityAction'
            ),
            array(
                'page'		=> '/blueprints/utilities/',
                'delegate'	=> 'UtilityPostEdit',
                'callback'	=> 'parseUtilityAction'
            ),
            array(
                'page'		=> '/blueprints/utilities/',
                'delegate'	=> 'UtilityPreDelete',
                'callback'	=> 'parseUtilityAction'
            ),
            array(
                'page'		=> '/blueprints/sections/',
                'delegate'	=> 'SectionPostCreate',
                'callback'	=> 'parseSectionAction'
            ),
            array(
                'page'		=> '/blueprints/sections/',
                'delegate'	=> 'SectionPostEdit',
                'callback'	=> 'parseSectionAction'
            ),
            array(
                'page'		=> '/blueprints/sections/',
                'delegate'	=> 'SectionPreDelete',
                'callback'	=> 'parseSectionAction'
            ),

            // System activity tracking
            array(
                'page'		=> '/system/authors/',
                'delegate'	=> 'AuthorPostCreate',
                'callback'	=> 'parseAuthorAction'
            ),
            array(
                'page'		=> '/system/authors/',
                'delegate'	=> 'AuthorPostEdit',
                'callback'	=> 'parseAuthorAction'
            ),
            array(
                'page'		=> '/system/authors/',
                'delegate'	=> 'AuthorPreDelete',
                'callback'	=> 'parseAuthorAction'
            ),
            array(
                'page'		=> '/system/extensions/',
                'delegate'	=> 'ExtensionPreEnable',
                'callback'	=> 'parseExtensionAction'
            ),
            array(
                'page'		=> '/system/extensions/',
                'delegate'	=> 'ExtensionPreDisable',
                'callback'	=> 'parseExtensionAction'
            ),
            array(
                'page'		=> '/system/extensions/',
                'delegate'	=> 'ExtensionPreUninstall',
                'callback'	=> 'parseExtensionAction'
            ),
            array(
                'page'		=> '/system/preferences/',
                'delegate'	=> 'Save',
                'callback'	=> 'parsePreferencesSave'
            ),

            // Login tracking
            array(
                'page'		=> '/login/',
                'delegate'	=> 'AuthorLoginFailure',
                'callback'	=> 'parseLogin'
            ),
            array(
                'page'		=> '/login/',
                'delegate'	=> 'AuthorLoginSuccess',
                'callback'	=> 'parseLogin'
            ),
            array(
                'page'		=> '/login/',
                'delegate'	=> 'AuthorPostPasswordResetSuccess',
                'callback'	=> 'parsePasswordAction'
            ),
            array(
                'page'		=> '/login/',
                'delegate'	=> 'AuthorPostPasswordResetFailure',
                'callback'	=> 'parsePasswordAction'
            ),
            array(
                'page'		=> '/login/',
                'delegate'	=> 'AuthorPostPasswordChange',
                'callback'	=> 'parsePasswordAction'
            ),
            array(
                'page'		=> '/login/',
                'delegate'	=> 'AuthorPostPasswordResetRequest',
                'callback'	=> 'parsePasswordAction'
            ),

            // Dashboard
            array(
                'page'		=> '/backend/',
                'delegate'	=> 'DashboardPanelRender',
                'callback'	=> 'renderPanel'
            ),
            array(
                'page'		=> '/backend/',
                'delegate'	=> 'DashboardPanelOptions',
                'callback'	=> 'dashboardPanelOptions'
            ),
            array(
                'page'		=> '/backend/',
                'delegate'	=> 'DashboardPanelTypes',
                'callback'	=> 'dashboardPanelTypes'
            ),
        );
    }

    public function install()
    {
        Symphony::Database()->query(
            'CREATE TABLE IF NOT EXISTS `tbl_tracker_activity` (
                `id` int(11) unsigned NOT NULL auto_increment,
                `item_type` varchar(255),
                `item_id` varchar(75),
                `action_type` varchar(255),
                `user_id` int(11),
                `timestamp` timestamp,
                `fallback_username` varchar(255),
                `fallback_description` varchar(255),
                PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;');

        return true;
    }

    public function uninstall()
    {
        Symphony::Database()->query(
            'DROP TABLE IF EXISTS `tbl_tracker_activity`;'
        );
        Symphony::Configuration()->remove('tracker');

        return Symphony::Configuration()->write();
    }

    /*-------------------------------------------------------------------------
        Activity Parsing
    -------------------------------------------------------------------------*/

    public function parseEntryAction($context)
    {
        if ($this->validateUser()) {
            $action = $this->getActionFromDelegateName($context['delegate']);

            // Entry IDs are provided in different formats depending
            // on if you're deleting or not. So standardize them.
            if ($action == 'deleted') {
                $ids = (array) $context['entry_id'];
            } else {
                $section_id = $context['entry']->get('section_id');
                $ids = (array) $context['entry']->get('id');
            }

            // Loop through entries, validate section, and log them.
            foreach ($ids as $entry_id) {

                if (!isset($section_id)) {
                    include_once(TOOLKIT . '/class.entrymanager.php');
                    $section_id = EntryManager::fetchEntrySectionID($entry_id);
                }

                if ($this->validateSection($section_id)) {
                    Tracker::log(
                        $section_id,
                        $entry_id,
                        $action,
                        $this->getAuthorID(),
                        $this->getTimestamp()
                    );
                }
            }
        }
    }

    public function parseEventSave($context)
    {
        if ($this->validateSection($context['entry']->get('section_id'))) {

            // If the ID's been passed, we're updating an existing entry,
            // otherwise, it's new.
            if (!empty($_POST['id'])) {
                $action = 'updated';
            } else {
                $action = 'created';
            }

            // Logged-in author, or anonymous front-end user?
            if (!is_null(Frontend::instance()->Author)) {
                $author = Frontend::instance()->Author;
                $author_id = $author->get('id');
                if (!$this->validateUser($author_id)) {
                    return;
                }
            } else {
                $author_id = 0;
            }

            // Log it.
            Tracker::log(
                $context['entry']->get('section_id'),
                $context['entry']->get('id'),
                $action,
                $author_id,
                $this->getTimestamp()
            );
        }
    }

    public function parsePageAction($context)
    {
        if ($this->validateUser() && $this->validateElement('pages')) {

            // Set action type from delegate name. Saves having to
            // use three separate callbacks.
            $action = $this->getActionFromDelegateName($context['delegate']);

            // Page IDs are provided in different formats depending
            // on if you're deleting or not. So standardize them.
            if (isset($context['page_ids'])) {
                $ids = $context['page_ids'];
            } else {
                $ids = (array) $context['page_id'];
            }

            // Log it.
            foreach ($ids as $id) {
                Tracker::log(
                    'pages',
                    $id,
                    $action,
                    $this->getAuthorID(),
                    $this->getTimestamp()
                );
            }
        }
    }

    public function parseEventAction($context)
    {
        if ($this->validateUser() && $this->validateElement('events')) {

            // Set action type from delegate name. Saves having to
            // use three separate callbacks.
            $action = $this->getActionFromDelegateName($context['delegate']);

            $item = str_replace(EVENTS . '/', '', $context['file']);

            // Log it.
            Tracker::log(
                'events',
                $item,
                $action,
                $this->getAuthorID(),
                $this->getTimestamp()
            );
        }
    }

    public function parseDatasourceAction($context)
    {
        if ($this->validateUser() && $this->validateElement('datasources')) {

            // Set action type from delegate name. Saves having to
            // use three separate callbacks.
            $action = $this->getActionFromDelegateName($context['delegate']);

            $item = str_replace(DATASOURCES . '/', '', $context['file']);

            // Log it.
            Tracker::log(
                'datasources',
                $item,
                $action,
                $this->getAuthorID(),
                $this->getTimestamp()
            );
        }
    }

    public function parseUtilityAction($context)
    {
        if ($this->validateUser() && $this->validateElement('utilities')) {

            // Set action type from delegate name. Saves having to
            // use three separate callbacks.
            $action = $this->getActionFromDelegateName($context['delegate']);

            $item = str_replace(UTILITIES . '/', '', $context['file']);

            // Log it.
            Tracker::log(
                'utilities',
                $item,
                $action,
                $this->getAuthorID(),
                $this->getTimestamp()
            );
        }
    }

    public function parseSectionAction($context)
    {
        if ($this->validateUser() && $this->validateElement('sections')) {

            // Set action type from delegate name. Saves having to
            // use three separate callbacks.
            $action = $this->getActionFromDelegateName($context['delegate']);

            // Section IDs are provided in different formats depending
            // on if you're deleting or not. So standardize them.
            if (isset($context['section_ids'])) {
                $ids = $context['section_ids'];
            } else {
                $ids = (array) $context['section_id'];
            }

            // Log it.
            foreach ($ids as $id) {
                Tracker::log(
                    'sections',
                    $id,
                    $action,
                    $this->getAuthorID(),
                    $this->getTimestamp()
                );
            }
        }
    }

    public function parseAuthorAction($context)
    {
        if ($this->validateUser() && $this->validateElement('authors')) {

            // Set action type from delegate name. Saves having to
            // use three separate callbacks.
            $action = $this->getActionFromDelegateName($context['delegate']);

            // Figure out the author IDs and standardize their format
            if ($context['author'] instanceof Author) {

                // Workaround because the Author object returned by the delegate
                // doesn't have an ID for some reason.
                if ($action == 'created') {
                    require_once(TOOLKIT . '/class.authormanager.php');
                    $author = AuthorManager::fetchByUsername($context['author']->get('username'));
                    $ids = (array) $author->get('id');
                } else {
                    $ids = (array) $context['author']->get('id');
                }
            } else {
                $ids = $context['author_ids'];
            }

            // Log it.
            foreach ($ids as $id) {
                Tracker::log(
                    'authors',
                    $id,
                    $action,
                    $this->getAuthorID(),
                    $this->getTimestamp()
                );
            }
        }
    }

    public function parseExtensionAction($context)
    {
        if ($this->validateUser() && $this->validateElement('extensions')) {

            // Set action type from delegate name. Saves having to
            // use three separate callbacks.
            $action = $this->getActionFromDelegateName($context['delegate']);

            $extensions = $context['extensions'];

            // Log it.
            foreach ($extensions as $name) {
                Tracker::log(
                    'extensions',
                    $name,
                    $action,
                    $this->getAuthorID(),
                    $this->getTimestamp()
                );
            }
        }

    }

    public function parsePreferencesSave($context)
    {
        if ($this->validateUser() && $this->validateElement('preferences')) {
            Tracker::log(
                'preferences',
                NULL,
                'updated',
                $this->getAuthorID(),
                $this->getTimestamp()
            );

            // Log changes to maintenance mode from system prefs.
            // Doesn't work if the page alert's "Restore" link is used.
            if (Symphony::Configuration()->get('enabled','maintenance_mode') != $context['settings']['maintenance_mode']['enabled']) {

                Tracker::log(
                    'maintenance-mode',
                    NULL,
                    ($context['settings']['maintenance_mode']['enabled'] == 'yes' ? 'enabled' : 'disabled'),
                    $this->getAuthorID(),
                    $this->getTimestamp()
                );
            }
        }
    }

    public function parseLogin($context)
    {
        if ($this->validateElement('login')) {
            $item = NULL;

            // Set author ID. If author doesn't exist, store the IP
            // address.
            if (Symphony::Engine()->Author) {
                if (!$this->validateUser(Symphony::Engine()->Author->get('id'))) {
                    return;
                }
                $account = Symphony::Engine()->Author->get('id');
            } else {
                $account = 0;
                $item = $_SERVER['REMOTE_ADDR'];
            }

            if (stripos($context['delegate'], 'success')) {
                $action = 'logged in';
            } else {
                $action = 'attempted to log in';
            }

            Tracker::log(
                'login',
                $item,
                $action,
                $account,
                $this->getTimestamp()
            );
        }
    }

    public function parsePasswordAction($context)
    {
        if ($this->validateElement('password')) {

            // Use delegate name to determine action
            switch ($context['delegate']) {
                case 'AuthorPostPasswordResetSuccess':
                    $action = 'reset';
                break;

                case 'AuthorPostPasswordResetFailure':
                    $action = 'attempted to reset';
                break;

                case 'AuthorPostPasswordChange':
                    $action = 'changed';
                break;

                case 'AuthorPostPasswordResetRequest':
                    $action = 'requested to reset';
                break;
            }

            // If the user's unknown, set ID to 0 and store their email.
            if ($action == 'attempted to reset') {
                $account = 0;
                $item = $context['email'];
            } else {
                $account = $context['author_id'];
                if (!$this->validateUser($account)) {
                    return;
                }
                $item = NULL;
            }

            Tracker::log(
                'password-reset',
                $item,
                $action,
                $account,
                $this->getTimestamp()
            );
        }
    }

    /*-------------------------------------------------------------------------
        Utilities
    -------------------------------------------------------------------------*/

    public function getAuthorID()
    {
        return Symphony::Engine()->Author->get('id');
    }

    public function getTimestamp()
    {
        return DateTimeObj::getGMT('Y-m-d H:i:s', time());
    }

    public function getActionFromDelegateName($name)
    {
        if (stripos($name,'edit')) {
            return 'updated';
        } elseif (stripos($name,'create')) {
            return 'created';
        } elseif (stripos($name,'delete') !== FALSE) { // Because Delete delegate returns 0

            return 'deleted';
        } elseif (stripos($name,'enable')) {
            return 'enabled';
        } elseif (stripos($name,'disable')) {
            return 'disabled';
        } elseif (stripos($name,'uninstall')) {
            return 'uninstalled';
        }
    }

    public function getExclusions($type)
    {
        return explode(',', Symphony::Configuration()->get('excluded-' . $type, 'tracker'));
    }

    public function validateElement($handle)
    {
        if (in_array($handle, $this->getExclusions('system-elements'))) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    public function validateSection($id)
    {
        if (in_array($id, $this->getExclusions('sections'))) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    public function validateUser($id = NULL)
    {
        if (is_null($id)) {
            $id = $this->getAuthorID();
        }
        if (in_array($id, $this->getExclusions('users'))) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    /*-------------------------------------------------------------------------
        Preferences
    -------------------------------------------------------------------------*/

    public function appendPreferences($context)
    {
        include_once(TOOLKIT . '/class.authormanager.php');
        include_once(TOOLKIT . '/class.sectionmanager.php');

        // Fieldset and layout
        $group = new XMLElement('fieldset');
        $group->setAttribute('class', 'settings');
        $group->appendChild(new XMLElement('legend', __('Tracker')));

        $div = new XMLElement('div');
        $div->setAttribute('class', 'group triple');

        // Excluded System Elements
        $label = Widget::Label(__('Excluded System Elements'));
        $options = array();
        $elements = array(
            'authors'		=> __('Authors'),
            'datasources'	=> __('Data Sources'),
            'events'		=> __('Events'),
            'pages'			=> __('Pages'),
            'sections'		=> __('Sections'),
            'utilities'		=> __('Utilities'),
            'preferences'	=> __('Preferences'),
            'extensions'	=> __('Extensions'),
            'login'			=> __('Login/Logout'),
            'password'		=> __('Password Reset')
        );
        $excluded_elements = explode(',', Symphony::Configuration()->get('excluded-system-elements', 'tracker'));

        foreach ($elements as $handle => $value) {
            $selected = (in_array($handle, $excluded_elements) ? TRUE : FALSE);
            $options[] = array(
                $handle,
                $selected,
                $value
            );
        }

        $input = Widget::Select(
            'settings[tracker][excluded-system-elements][]',
            $options,
            array('multiple' => 'multiple')
        );

        $label->appendChild($input);
        $div->appendChild($label);

        // Excluded Sections
        $label = Widget::Label(__('Excluded Sections'));
        $options = array();

        $sm = new SectionManager(Administration::instance());

        $sections = $sm->fetch();
        $excluded_sections = explode(',', Symphony::Configuration()->get('excluded-sections', 'tracker'));

        if (!empty($sections) && is_array($sections)) {
            foreach ($sections as $section) {
                $selected = (in_array($section->get('id'), $excluded_sections) ? TRUE : FALSE);
                $options[] = array(
                    $section->get('id'),
                    $selected,
                    $section->get('name')
                );
            }
        }

        $input = Widget::Select(
            'settings[tracker][excluded-sections][]',
            $options,
            array('multiple' => 'multiple')
        );

        $label->appendChild($input);
        $div->appendChild($label);

        // Excluded Users
        $label = Widget::Label(__('Excluded Users'));
        $options = array();

        $am = new AuthorManager(Administration::instance());
        $authors = $am->fetch();
        $excluded_authors = explode(',',Symphony::Configuration()->get('excluded-users', 'tracker'));

        if (!empty($authors) && is_array($authors)) {
            foreach ($authors as $author) {
                $selected = (in_array($author->get('id'), $excluded_authors) ? TRUE : FALSE);
                $options[] = array(
                    $author->get('id'),
                    $selected,
                    $author->getFullName()
                );
            }
        }

        $input = Widget::Select(
            'settings[tracker][excluded-users][]',
            $options,
            array('multiple' => 'multiple')
        );

        $label->appendChild($input);
        $div->appendChild($label);

        $group->appendChild($div);

        // notify url of tracker event
        $notify_url = Symphony::Configuration()->get('notify_url', 'tracker');
        $notify_label = Widget::Label(__('Send tracker event to URL'));
        $notify_label->appendChild(Widget::Input('settings[tracker][notify_url]', $notify_url, 'text'));
        $group->appendChild($notify_label);

        $context['wrapper']->appendChild($group);
    }

    public function savePreferences()
    {
        // Remove existing configuration settings.
        Symphony::Configuration()->remove('tracker');
        Symphony::Configuration()->write();

        // If there are Tracker settings, format them
        if (is_array($_POST['settings']['tracker'])) {
            foreach ($_POST['settings']['tracker'] as $preference => $value) {
                if (is_array($value)) {
                    $_POST['settings']['tracker'][$preference] = implode(',',$value);
                }
            }
        }
    }

    /*-------------------------------------------------------------------------
        Dashboard
    -------------------------------------------------------------------------*/

    public function dashboardPanelTypes($context)
    {
        $context['types']['tracker_activity'] = "Tracker Activity";
    }

    public function dashboardPanelOptions($context)
    {
        $config = $context['existing_config'];

        switch ($context['type']) {

            case 'tracker_activity':

                $fieldset = new XMLElement('fieldset', NULL, array('class' => 'settings'));
                $fieldset->appendChild(new XMLElement('legend', __('Tracker Activity')));

                $label = Widget::Label(__('Limit'), Widget::Input('config[limit]', $config['limit']));
                $fieldset->appendChild($label);

                $label = Widget::Label(__('Filter Query String'), Widget::Input('config[filter_string]', $config['filter_string']));
                $fieldset->appendChild($label);

                $context['form'] = $fieldset;

            break;

        }

    }

    public function renderPanel($context)
    {
        $config = $context['config'];
        $page = Administration::instance()->Page;

        switch ($context['type']) {

            case 'tracker_activity':

                // Build filter info
                $filters = array();

                if (isset($config['filter_string'])) {

                    list($column, $value) = explode(':', $config['filter_string'], 2);
                    $values = explode(',', $value);
                    $filters[$column] = array();

                    foreach ($values as $value) {
                        $filters[$column][] = rawurldecode($value);
                    }
                }

                // Check to see we are being called in the right context
                // Dashboard also has `contentExtensionDashboardPanel_Config` which extends `AjaxPage`
                if (method_exists($page, 'addStylesheetToHead')) {
                    $page->addStylesheetToHead(URL . '/extensions/tracker/assets/dashboard.css', 'screen', 151);
                }

                $logs = Tracker::fetchActivities($filters, (int) $config['limit'], 0);

                $thead = array(
                    array(__('Activity'), 'col'),
                    array(__('Date'), 'col'),
                    array(__('Time'), 'col')
                );
                $tbody = array();

                // If there are no logs, display default message
                if (!is_array($logs) or empty($logs)) {
                    $tbody = array(Widget::TableRow(array(
                        Widget::TableData(
                            __('No data available.'),
                            'inactive',
                            null,
                            count($thead)
                        )),
                        'odd')
                    );
                }

                // Otherwise, build table rows
                else {
                    $bOdd = true;

                    foreach ($logs as $activity) {

                        // Format the date and time
                        $date = DateTimeObj::get(
                            __SYM_DATE_FORMAT__,
                            strtotime($activity['timestamp'] . ' GMT')
                        );
                        $time = DateTimeObj::get(
                            __SYM_TIME_FORMAT__,
                            strtotime($activity['timestamp'] . ' GMT')
                        );

                        $description = Tracker::getDescription($activity);

                        // Assemble the columns
                        $col_date = Widget::TableData($date);
                        $col_time = Widget::TableData($time);
                        $col_desc = Widget::TableData($description);

                        // Insert the row
                        if (!is_null($description)) {
                            $tbody[] = Widget::TableRow(array($col_desc, $col_date, $col_time), ($bOdd ? 'odd' : NULL));

                            $bOdd = !$bOdd;
                        }
                    }
                }

                // Assemble the table
                $table = Widget::Table(
                    Widget::TableHead($thead), null,
                    Widget::TableBody($tbody), null
                );

                $context['panel']->appendChild($table);

            break;
        }
    }

}
