<?php
/*
Plugin Name: Fitness Stats
Plugin URI: http://natelaclaire.com/wp-fitness-stats/
Description: Keep track of fitness stats.
Version: 0.001
Author: Nate LaClaire <nate@natelaclaire.com>
Author URI: http://natelaclaire.com/
*/

// TODO: create plug-in stylesheet

define('FITNESS_STATS_VERSION', '0.001');
$fitness_stats_db_version = '0.001';
define('FITNESS_STATS_PLUGIN_FILE', plugin_basename(__FILE__));
define('FITNESS_STATS_PATH', dirname(__FILE__));

/*
 * Create or upgrade the Fitness Stats database table
 */
function fitness_stats_install () {
	global $wpdb;
	global $fitness_stats_db_version;
	
	$table_name = $wpdb->prefix . "fitness_stats";
	
	// check to see if the database table exists at all and, if not, create it
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
	
		$sql = "CREATE TABLE " . $table_name . " (
				  id mediumint(9) NOT NULL AUTO_INCREMENT,
				  dt date NOT NULL,
				  waist decimal(5,2) NOT NULL,
				  weight decimal(5,2) NOT NULL,
				  notes text NOT NULL,
				  PRIMARY KEY  id (id)
				);";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		add_option("fitness_stats_db_version", $fitness_stats_db_version);
	
	}
	
	// check the current database table version and upgrade if necessary
	$installed_ver = get_option( "fitness_stats_db_version" );
	
	if( $installed_ver != $fitness_stats_db_version ) {
	
		$sql = "CREATE TABLE " . $table_name . " (
				  id mediumint(9) NOT NULL AUTO_INCREMENT,
				  dt date NOT NULL,
				  waist decimal(5,2) NOT NULL,
				  weight decimal(5,2) NOT NULL,
				  notes text NOT NULL,
				  PRIMARY KEY  id (id)
				);";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		update_option( "fitness_stats_db_version", $fitness_stats_db_version );
	}
} // end function fitness_stats_install ()
register_activation_hook(__FILE__,'fitness_stats_install');

// If the user is an administrator, add the menu item
if ( is_admin() ){ // admin actions
	add_action('admin_menu', 'fitness_stats_menu');
}

/*
 * Create the menu item for the administrative area
 */
function fitness_stats_menu() {
	add_submenu_page('edit.php', 'Fitness Stats', 'Fitness Stats', 'administrator', 'fitness_stats_options', 'fitness_stats_options');
}

/*
 * Administrative area for Fitness Stats
 */
function fitness_stats_options() {
	global $wpdb;
	$height = get_option('fitness_stats_height'); // the user's height is stored as an option
  
	// first, present the form for adding an entry to the table 
	echo '<div class="wrap">';
	echo '<div id="icon-edit" class="icon32"><br /></div><h2>Fitness Stats</h2>';
	echo '<form method="post" action="admin-post.php"><h3>Add/Update Stats</h3>';
	echo '<input type="hidden" name="action" value="add_fitness_stats" />';
	echo '<label for="fitness_waist">Waist (inches):</label> <input type="text" name="fitness_waist" id="fitness_waist" style="width:50px; margin-right:20px;" />';
	echo '<label for="fitness_weight">Weight (pounds):</label> <input type="text" name="fitness_weight" id="fitness_weight" style="width:60px; margin-right:20px;" />';
	echo '<label for="fitness_notes">Notes:</label> <input type="text" name="fitness_notes" id="fitness_notes" style="width:150px; margin-right:20px;" />';
	echo '<input type="submit" class="button-primary" value="Add" />';
	echo '</form>';

	// grab the 25 most recent entries
	// TODO: add pagination 
	$results =& $wpdb->get_results("SELECT *,UNIX_TIMESTAMP(dt) AS ts FROM `".$wpdb->prefix."fitness_stats` ORDER BY dt DESC LIMIT 0,25");

	// display the entries, each with a checkbox
	echo '<form method="post" action="admin-post.php">';
	echo '<input type="hidden" value="delete_fitness_stats" name="action" />';
	echo '<table class="widefat post fixed" cellspacing="0">';
	echo '<thead><tr><th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th><th scope="col" class="manage-column">Date</th><th scope="col" class="manage-column">Waist</th><th scope="col" class="manage-column">Weight</th><th scope="col" class="manage-column">Notes</th><th scope="col" class="manage-column">BMI</th></tr></thead>';
	echo '<tfoot><tr><th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th><th scope="col" class="manage-column">Date</th><th scope="col" class="manage-column">Waist</th><th scope="col" class="manage-column">Weight</th><th scope="col" class="manage-column">Notes</th><th scope="col" class="manage-column">BMI</th></tr></tfoot>';
	echo "<tbody>";
	$flip = true; // used to display alternating backgrounds for rows
	foreach ($results as &$result) {
		echo "<tr".($flip?' class="alternate"':'').">";
		echo '<th scope="row" class="check-column"><input type="checkbox" name="fitness_delete_id[]" value="'.$result->id.'" /></th>';
		echo "<td>".date('n/j/Y', $result->ts)."</td>";
		echo "<td>".$result->waist."</td>";
		echo "<td>".$result->weight."</td>";
		echo "<td>".$result->notes."</td>";
		echo "<td>".number_format((($result->weight / ($height * $height)) * 703), 2)."</td>";
		echo "</tr>";
		
		$flip = !$flip;
	}
	echo '</tbody>';
	echo '</table>';
	echo '<input type="submit" class="button" value="Delete Checked" />';
	echo '</form>';
	
	echo '<hr />';
	
	// present the form for updating the user's height
	echo '<form method="post" action="options.php"><h3>Update Settings</h3>';
	wp_nonce_field('update-options');
	echo '<label for="fitness_stats_height">Height (inches):</label> <input type="text" name="fitness_stats_height" id="fitness_stats_height" value="'.$height.'" /><br />';
	echo '<input type="hidden" name="action" value="update" />';
	echo '<input type="hidden" name="page_options" value="fitness_stats_height" />';
	echo '<p class="submit">';
	echo '<input type="submit" class="button" value="Save Changes" />';
	echo '</p>';
	echo '</form>';
	echo '</div>';
} // end function fitness_stats_options()

