<?php

	require_once(EXTENSIONS . '/tracker/lib/class.tracker.php');

	class Extension_Tracker extends Extension {

		public function about() {
			return array(
				'name'			=> 'Tracker',
				'version'		=> '0.9.3',
				'release-date'	=> '2010-08-12',
				'author'		=> array(
					'name'			=> 'craig zheng',
					'email'			=> 'craig@symphony-cms.com'
				),
				'description'	=> 'Track user and system activity.'
			);
		}
		
		public function fetchNavigation() {
			return array(
				array(
					'location'	=> 'System',
					'name'		=> 'Tracker Activity',
					'link'		=> '/',
					'limit'		=> 'developer'
				)
			);
		}

		public function getSubscribedDelegates() {
			return array(
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
				array(
					'page' 		=> '/publish/new/',
					'delegate'	=> 'EntryPostCreate',
					'callback'	=> 'parseEntryCreate'
				),
				array(
					'page' 		=> '/publish/edit/',
					'delegate'	=> 'EntryPostEdit',
					'callback'	=> 'parseEntryEdit'
				),
				array(
					'page' 		=> '/publish/',
					'delegate'	=> 'Delete',
					'callback'	=> 'parseEntryDelete'
				),
				array(
					'page' 		=> '/backend/',
					'delegate'	=> 'InitaliseAdminPageHead',
					'callback'	=> 'parsePageLoad'
				)
			);
		}
		
		public function install() {
			Symphony::Database()->query(
				'CREATE TABLE `tbl_tracker_activity` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`item_type` varchar(255),
					`item_id` varchar(75),
					`action_type` varchar(255),
					`user_id` int(11),
					`timestamp` timestamp,
					`fallback_username` varchar(255),
					`fallback_description` varchar(255),
					PRIMARY KEY (`id`)
				);');
			return;
		}

		public function uninstall() {
			Symphony::Database()->query(
				'DROP TABLE `tbl_tracker_activity`;'
			);
			Administration::instance()->Configuration->remove(
				'excluded-sections',
				'tracker'
			);
			Administration::instance()->Configuration->remove(
				'excluded-users',
				'tracker'
			);
			Administration::instance()->Configuration->remove(
				'excluded-system-elements',
				'tracker'
			);
			Administration::instance()->saveConfig();
		}
		
		public function parsePageLoad($context) {
		
			$page = $context['parent']->Page;
			$assets_path = '/extensions/tracker/assets/';

			$page->addStylesheetToHead(URL . $assets_path . 'tracker.css', 'screen', 150);
	
		// Only proceed if the current author is not excluded from tracking
			if($this->validateUser($page->_Parent->Author->get("id"))) {
		
			// Set some contextual info
				$page_url = $page->_Parent->getCurrentPageURL();
				$callback = $page->_Parent->getPageCallback($page);

			// Proceed only if a non-entry has been created, updated, or deleted	
				if(!preg_match('#\/publish\/#', $page_url) && (preg_match('#\/(saved|created)\/#', $page_url) || @array_key_exists('delete', $_POST['action']) || !empty($_POST['with-selected']))) {
				
				// Avoid false duplicates due to POST->redirect->GET loop
					if($_SERVER['REQUEST_METHOD'] == 'POST' && preg_match('#\/(saved|created)\/#', $_SERVER['HTTP_REFERER'])) { return; }
		
				// Grab list of excluded elements
					$excluded_elements = explode(',', Symphony::Configuration()->get('excluded-system-elements', 'tracker'));
			
				// Sanitize the item type for exclusion checking
					if(preg_match('/blueprints/', $callback['driver'])) {
						$type = str_replace('blueprints','',$callback['driver']);
					} elseif(preg_match('/system/', $callback['driver'])) {
						$type = str_replace('system','', $callback['driver']);
					} else {
						$type = $callback['driver'];
					}
		
				// Proceed only if the item type isn't excluded from tracking
					if(!in_array($type, $excluded_elements)) {
						$timestamp = DateTimeObj::getGMT('Y-m-d H:i:s', time());
		
					// Sanitize/standardize the action type
						if(@array_key_exists('delete', $_POST['action']) || $_POST['with-selected'] == 'delete') {
							$action = 'deleted';
						}
						elseif ($_POST['with-selected'] == 'enable' || $_POST['with-selected'] == 'disable' || $_POST['with-selected'] == 'uninstall') {
							$action = $_POST['with-selected'] . ($_POST['with-selected'] == 'uninstall' ? 'ed' : 'd');						
						}
						elseif($callback['context'][2] == 'saved') {
							$action = 'updated';
						}
						else {
							$action = $callback['context'][2];
						}
				
					// If it was a bulk operation, grab the IDs
						if(!empty($_POST['with-selected'])) {
							$items = array_keys($_POST['items']);
						}
					// Otherwise we already have the ID in the context
						else {
							$items = array($callback['context'][1]);
						}
		
					// Log em
						foreach($items as $item) {
							Tracker::log(
								$type,
								$item,
								$action,
								$page->_Parent->Author->get('id'),
								$timestamp
							);
						}
					}
				}
			}
		}
		
		public function parseEntryCreate($entry) {
		
		// If the current author isn't excluded from tracking, prepare the log
			if($this->validateUser($entry['entry']->_engine->_user_id)) {
				$this->prepareEntryLog($entry, 'created');
			}
		}
		
		public function parseEntryEdit($entry) {
		
		// If the current author isn't excluded from tracking, prepare the log
			if($this->validateUser($entry['entry']->_engine->_user_id)) {
				$this->prepareEntryLog($entry, 'updated');
			}
		}
		
		public function parseEntryDelete($entry) {
		
		// If the current author isn't excluded from tracking, prepare the log
			$author = $entry['parent']->Author->get('id');
			if($this->validateUser($author)){
				
				include_once(TOOLKIT . '/class.sectionmanager.php');
				$sm = new SectionManager($entry['parent']);
				
				$entries = (array)$entry['entry_id'];
				foreach($entries as $id){
					
				// Find the section ID
					$page_url = $entry['parent']->getCurrentPageURL();
					$section_url = preg_replace('#http:(.)*publish/#','',$page_url);
					$section_url = explode('/', $section_url);
			
					$section_id = $sm->fetchIDFromHandle($section_url[0]);
		
				// If the section's not excluded from tracking, log it
					if($this->validateSection($section_id)){
						Tracker::log(
							$section_id,
							$id,
							'deleted',
							$author,
							DateTimeObj::getGMT('Y-m-d H:i:s', time())
						);
					}
				}
			}
		}
		
		public function prepareEntryLog($entry, $type) {
		
		// If the section's not excluded from tracking, log it
			if($this->validateSection($entry['entry']->_fields['section_id'])){
				Tracker::log(
					$entry['entry']->_fields['section_id'],
					$entry['entry']->_fields['id'],
					$type,
					$entry['entry']->_engine->_user_id,
					DateTimeObj::getGMT('Y-m-d H:i:s', time())
				);
			}
		}
		
		public function validateSection($id) {
			$excluded_sections = explode(',',Symphony::Configuration()->get('excluded-sections', 'tracker'));
			
			if(in_array($id, $excluded_sections)) {
				return FALSE;
			}
			else {
				return TRUE;
			}
		}	
		
		public function validateUser($id) {
			$excluded_users = explode(',',Symphony::Configuration()->get('excluded-users', 'tracker'));
			
			if(in_array($id, $excluded_users)) {
				return FALSE;
			}
			else {
				return TRUE;
			}
		}

		public function appendPreferences($context){
			
			include_once(TOOLKIT . '/class.authormanager.php');
			include_once(TOOLKIT . '/class.sectionmanager.php');
		
		// Fieldset and layout
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Tracker'));

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
				'extensions'	=> __('Extensions')
			);
			$excluded_elements = explode(',', Symphony::Configuration()->get('excluded-system-elements', 'tracker'));

			foreach($elements as $handle => $value) {
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
			
			$sm = new SectionManager($this->_Parent);
			$sections = $sm->fetch();
			$excluded_sections = explode(',', Symphony::Configuration()->get('excluded-sections', 'tracker'));

			if(!empty($sections) && is_array($sections)){
				foreach($sections as $section) {
					$selected = (in_array($section->_data['id'], $excluded_sections) ? TRUE : FALSE);
					$options[] = array(
						$section->_data['id'],
						$selected,
						$section->_data['name']
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
			
			$am = new AuthorManager();
			$authors = $am->fetch();
			$excluded_authors = explode(',',Symphony::Configuration()->get('excluded-users', 'tracker'));

			if(!empty($authors) && is_array($authors)){
				foreach($authors as $author) {
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
			$context['wrapper']->appendChild($group);
		}
		
		public function savePreferences() {
		
		// Remove pre-existing preferences
			Administration::instance()->Configuration->remove(
				'excluded-sections',
				'tracker'
			);
			Administration::instance()->Configuration->remove(
				'excluded-users',
				'tracker'
			);
			Administration::instance()->Configuration->remove(
				'excluded-system-elements',
				'tracker'
			);
			
		// If preferences have been set, save them
			if(is_array($_POST['settings']['tracker'])){
				foreach($_POST['settings']['tracker'] as $preference => $value){
					if(is_array($value)){
						$_POST['settings']['tracker'][$preference] = implode(',',$value);
					}
				}
			}
			
		// If the author or preferences isn't excluded from tracking, log the update
			$author = $this->_Parent->Author->get("id");
			
			if($this->validateUser($author) && !in_array('preferences', (array)$_POST['settings']['tracker']['excluded-system-elements'])){
				Tracker::log(
					'preferences',
					NULL,
					'updated',
					$author,
					DateTimeObj::getGMT('Y-m-d H:i:s', time())
				);
			}
		}

	}
