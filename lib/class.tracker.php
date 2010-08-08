<?php

	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.authormanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(CONTENT . '/content.blueprintspages.php');

	Class Tracker {

		public function log($item_type, $item_id, $action_type, $user_id, $timestamp) {
		
			$author = AuthorManager::fetchByID($user_id);
		
			$data = array(
				'item_type'				=> $item_type,
				'item_id'				=> $item_id,
				'action_type'			=> $action_type,
				'user_id'				=> $user_id,
				'timestamp'				=> $timestamp,
				'fallback_username'		=> $author->getFullName()
			);
			
		// If the item type is numeric, we're dealing with an entry
			if(is_numeric($item_type)) {
				$data['fallback_description'] = Tracker::formatEntryItem($data, TRUE);
			} else {
		// Otherwise, it's a system element
				$data['fallback_description'] = Tracker::formatElementItem($data, TRUE);
			}
			
			Symphony::Database()->insert($data, 'tbl_tracker_activity');
		}
		
		public function fetchActivities(array $filters,$limit=NULL,$start=0,$sort='timestamp',$order='DESC') {
		
		// Build the filter SQL
			$filter_sql = Tracker::buildFilterSQL($filters);
		
		// Run the query
			$activities = Symphony::Database()->fetch('
				SELECT
					*
				FROM
					`tbl_tracker_activity`' .
				$filter_sql .
				' ORDER BY `' .
					$sort . '` ' . $order
				. ($limit ? ' LIMIT ' . intval($start) . ', ' . intval($limit) : '')
			);
			
			return $activities;
		}
		
		/**
		 * Function for building filter SQL while fetching activities.
		 * Expects a multi-dimensional array, where each key corresponds
		 * to a field.
		 *
		 * 		$filters = array(
		 *			'item_type' => array(
		 *				'entries',
		 *				'pages'
		 *			),
		 *			'action_type' => array(
		 *				'updated',
		 *				'created'
		 *			),
		 *			'user_id' => array(
		 *				1
		 *			)
		 *		);
		 *
		 * It's possible to use custom SQL for a field by passing a string
		 * of SQL rather than an array:
		 *
		 * 		$filters = array(
		 * 			'item_type' => 'REGEXP "[[:digit:]]+"'
		 * 		);
		 *
		**/
		public function buildFilterSQL($filters) {
		
			$columns = Symphony::Database()->fetch('DESCRIBE `tbl_tracker_activity`');
			foreach($columns as $key => $column) {
				$columns[$key] = $column['Field'];
			}
			
			$filter_sql = '';
			
		// If we've got a $filters array, let's build the SQL
		// TODO: I imagine this can be made more elegant
		
			if(!empty($filters) && is_array($filters)) {
				$filter_sql .= ' WHERE ';
				$i = 0;
				
			// Iterate over the field filters
				foreach($filters as $field => $options) {
				
				// Prevent fatal error when filter field doesn't exist
					if(!in_array($field,$columns)) { return; }
				
				// If there's more than one field filter
					if($i > 0) {
						$filter_sql .= ' AND ';
					}
				
				// Allow custom SQL by passing a string
				// TODO: Is this a security concern?
				
					if(!is_array($options)) {
						$filter_sql .= '`' . $field . '` ' . $options . ' ';
					}
					
				// Iterate over the filter values and group them with OR
					else {
						foreach($options as $num => $option) {
							if($num == 0 && count($options) > 1) {
								$filter_sql .= ' (';
							}
							if($num > 0) {
								$filter_sql .= ' OR ';
							}
							$filter_sql .= '`' . $field . '` = "' . $option . '"';
							if(count($options) > 1 && $option == end($options)) {
								$filter_sql .= ')';
							}
						}
					}
					$i++;
				}
			}
			
			return $filter_sql;
		}
		
		public function getDescription(array $activity) {
		
			$author_string = Tracker::formatAuthorString(
				$activity['user_id'],
				$activity['fallback_username']
			);
			
		// If the item type is numeric, we're dealing with an entry
			if(is_numeric($activity['item_type'])) {
				$item = Tracker::formatEntryItem($activity);
			} else {
		// Otherwise, it's a system element
				$item = Tracker::formatElementItem($activity);
			}
			
		// Concat author string, activity type, and an item description
			$description = __(
				'%1s %2s %3s.',
				array(
					$author_string,
					__($activity['action_type']),
					$item
				) 
			);
					
			return $description;
		}
		
		public function formatEntryItem($activity, $fallback=FALSE) {
		
		// Fetch the entry and its section
			$entryManager = new EntryManager($context['parent']);
			$entry = $entryManager->fetch($activity['item_id']);
			$entry = $entry[0];
			$section = SectionManager::fetch($activity['item_type']);
		
		// If the entry no longer exists, get the fallback entry description
			if(!($entry instanceof Entry) || !($section instanceof Section)) {
			 	$entry_string = explode(
			 		':::',
			 		$activity['fallback_description']
			 	);
			 	$entry_string = '"' . $entry_string[0] . '"';
			}
		// Otherwise grab the primary field value and build the entry string
			else {
				$data = $entry->getData();
				$primary_field = array_shift($data);
			
			// If we're creating the fallback, just return a string
				if($fallback) {
					$entry_string = $primary_field['value'];
				}
			// Otherwise build a link to the entry
				else {				
					$entry_string = Widget::Anchor(
						$primary_field['value'],
						URL . '/symphony/publish/' . $section->get('handle') . '/edit/' . $activity['item_id']
					)->generate();
				}
			}
			
		// If the section no longer exists, get the fallback section description
			if(!($section instanceof Section)) {
				$section_string = explode(
					':::',
					$activity['fallback_description']
				);
				$section_string = $section_string[1];
			}
		// Otherwise build a fallback
			elseif($fallback) {
				$section_string = $section->get('name');
			} 
		// Or build a link to the section
			else {
				$section_string = Widget::Anchor(
					$section->get('name'),
					'/symphony/blueprints/sections/edit/' . $activity['item_type']
				)->generate();
			}
			
		// Use a unique delimiter for the fallback so we can fetch each string independently
			if($fallback) {
				$item = $entry_string . ':::' . $section_string;
			}
		// Or build the full description with links
			else {
				$item = __(
					' %1s in the %2s section',
					array(
						$entry_string,
						$section_string
					)
				);
			}
			
			return $item;
		}
		
		public function formatElementItem($activity, $fallback=FALSE){
			$sectionManager = new SectionManager($context['parent']);
			$eventManager = new EventManager($context['parent']);
			$dsManager = new DatasourceManager($context['parent']);
			
			switch($activity['item_type']) {
			
			// Pages and Page Templates
				case 'pages':
				
				// Is is a Page Template?
					$is_template = !is_numeric($activity['item_id']);
				
				// Fetch the page from the DB
					$page = Symphony::Database()->fetch('
						SELECT `title`
						FROM `tbl_pages`
						WHERE `' . ($is_template ? 'handle' : 'id') . '` = "' . $activity['item_id'] . '"'
					);
				
				// If the page no longer exists, use the fallback description
					if(empty($page)) {
						$item = $activity['fallback_description'];
					}
				// Otherwise, if it was the template that was edited, build a description
					elseif($is_template) {
						$item = __(
							' the %1s page %2s',
							array(
								$page[0]['title'],
								($fallback ? __('template') : Widget::Anchor(
									__('template'),
									URL . '/symphony/blueprints/pages/template/' . $activity['item_id']
								)->generate())
							)
						);
				// Or if it was the page config, build that description
					} else {
						$item = __(
							' the %1s page',
							array(
								($fallback ? $page[0]['title'] : Widget::Anchor(
									$page[0]['title'],
									URL . '/symphony/blueprints/pages/edit/' . $activity['item_id']
								)->generate())
							)
						);
					}
					break;
					
				case "events":
				
				// Grab the event info
					$about = $eventManager->about($activity['item_id']);
				
				// If the event no longer exists, use the fallback description
					if(empty($about)) {
						$item = $activity['fallback_description'];
					}
				// Otherwise, build the description
					else {
						$item = __(
							' the %1s event',
							array(
								($fallback ? $about['name'] : Widget::Anchor(
									$about['name'],
									URL . '/symphony/blueprints/datasources/edit/' . $activity['item_id']
								)->generate())
							)
						);
					}
					break;
					
				case "datasources":
				
				// Grab the DS info
					$about = $dsManager->about($activity['item_id']);
					
				// If the DS no longer exists, use the fallback description
					if(empty($about)) {
						$item = $activity['fallback_description'];
					}
				// Otherwise, build the item description
					else {
						$item = __(
							' the %1s data source',
							array(
								($fallback ? $about['name'] : Widget::Anchor(
									$about['name'],
									URL . '/symphony/blueprints/datasources/edit/' . $activity['item_id']
								)->generate())
							)
						);
					}
					break;
					
				case "utilities":
				
				// If the utility no longer exists, use the fallback description
					if(!file_exists(UTILITIES . '/' . $activity['item_id'] . '.xsl')) {
						$item = $activity['fallback_description'];
					}
				// Otherwise, build a description
					else {
						$item = __(
							' the %1s utility',
							array(
								($fallback ? $activity['item_id'] . '.xsl' : Widget::Anchor(
									$activity['item_id'] . '.xsl',
									URL . '/symphony/blueprints/utilities/edit' . $activity['item_id']
								)->generate())
							)
						);
					}
					break;
					
				case "sections":
				
				// Grab the section info
					$section = $sectionManager->fetch($activity['item_id']);
				
				// If the section no longer exists, use the fallback description	
					if(!($section instanceof Section)) {
						$item = $activity['fallback_description'];
					}
				// Otherwise build a description
					else {
						$item = __(
							' the %1s section',
							array(
								($fallback ? $section->get('name') : Widget::Anchor(
									$section->get('name'),
									URL . '/symphony/blueprints/sections/edit/' . $activity['item_type']
								)->generate())
							)
						);
					}
					
					break;
					
				case "authors":
				
				// Grab the author info
					$author = AuthorManager::fetchByID($activity['item_id']);
					
				// If the author no longer exists, use the fallback description
					if(!($author instanceof Author)) {
						$item = $activity['fallback_description'];
					}
				// Otherwise, build the description
					else  {
						
					// If the author edited their own record
						if($activity['user_id'] == $activity['item_id']) {
							$item = __(
								' his/her %1s',
								array(
									($fallback ? __('author record') : Widget::Anchor(
										__('author record'),
										URL . '/symphony/system/authors/edit/' . $activity['item_id']
									)->generate())
								)
							);
						}
					// If it's another person's author record
						else {
							$item = __(
								' the author record for %1s',
								array(
									($fallback ? $author->getFullName() : Widget::Anchor(
										$author->getFullName(),
										URL . '/symphony/system/authors/edit/' . $activity['item_id']
									)->generate())
								)
							);
						}
					}
					
					break;

				case "preferences":
					$item = __(
						' the ' . 
						Widget::Anchor(
							'system preferences', 
							URL . '/symphony/system/preferences'
						)->generate()
					);
					
				break;
				
				case "extensions":
					$about = Administration::instance()->ExtensionManager->about($activity['item_id']);
					if(empty($about)) {
						$item = $activity['fallback_description'];
					}
					else {
						$item = __(
							'the %1s extension',
							array(
								$about['name']
							)
						);
					}
				break;
			}
			
			return $item;
		}
		
		public function formatAuthorString($id, $username) {
		// Get author info
			$author = AuthorManager::fetchByID($id);
			
		// If the author no longer exists, use the fallback name
			if(!($author instanceof Author)) {
				$author_string = $username;
			}
		// Otherwise generate a link to the author record
			else {
				$author_string = Widget::Anchor(
					$author->getFullName(),
					'/symphony/system/authors/edit/' . $id
				)->generate();
			}
			return $author_string;
		}
		
	}
