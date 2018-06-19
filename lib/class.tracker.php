<?php

require_once(TOOLKIT . '/class.sectionmanager.php');
require_once(TOOLKIT . '/class.authormanager.php');
require_once(TOOLKIT . '/class.entrymanager.php');
require_once(TOOLKIT . '/class.gateway.php');
require_once(CONTENT . '/content.blueprintspages.php');

class Tracker
{
    public static function log($item_type, $item_id, $action_type, $user_id, $timestamp)
    {
        /**
         * Build author string for the fallback username. If we've got
         * a valid author, grab the full name. Otherwise, determine
         * whether it's an anonymous front-end user or a potentially
         * malicious person trying to access the back end. In the latter
         * case, output the IP or email we captured for reference.
         */
        $author = null;
        $members = $_SESSION['sym-members'];

        if (is_numeric($user_id)) {
            $author = AuthorManager::fetchByID($user_id);
        }

        if ($author instanceof Author) {
            $username = $author->getFullName();
        }
        else if (!empty($members) || is_array($user_id)) {
            $member = array(
                'username' => $members['username'] ? $members['username'] : $user_id['username'],
                'email' => $members['email'] ? $members['email'] : $user_id['email'],
                'section-id' => $members['members-section-id'],
                'id' => $members['id']
            );

            $username = Tracker::getMemberUsername($member);
            $user_id = null;
        }
        else if (is_numeric($item_type)) {
            $username = __('A front-end user');
        }
        else {
            $username = __('An unidentified user (%s)', array($item_id));
        }

        // Build the $data array for our table columns
        $data = array(
            'item_type'             => $item_type,
            'item_id'               => $item_id,
            'action_type'           => $action_type,
            'user_id'               => $user_id,
            'timestamp'             => $timestamp,
            'fallback_username'     => static::truncateValue($username)
        );

        /**
         * Build the fallback description. Used if the item gets deleted.
         * If the item type is numeric, we're dealing with an entry,
         * otherwise it's some other system element. They're formatted
         * differently.
         */
        if (is_numeric($item_type)) {
            $data['fallback_description'] = static::truncateValue(static::formatEntryItem($data, true));
        } else {
            $data['fallback_description'] = static::truncateValue(static::formatElementItem($data, true));
        }

        // Push it into the DB.
        Symphony::Database()->insert($data, 'tbl_tracker_activity');

        // Send the event to the URL if specificed
        $notify_url = Symphony::Configuration()->get('notify_url', 'tracker');
        $notify_urls_array = preg_split('/[\s,]+/', $notify_url);
        foreach($notify_urls_array as $url) {
            $gateway = new Gateway;
            $gateway->init($url . "?". http_build_query($data));
            $gateway->exec();
        }
    }

    public static function getMemberUsername($member)
    {
        if (empty($member['username']) && empty($member['email'])) {
            $username = __('An unkown Member');
        }
        else if (!empty($member['section-id']) && !empty($member['id'])) {
            $sectionID = $member['section-id'] ? $member['section-id'] : extension_Members::getMembersSection();
            $sectionHandle = (new SectionManager)
                ->select()
                ->section($sectionID)
                ->execute()
                ->next()
                ->get('handle');
            $link = SYMPHONY_URL . '/publish/' . $sectionHandle . '/edit/' . $member['id'] . '/';
            $name = $member['username'] ? $member['username'] : $member['email'];

            $username = __('Member %s', array('<a href="' . $link . '">' . $name . '</a>'));
        }
        else {
            $name = $member['username'] ? $member['username'] : $member['email'];
            $username = __('Member %s', array($name));
        }

        return $username;
    }

    public static function truncateValue($value, $max = 2048)
    {
        if (General::strlen($value) < $max) {
            return $value;
        }
        $value = strip_tags($value);
        if (General::strlen($value) > $max) {
            $value = General::substr($value, 0, $max);
        }
        return $value;
    }

