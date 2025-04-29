
## Usage 📖

### Initialize

Initialize one notice per plugin.
```php
// Setup notice.
$notice = \pluginPrefix\Reviews\Notice::get(
	'my-plugin', // Plugin slug on wp.org (eg: hello-dolly).
	'My Plugin', // Plugin name (eg: Hello Dolly).
	array(
		'days'          => 7, // default: 7 days.
		'message'       => 'My custom message asking for review', // If you want to use different review notice message.
		'action_labels' => array(
			'review'  => 'Please review me', // Change review link label.
			'later'   => 'I will review later', // Change review extension link.
			'dismiss' => 'Nope', // No review label :(.
		),
	)
);

// Render notice.
$notice->render();
```
### Options
You can customize the notice behaviour using options. All these options are optional.

| Option          | Type   | Description                                                                                                                                                                                                                                                                                        |
| --------------- | ------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `days`          | int    | No. of days after the review is shown.                                                                                                                                                                                                                                                             |
| `screens`       | array  | WordPress admin page screen IDs to show notice. If you leave this empty the notice will be added to add admin pages. Strongly recommended to use this option to limit the review notices only within your plugin's admin pages, especially if you are showing notice using `admin_notices` action. |
| `cap`           | string | WordPres user capability to show notice to. Notice will be visible only to user with this capability. Also only users with this capability can dismiss/extend notice.                                                                                                                              |
| `classes`       | array  | Additional class names for notice.                                                                                                                                                                                                                                                                 |
| `domain`        | string | Text domain string for internationalization.                                                                                                                                                                                                                                                       |
| `message`       | string | Notice main message (to override default message).                                                                                                                                                                                                                                                 |
| `action_labels` | array  | To use different labels for action links. Available items are: `review`, `later`, `dismiss`. Remember to escape.                                                                                                                                                                                   |
| `prefix`        | string | To override plugin option and other key prefixes. By default it's plugin slug with dashes replaces with underscores.                                                                                                                                                                               |


### default usage: 
1. change namespace prefix to plugin prefix for unique namespace.
2. then:
   require plugin_dir_path(__FILE__) . 'includes/packages/plugin-review/notice.php';
function prefix_review()
{
	// delete_site_option('prefix_reviews_time'); // FOR testing purpose only. this helps to show message always
	$message = sprintf(__("Hello! Seems like you have been using %s for a while – that’s awesome! Could you please do us a BIG favor and give it a 5-star rating on WordPress? This would boost our motivation and help us spread the word.", 'PLUGIN-TEXT-DOMAIN'), "<b>" . get_plugin_data(__FILE__)['Name'] . "</b>");
	$actions = array(
		'review'  => __('Ok, you deserve it', 'PLUGIN-TEXT-DOMAIN'),
		'later'   => __('Nope, maybe later I', 'PLUGIN-TEXT-DOMAIN'),
		'dismiss' => __('already did', 'PLUGIN-TEXT-DOMAIN'),
	);
	$notice = \PREFIX\Reviews\Notice::get(
		'PLUGIN-TEXT-DOMAIN',
		get_plugin_data(__FILE__)['Name'],
		array(
			'days'          => 7,
			'message'       => $message,
			'action_labels' => $actions,
			'prefix' => "prefix"
		)
	);

	// Render notice.
	$notice->render();
}
add_action('admin_notices', 'prefix_review');

3. For development show this option always, remove stored data:
   delete_site_option('prefix_reviews_time'); 
