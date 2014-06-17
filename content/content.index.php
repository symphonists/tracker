<?php

require_once(EXTENSIONS . '/tracker/lib/class.tracker.php');

class contentExtensionTrackerIndex extends contentBlueprintsPages
{
    public function view()
    {
        // Start building the page
        $this->setPageType('index');
        $this->setTitle(
            __('%1$s &ndash; %2$s',
            array(
                __('Symphony'),
                __('Tracker Activity')
            ))
        );

        // Add a button to clear all activity
        $clearform = new XMLElement('form');
        $clearform->setAttribute('method','post');
        $clearform->setAttribute('action',Symphony::Engine()->getCurrentPageURL());
        $button = new XMLElement('button', __('Clear All'));
        $button->setAttributeArray(array('name' => 'action[clear-all]', 'class' => 'button confirm delete', 'title' => __('Clear all activity'), 'accesskey' => 'd', 'data-message' => __('Are you sure you want to clear all activity?')));
        $clearform->appendChild($button);
        $this->appendSubheading(
            __('Tracker Activity'),
            $clearform
        );

        // Build pagination, sorting, and limiting info
        $current_page = (isset($_REQUEST['pg']) && is_numeric($_REQUEST['pg']) ? max(1, intval($_REQUEST['pg'])) : 1);
        $start = (max(1, $current_page) - 1) * Symphony::Configuration()->get('pagination_maximum_rows', 'symphony');
        $limit = Symphony::Configuration()->get('pagination_maximum_rows', 'symphony');

        // Build filter info
        $filters = array();

        if (isset($_REQUEST['filter'])) {

            list($column, $value) = explode(':', $_REQUEST['filter'], 2);
            $values = explode(',', $value);
            $filters[$column] = array();

            foreach ($values as $value) {
                $filters[$column][] = rawurldecode($value);
            }
        }

        // Fetch activity logs
        $logs = Tracker::fetchActivities($filters, $limit, $start);

        // Build the table
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

                $col_desc->appendChild(Widget::Input("items[{$activity['id']}]", null, 'checkbox'));

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
        $table->setAttribute('class','selectable');
        $this->Form->appendChild($table);

        // Append table actions
        $options = array(
            array(null, false, __('With Selected...')),
            array('delete', false, __('Delete'))
        );

        $tableActions = new XMLElement('div');
        $tableActions->setAttribute('class', 'actions');
        $tableActions->appendChild(Widget::Apply($options));
        $this->Form->appendChild($tableActions);

        // Append pagination
        $filter_sql = Tracker::buildFilterSQL($filters);
        $sql = '
            SELECT count(id) as `count`
            FROM `tbl_tracker_activity`' .
            $filter_sql
        ;
        $per_page = Symphony::Configuration()->get('pagination_maximum_rows', 'symphony');
        $total_entries = Symphony::Database()->fetchVar('count', 0, $sql);
        $remaining_entries = max(0, $total_entries - ($start + $per_page));
        $total_pages = max(1, ceil($total_entries * (1 / $per_page)));
        $remaining_pages = max(0, $total-pages - $current_page);

        if ($total_pages > 1) {
            $ul = new XMLElement('ul');
            $ul->setAttribute('class', 'page');

            // First
            $li = new XMLElement('li');
            if($current_page > 1) $li->appendChild(Widget::Anchor(__('First'), Administration::instance()->getCurrentPageURL(). '?pg=1'));
            else $li->setValue(__('First'));
            $ul->appendChild($li);

            // Previous
            $li = new XMLElement('li');
            if($current_page > 1) $li->appendChild(Widget::Anchor(__('&larr; Previous'), Administration::instance()->getCurrentPageURL(). '?pg=' . ($current_page - 1)));
            else $li->setValue(__('&larr; Previous'));
            $ul->appendChild($li);

            // Summary
            $li = new XMLElement('li', __('Page %1$s of %2$s', array($current_page, max($current_page, $total_pages))));
            $li->setAttribute('title', __('Viewing %1$s - %2$s of %3$s entries', array(
                $start,
                ($current_page != $total_pages) ? $current_page * Symphony::Configuration()->get('pagination_maximum_rows', 'symphony') : $total_entries,
                $total_entries
            )));
            $ul->appendChild($li);

            // Next
            $li = new XMLElement('li');
            if($current_page < $total_pages) $li->appendChild(Widget::Anchor(__('Next &rarr;'), Administration::instance()->getCurrentPageURL(). '?pg=' . ($current_page + 1)));
            else $li->setValue(__('Next &rarr;'));
            $ul->appendChild($li);

            // Last
            $li = new XMLElement('li');
            if($current_page < $total_pages) $li->appendChild(Widget::Anchor(__('Last'), Administration::instance()->getCurrentPageURL(). '?pg=' . $total_pages));
            else $li->setValue(__('Last'));
            $ul->appendChild($li);

            $this->Form->appendChild($ul);
        }
    }

    public function __actionIndex()
    {
        if (isset($_POST)) {
            $checked = @array_keys($_POST['items']);

            if (@array_key_exists('clear-all', $_POST['action'])) {
                $sql = 'TRUNCATE `tbl_tracker_activity`;';
                Symphony::Database()->query($sql);
                redirect(Administration::instance()->getCurrentPageURL());
            } elseif (is_array($checked) && !empty($checked)) {

                switch ($_POST['with-selected']) {

                    case 'delete':

                        Symphony::Database()->delete('tbl_tracker_activity', ' `id` IN("' . implode('","',$checked) . '")');

                        redirect(Administration::instance()->getCurrentPageURL());
                        break;
                }
            }
        }
    }

}