    public static function fetchActivities($filters = array(),$limit = null, $start = 0, $sort = 'timestamp', $order = 'DESC')
    {
        // Build the filter SQL.
        $filter_sql = static::buildFilterSQL($filters);

        // Run the query.
        $q = Symphony::Database()
            ->select(['*'])
            ->from('tbl_tracker_activity');

        foreach ($filter_sql as $key => $value) {
            $q->where([$key => $value]);
        }

        $q->orderBy($sort, $order);

        if ($limit !== null) {
            $q->limit(intval($limit));
        }

        return $q
            ->execute()
            ->rows();
    }

    /**
     * Function for building filter SQL while fetching activities.
     * Expects a multi-dimensional array, where each key corresponds
     * to a field.
     *
     *      $filters = array(
     *          'item_type' => array(
     *              'entries',
     *              'pages'
     *          ),
     *          'action_type' => array(
     *              'updated',
     *              'created'
     *          ),
     *          'user_id' => array(
     *              1
     *          )
     *      );
     *
     * It's possible to use custom SQL for a field by passing a string
     * of SQL rather than an array:
     *
     *      $filters = array(
     *          'item_type' => 'REGEXP "[[:digit:]]+"'
     *      );
     *
     */
    public static function buildFilterSQL($filters = array())
    {
        $columns = Symphony::Database()
            ->describe('tbl_tracker_activity')
            ->execute()
            ->rows();

        foreach($columns as $key => $column) {
            $columns[$key] = $column['Field'];
        }

        $filter_sql = array();

        // If we've got a $filters array, let's build the SQL
        if (!empty($filters) && is_array($filters)) {

            // Iterate over the field filters
            foreach($filters as $field => $options) {
                // Prevent fatal error when filter field doesn't exist
                if (!in_array($field, $columns)) {
                    return array();
                }

                if (count($options) < 2) {
                    $filter_sql[$field] = $options[0];
                }
                else {
                    $opts = array();

                    foreach($options as $option) {
                        $opts[] = [$field => $option];
                    }

                    $filter_sql['or'] = $opts;
                }
            }
        }

        return $filter_sql;
    }

    public static function getDescription(array $activity)
    {
        $author_string = static::formatAuthorString(
            $activity['user_id'],
            $activity['fallback_username']
        );

        // If the item type is numeric, we're dealing with an entry
        if (is_numeric($activity['item_type'])) {
            $item = static::formatEntryItem($activity);
        }

        // Otherwise, it's a system element
        else {
            $item = static::formatElementItem($activity);
        }

        // Concat author string, activity type, and an item description
        if (!is_null($item)) {
            $replacements = array(
                $author_string,
                $item
            );

            // Don't merge description so make sure each string can be translated accurately:
            // this is important if other languages need reflexive or splitted verbs (like German for example)
            switch($activity['action_type']) {

                case 'deleted':
                    $description = __('%1$s deleted %2$s.', $replacements);
                    break;

                case 'updated':
                    $description = __('%1$s updated %2$s.', $replacements);
                    break;

                case 'created':
                    $description = __('%1$s created %2$s.', $replacements);
                    break;

                case 'enabled':
                    $description = __('%1$s enabled %2$s.', $replacements);
                    break;

                case 'disabled':
                    $description = __('%1$s disabled %2$s.', $replacements);
                    break;

                case 'logged in':
                    $description = __('%1$s logged in %2$s.', $replacements);
                    break;

                case 'attempted to log in':
                    $description = __('%1$s attempted to log in %2$s.', $replacements);
                    break;

                case 'reset':
                    $description = __('%1$s reset %2$s.', $replacements);
                    break;

                case 'attempted to reset':
                    $description = __('%1$s attempted to reset %2$s.', $replacements);
                    break;

                case 'changed':
                    $description = __('%1$s changed %2$s.', $replacements);
                    break;

                case 'requested to reset':
                    $description = __('%1$s requested to reset %2$s.', $replacements);
                    break;

                case 'uninstalled':
                    $description = __('%1$s uninstalled %2$s.', $replacements);
                    break;

                case 'regenerated':
                    $description = __('%1$s regenerated %2$s.', $replacements);
                    break;

                case 'activated':
                    $description = __('%1$s activated %2$s.', $replacements);
                    break;

                case 'requested':
                    $description = __('%1$s requested %2$s.', $replacements);
                    break;

                default:
                    $description = __('%1$s %2$s %3$s.', array(
                        $author_string,
                        $activity['action_type'],
                        $item
                    ));
                    break;
            }

            return $description;
        }
    }