/*
 * Called when the user submits the form for adding an entry to the table
 */
function add_fitness_stats() {
	global $wpdb;
	
	// prepare and execute the query
	$insert = $wpdb->prepare("INSERT INTO ".$wpdb->prefix."fitness_stats SET dt=NOW(), waist=%s, weight=%s, notes=%s", $_POST['fitness_waist'], $_POST['fitness_weight'], $_POST['fitness_notes']);
	$wpdb->query($insert);
	
	// redirect the user to the administrative page
	header("Location: edit.php?page=fitness_stats_options");
} // end function add_fitness_stats()

/*
 * Called when the user checks an entry and clicks "Delete Checked"
 */
function delete_fitness_stats() {
	global $wpdb;
	
	$sql = '';
	
	// loop through the checked entries to produce the SQL query
	foreach($_POST['fitness_delete_id'] as $delete_id) {
		if (strlen($sql)) {
			$sql .= ' OR ';
		}
		
		$sql .= 'id='.((int)$delete_id);
	}
	
	// execute the query
	$wpdb->query("DELETE FROM ".$wpdb->prefix."fitness_stats WHERE ".$sql);
	
	// redirect the user to the administrative page
	header("Location: edit.php?page=fitness_stats_options");
} // end function delete_fitness_stats()

add_action('admin_post_add_fitness_stats', 'add_fitness_stats');
add_action('admin_post_delete_fitness_stats', 'delete_fitness_stats');

/*
 * Widget (for sidebar/footer/etc) that displays a BMI chart
 */
function fitness_stats_tracking_widget($args) {
	global $wpdb;
	$height = get_option('fitness_stats_height');
	
	// convert the $args array into standalone variables
	extract($args);
	
	// output the code that should appear before the widget (from $args)
	echo $before_widget;
	
	// output the widget title  (before and after come from $args)
	echo $before_title. 'Fitness Stats Chart'. $after_title;
	
	// select the weekly average weight
	$results =& $wpdb->get_results("SELECT FORMAT(AVG(weight),1) AS avg_weight,DATE_FORMAT(dt,'%Y-%U') AS week FROM `".$wpdb->prefix."fitness_stats` GROUP BY week");
	
	$weights = ''; // list of averages in a string, passed to chart API
	$max_weight = 0; // starting maximum value, passed to chart API (must be smaller than all possible values)
	$min_weight = 700; // starting minimum value, passed to chart API (must be larger than all possible values)
	
	// loop through the weekly averages
	foreach ($results as &$result) {
		$weights .= $result->avg_weight.","; // add current average to list
		
		// if the current weight is less than the previous minimum, update the minimum
		if ($result->avg_weight < $min_weight) {
			$min_weight = $result->avg_weight;
		}
		
		// if the current weight is greater than the previous maximum, update the maximum
		if ($result->avg_weight > $max_weight) {
			$max_weight = $result->avg_weight;
		}
	}
	
	// remove the trailing comma
	$weights = substr($weights, 0, strlen($weights)-1);
	
	// output the image tag that displays the chart
	echo "<img src=\"http://chart.apis.google.com/chart?";
	echo "cht=lc&chs=300x100&chxt=y&chxr=0,$min_weight,$max_weight&chds=$min_weight,$max_weight&chd=t:$weights";
	echo "\" />";
	
	// output the code that should appear after the widget (from $args)
	echo $after_widget;

} // function fitness_stats_tracking_widget($args)
register_sidebar_widget('Fitness Stats Chart', 'fitness_stats_tracking_widget');

/*
 * Latest Fitness Check-In widget
 */
function fitness_stats_widget($args) {
	global $wpdb;
	$height = get_option('fitness_stats_height');
	
	// convert the $args array into standalone variables
	extract($args);
	
	// output the code that should appear before the widget (from $args)
	echo $before_widget;
	
	// output the widget title  (before and after come from $args)
	echo $before_title. 'Latest Fitness Check-In'. $after_title;
	
	// fetch the most recent row from table
	$results =& $wpdb->get_results("SELECT *,UNIX_TIMESTAMP(dt) AS ts FROM `".$wpdb->prefix."fitness_stats` ORDER BY dt DESC LIMIT 0,1");

	echo "<div class=\"fitness_stats_checkin\">";
	echo "<div class=\"fitness_stats_date\">".date('F j, Y', $results[0]->ts)."</div>";
	echo "<strong>Weight:</strong> ".$results[0]->weight." lb.<br />";
	echo "<strong>Waist:</strong> ".$results[0]->waist." in.<br />";
	echo "<strong>BMI:</strong> ".number_format((($results[0]->weight / ($height * $height)) * 703), 0);
	echo "</div>";
	
	// output the code that should appear after the widget (from $args)
	echo $after_widget;

} // end function fitness_stats_widget($args)
register_sidebar_widget('Latest Fitness Check-In', 'fitness_stats_widget');
