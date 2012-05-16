# Tracker

## Description

A Symphony extension that tracks user and system activity.

### Features

- Tracks creations, updates, and deletes of Entries, Pages, Events, Data Sources, Utilities, and Sections.
- Tracks frontend submissions via events.
- Tracks when Preferences are changed.
- Tracks when Extensions are enabled, disabled, or uninstalled.
- Tracks login activity and password reset activity.
- Provides a back-end interface for viewing and managing tracked activities.
- Provides a data source for accessing Entry-related tracker activity from the front end.

## Installation

1. Place the `tracker` folder in your Symphony `extensions` directory.
2. Go to _System > Extensions_, select "Tracker", choose "Enable" from the with-selected menu, then click Apply.

## Usage

### Managing/Viewing Activities

Activities can be viewed at _System > Tracker Activity_. It is possible from this page to delete individual activities or to clear all activity in the system.

The view can be filtered with GET params using the normal Symphony back-end filtering syntax: 

	?filter=column:values

**column** can be any of: `item_type`, `item_id`, `action_type`, or `user_id`. **values** can be a comma-delimited list of values to filter on. The following values are acceptable:

- For **item_type**: a section id, `pages`, `events`, `datasources`, `utilities`, `sections`, `authors`, `preferences`, `extensions`, `login`, or `password`
- For **action_type**: `updated`, `created`, `deleted`, `enabled`, `disabled`, `uninstalled`
- For **user_id**: an author id, or `0` for unauthenticated/unknown users.

Example:

	/symphony/extension/tracker/?filter=item_type:pages,events
	
These query strings can also be used to filter the entries in Tracker's Dashboard panel:

	item_type:1,2

### Excluding Activities

Tracker allows you to exclude certain types of activity from being tracked. Go to _System > Preferences_ and, in the "Tracker" section, choose any system elements, sections, or users you'd like to exclude from tracking.

### Using Activity Info on the Front End

The included data source, "Tracker Activity" can be attached to Pages and returns all Entry creation/update activity. 

#### Customized Data Sources

Configuration options are available in the data source file, but rather than edit the included version, it's recommended to copy the code into a custom DS of your own:

- Copy the `data.tracker_activity.php` file to your `workspace/data-sources` and rename it to data.**your_datasource**.php
- Update the class name (line 6) to datasource**your_datasource**
- Update the `about()` method (line 28) to reflect your data source's name and other info
- Update the sorting and filtering options (lines 10-21) to suit your needs

### To Do

- Enable other extensions to log activities
- Beef up the filtering; allow an all-sections wildcard

### Known Issues

- Doesn't track page template changes (there's no delegate for that)