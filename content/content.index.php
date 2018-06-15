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

        // Add a button to clear all activity, if developer
        if (Tracker::Author()->isDeveloper()) {
            $clearform = Widget::Form(Symphony::Engine()->getCurrentPageURL(), 'post');
            Widget::registerSVGIcon(
                'close',
                '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="19.9px" height="19.9px" viewBox="0 0 19.9 19.9"><path fill="currentColor" d="M1,19.9c-0.3,0-0.5-0.1-0.7-0.3c-0.4-0.4-0.4-1,0-1.4L18.2,0.3c0.4-0.4,1-0.4,1.4,0s0.4,1,0,1.4L1.7,19.6C1.5,19.8,1.3,19.9,1,19.9z"/><path fill="currentColor" d="M18.9,19.9c-0.3,0-0.5-0.1-0.7-0.3L0.3,1.7c-0.4-0.4-0.4-1,0-1.4s1-0.4,1.4,0l17.9,17.9c0.4,0.4,0.4,1,0,1.4C19.4,19.8,19.2,19.9,18.9,19.9z"/></svg>'
            );
            $button = new XMLElement(
                'button',
                Widget::SVGIcon('close') . '<span><span>' . __('Clear All') . '</span></span>'
            );
            $button->setAttributeArray(array('name' => 'action[clear-all]', 'class' => 'button confirm delete', 'title' => __('Clear all activity'), 'accesskey' => 'd', 'data-message' => __('Are you sure you want to clear all activity?')));
            $clearform->appendChild($button);

            if (Symphony::Engine()->isXSRFEnabled()) {
                $clearform->prependChild(XSRF::formToken());
            }

            $this->appendSubheading(
                __('Tracker Activity'),
                $clearform
            );
        }

        // Build pagination, sorting, and limiting info
        $current_page = (isset($_REQUEST['pg']) && is_numeric($_REQUEST['pg']) ? max(1, intval($_REQUEST['pg'])) : 1);
        $start = (max(1, $current_page) - 1) * Symphony::Configuration()->get('pagination_maximum_rows', 'symphony');
        $limit = Symphony::Configuration()->get('pagination_maximum_rows', 'symphony');

        // Build filter info
        $filters = array();

        if (isset($_REQUEST['filter'])) {
            foreach (explode('-', $_REQUEST['filter']) as $key => $value) {
                $filter = explode(':', $value);
                $filters[$filter[0]] = explode(',', rawurldecode($filter[1]));
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
                )))
            );
        }

        // Otherwise, build table rows
        else {
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
                $description_class = '';

                // Row class
                $row_class = null;
                if ($activity['action_type'] === 'created') {
                    $row_class = 'status-ok';
                }
                elseif ($activity['action_type'] === 'deleted') {
                    $row_class = 'status-error';
                }

                if (is_null($description)) {
                    if (!empty($activity['fallback_description'])) {
                        $description = $activity['fallback_description'];
                    } else {
                        $description = __('None found.');
                        $description_class = 'inactive';
                    }
                }

                // Assemble the columns
                $col_date = Widget::TableData($date);
                $col_date->setAttribute('data-title', __('Date'));
                $col_time = Widget::TableData($time);
                $col_time->setAttribute('data-title', __('Time'));
                $col_desc = Widget::TableData($description, $description_class);
                $col_desc->appendChild(Widget::Input("items[{$activity['id']}]", null, 'checkbox'));
                $col_desc->setAttribute('data-title', __('Activity'));

                // Insert the row
                $tbody[] = Widget::TableRow(array($col_desc, $col_date, $col_time), $row_class, 'activity-' . $activity['id']);
            }
        }

        // Assemble the table
        $table = Widget::Table(
            Widget::TableHead($thead), null,
            Widget::TableBody($tbody), 'selectable', null,
            array('role' => 'directory', 'aria-labelledby' => 'symphony-subheading', 'data-interactive' => 'data-interactive')
        );
        $this->Form->appendChild($table);

        // Append table actions, if developer
        if (Tracker::Author()->isDeveloper()) {
            $options = array(
                array(null, false, __('With Selected...')),
                array('delete', false, __('Delete'))
            );

            $tableActions = new XMLElement('div');
            $tableActions->setAttribute('class', 'actions');
            $tableActions->appendChild(Widget::Apply($options));
            $this->Form->appendChild($tableActions);
        }

        // Append pagination
        $filter_sql = Tracker::buildFilterSQL($filters);
        $per_page = Symphony::Configuration()->get('pagination_maximum_rows', 'symphony');
        $q = Symphony::Database()
            ->select(['count(id)' => 'count'])
            ->from('tbl_tracker_activity');
        foreach ($filter_sql as $key => $value) {
            $q->where([$key => $value]);
        }
        $total_entries = $q
            ->execute()
            ->variable('count');

        $remaining_entries = max(0, $total_entries - ($start + $per_page));
        $total_pages = max(1, ceil($total_entries * (1 / $per_page)));
        $remaining_pages = max(0, $total_pages - $current_page);

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
        // Only developers can make actions
        if (isset($_POST) && Tracker::Author()->isDeveloper()) {
            $checked = @array_keys($_POST['items']);

            if (@array_key_exists('clear-all', $_POST['action'])) {
                // $sql = 'TRUNCATE `tbl_tracker_activity`;';
                // Symphony::Database()->query($sql);
                Symphony::Database()
                    ->truncate('tbl_tracker_activity')
                    ->execute()
                    ->success();

                redirect(Administration::instance()->getCurrentPageURL());
            } elseif (is_array($checked) && !empty($checked)) {

                switch ($_POST['with-selected']) {

                    case 'delete':
                        Symphony::Database()
                            ->delete('tbl_tracker_activity')
                            ->where(['id' => ['in' => $checked]])
                            ->execute()
                            ->success();

                        redirect(Administration::instance()->getCurrentPageURL());
                        break;
                }
            }
        }
    }

}