    public static function formatEntryItem($activity, $fallback = false)
    {
        // Fetch the entry and its section
        $entry = (new EntryManager)
            ->select()
            ->entry($activity['item_id'])
            ->execute()
            ->next();
        $entry = $entry[0];
        $section = (new SectionManager)
            ->select()
            ->section($activity['item_type'])
            ->execute()
            ->next();

        // If the entry no longer exists, get the fallback entry description
        if (!($entry instanceof Entry) || !($section instanceof Section)) {
            $entry_string = explode(
                ':::',
                $activity['fallback_description']
            );
            $entry_string = '"' . $entry_string[0] . '"';
        }

        // Otherwise grab the primary field value and build the entry string
        else {
            $primary_field = reset($section->fetchVisibleColumns());
            if ($primary_field) {
                $data = $entry->getData($primary_field->get('id'));
                $value = $primary_field->prepareReadableValue($data);

                // If we're creating the fallback, just return a string
                if ($fallback) {
                    $entry_string = $value;
                }

                // Otherwise build a link to the entry
                else {
                    $entry_string = Widget::Anchor(
                        !$value ? 'unknown' : (string)$value,
                        SYMPHONY_URL . '/publish/' . $section->get('handle') . '/edit/' . $activity['item_id']
                    )->generate();
                }
            }
            // using limit section entries?
            else {
                $fallback = true;
            }
        }

        // If the section no longer exists, get the fallback section description
        if (!($section instanceof Section)) {
            $section_string = explode(
                ':::',
                $activity['fallback_description']
            );
            $section_string = $section_string[1];
        }

        // Otherwise build a fallback
        elseif ($fallback) {
            $section_string = $section->get('name');
        }

        // Or build a link to the section
        else {
            $section_string = Widget::Anchor(
                $section->get('name'),
                SYMPHONY_URL . '/blueprints/sections/edit/' . $activity['item_type']
            )->generate();
        }

        // Use a unique delimiter for the fallback so we can fetch each string independently
        if ($fallback) {
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

    public static function formatElementItem($activity, $fallback = false)
    {
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
                if (empty($page)) {
                    $item = $activity['fallback_description'];
                }

                // Otherwise, if it was the template that was edited, build a description
                elseif ($is_template) {
                    $item = __(
                        ' the %1s page %2s',
                        array(
                            $page[0]['title'],
                            ($fallback ? __('template') : Widget::Anchor(
                                __('template'),
                                SYMPHONY_URL . '/blueprints/pages/template/' . $activity['item_id']
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
                                SYMPHONY_URL . '/blueprints/pages/edit/' . $activity['item_id']
                            )->generate())
                        )
                    );
                }
                break;

            case "events":

                // Grab the event info
                $handle = EventManager::__getHandleFromFilename($activity['item_id']);
                $about = EventManager::about($handle);

                // If the event no longer exists, use the fallback description
                if (empty($about)) {
                    $item = $activity['fallback_description'];
                }

                // Otherwise, build the description
                else {
                    $item = __(
                        ' the %1s event',
                        array(
                            ($fallback ? $about['name'] : Widget::Anchor(
                                $about['name'],
                                SYMPHONY_URL . '/blueprints/events/edit/' . $handle
                            )->generate())
                        )
                    );
                }
                break;

            case "datasources":

                // Grab the DS info
                $handle = DatasourceManager::__getHandleFromFilename($activity['item_id']);
                $about = DatasourceManager::about($handle);

                // If the DS no longer exists, use the fallback description
                if (empty($about)) {
                    $item = $activity['fallback_description'];
                }

                // Otherwise, build the item description
                else {
                    $item = __(
                        ' the %1s data source',
                        array(
                            ($fallback ? $about['name'] : Widget::Anchor(
                                $about['name'],
                                SYMPHONY_URL . '/blueprints/datasources/edit/' . $handle
                            )->generate())
                        )
                    );
                }
                break;

            case "utilities":

                // If the utility no longer exists, use the fallback description
                if (!file_exists(UTILITIES . '/' . $activity['item_id'])) {
                    $item = $activity['fallback_description'];
                }

                // Otherwise, build a description
                else {
                    $item = __(
                        ' the %1s utility',
                        array(
                            ($fallback ? $activity['item_id'] : Widget::Anchor(
                                $activity['item_id'],
                                SYMPHONY_URL . '/blueprints/utilities/edit/' . str_replace('.xsl', '', $activity['item_id'])
                            )->generate())
                        )
                    );
                }
                break;

            case "sections":

                // Grab the section info
                $section = (new SectionManager)
                    ->select()
                    ->section($activity['item_id'])
                    ->execute()
                    ->next();

                // If the section no longer exists, use the fallback description
                if (!($section instanceof Section)) {
                    $item = $activity['fallback_description'];
                }

                // Otherwise build a description
                else {
                    $item = __(
                        ' the %1s section',
                        array(
                            ($fallback ? $section->get('name') : Widget::Anchor(
                                $section->get('name'),
                                SYMPHONY_URL . '/blueprints/sections/edit/' . $activity['item_id']
                            )->generate())
                        )
                    );
                }

                break;

            case "authors":

                // Grab the author info
                $author = AuthorManager::fetchByID($activity['item_id']);

                // If the author no longer exists, use the fallback description
                if (!($author instanceof Author)) {
                    $item = $activity['fallback_description'];
                }

                // Otherwise, build the description
                else  {

                    // If the author edited their own record
                    if ($activity['user_id'] == $activity['item_id']) {
                        $item = __(
                            ' their %1s',
                            array(
                                ($fallback ? __('author record') : Widget::Anchor(
                                    __('author record'),
                                    SYMPHONY_URL . '/system/authors/edit/' . $activity['item_id']
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
                                    SYMPHONY_URL . '/system/authors/edit/' . $activity['item_id']
                                )->generate())
                            )
                        );
                    }
                }

                break;

            case "preferences":
                $item = __(
                    ' the %s',
                    array(
                        Widget::Anchor(
                            __('system preferences'),
                            SYMPHONY_URL . '/system/preferences'
                        )->generate()
                    )
                );

            break;

            case "maintenance-mode":
                $item = __(' maintenance mode');

            break;

            case "extensions":
                try {
                    $about = ExtensionManager::about($activity['item_id']);
                }
                catch (Exception $e) {
                    $about = null;
                }
                if (empty($about)) {
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

            case "login":
                $item = __(' to the back end');
            break;

            case "password-reset":
                $item = __(' their password');
            break;

            case "members-login":
                $item = __(' to the front-end');
            break;

            case "members-login-failure":
                $item = __(' to the front-end');
            break;

            case "members-activation":
                $item = __(' their account');
            break;

            case "members-regenerate-activation":
                $item = __(' their activation code');
            break;

            case "members-forgot-password":
                $item = __(' a recovery code');
            break;

            default:
                $item = null;
            break;
        }

        return $item;
    }

    public static function formatAuthorString($id, $username)
    {
        // Get author info
        $author = AuthorManager::fetchByID($id);

        // If the author no longer exists, use the fallback name
        if (!($author instanceof Author)) {
            $author_string = $username;
        }

        // Otherwise generate a link to the author record
        else {
            $author_string = Widget::Anchor(
                $author->getFullName(),
                SYMPHONY_URL . '/system/authors/edit/' . $id
            )->generate();
        }
        return $author_string;
    }

    public static function Author()
    {
        if (is_callable(array('Symphony', 'Author'))) {
            return Symphony::Author();
        }
        return Symphony::instance()->Author;
    }
}
