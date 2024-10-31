<?php
/**
 * The search.php plugin contains two classes and some additonal code. The first class is the search api which can be used by other plugins for building WordPress search
 * engines. The second class is for building a search index when content is updated on WordPress. Finally, search.php also tells WordPress when to execute the other 
 * code in this file.
 * @author Justin Shreve <jshreve4@kent.edu>
 * @version 1.0.8
 */
 
/*
Plugin Name: Search API
Plugin URI: http://wordpress.org/
Description: A set of functions that allow developers to easily implement new search functionality for WordPress.
Author: Justin Shreve
Version: 1.0.8
*/

// BEGIN SEARCH API

/**
* Search API
* An API that is loaded when a search related action is taken place. The class contains various functions for outputing to WordPress and doing the necessary legwork for
* common search functions (pagination, trimming content for display, options and filtering, etc)
* @version 1.0.8
*/
class search_api
{
	
	/**
	* Plugin
	* This variable contains a reference to a search plugin
	* @var object reference to search plugin
	*/
	var $plugin = NULL;
	
	/**
	* Flags
	* An array of search parameters
	* @var array search parameters
	*/
	var $flags = array();
	
	/**
	* Custom Options
	* An array of options to edit in the admin control panel
	* Pulled from `unserialize( get_option( "searchapi_custom_options" ) );`
	*/
	var $custom_options = array();

	/** 
	* Search API (Constructor)
	* This function creates a new filter 'search_load' so that search plugins can call it and load their class object into "$this->plugin".
	*/
	function search_api() {
	
		// Check if the plugin is loaded before we try to load this class to it
		// and show a message if the plugin does not load
		if( $this->plugin == NULL ) {
			$this->plugin = apply_filters( 'search_load', array() );
			
			// Do we still need to load a plugin?
			if( $this->plugin == NULL ) {
				function searchplugin_warning() {
					echo "<div id='message' class='updated fade'><p><strong>" . __('The search system is almost ready.' ) . "</strong> " . __( 'You must enable a search plugin to work with the Search API plugin.' ) . "</p></div>";
				}
				
				add_action('admin_notices', 'searchplugin_warning');
			}
			
			
			$this->plugin->parent =& $this;
		}
		
		$this->custom_options = unserialize( get_option( "searchapi_custom_options" ) );
		
		/**
		* Config Warning
		* Shows a warning if you need to configure additonal settings
		*/
		function config_warning() {
			echo "<div id='message' class='updated fade'><p><strong>" . __('The search system is almost ready.' ) . "</strong> ".sprintf( __ ( '<a href="%1$s">You must fill out a few additional configuration fields before the search engine can be used.</a>'), "options-general.php?page=search/search.php" ) . "</p></div>";
		}
		
		// Should we show the above notice?
		if( is_array( $this->custom_options ) && ( count ( $this->custom_options ) > 0 ) ) {
			foreach( $this->custom_options as $setting ) {
				if( $setting['required'] == 1 ) {
					if( ! trim( get_option( $setting['id'] ) ) ) {
						add_action('admin_notices', 'config_warning');
						break;
					}
				}
			}
		}
	}
	
	/**
	* Initialize Search
	* This function overloads the template outputer so that we can replace the content with our search results. We do this so the legacy search template or older search stuff
	* doesn't load instead. This function calls execute_search which does the bulk of the work for this class.
	* @see search_api::execute_search()
	*/
	function init_search() {
		add_filter( 'template_redirect', array( &$this, 'execute_search' ) );
	}
	
	/**
	* Build Options
	* This function takes a list of custom options a plugin might have (a search api key, a charset string, etc) so that you can edit them from the control panel
	* @param array $options An array of options stored in arrays containing ids, values, titles and descriptions
	*/
	function build_options( $options, $help = '' ) {
		global $wpdb;
		
		foreach( $options as $option ) {
			add_option( esc_attr__ ( $option['id'] ) , esc_attr__ ( $option['value'] ) );
		}
		
		update_option( "searchapi_custom_options", serialize( $options ) );
		update_option( "searchapi_help", $wpdb->escape( $help ) );
	}
	
	/**
	* Options Admin Menu
	* This function creates a menu under "settings" for search settings if the search plugin has any
	*/
	function options_admin_menu() {
		if( is_array ( $this->custom_options ) ) {
			add_options_page (
				__("Search Settings"),
				__("Search Settings"),
				'manage_options',
				__FILE__,
				array( &$this, "options_admin_page" )
			);
		}
	}
	
	/**
	* Options Admin Page
	* This function takes creates a page for editing search settings
	*/
	function options_admin_page() {
		global $wpdb;
		echo "<div class='wrap'><h2>" . __( "Search Settings " ) . "</h2>";
		
		if( get_option( 'searchapi_help' ) )
			echo "<div id='message' class='update fade'>\n" . stripslashes( get_option( 'searchapi_help' ) ) . "</div>\n";
		
		// Update settings
		if( $_REQUEST['submit'] ) {
			$ok = false;
			
			foreach( $this->custom_options as $option ) {
				if( !empty( $_REQUEST[$option['id']] ) ) {
					update_option( $wpdb->escape( $option['id'] ), $wpdb->escape ( $_REQUEST[$option['id']] ) );
					$ok = true;
				}
			}
			
			if( $ok == true )
				echo "<div id='message' class='update fade'><p>" . __("Options Saved") . "</p></div>";
			else
				echo "<div id='message' class='error fade'><p>" . __("Failed to save options.") . "</p></div>";
		}
		
		// Display the form
		echo "<form method='post'>\n";
		
			foreach( $this->custom_options as $option ) {
				echo "<p><label for='{$option['id']}'> " . esc_html__( $option['title'] ) . ": \n";
				if( !empty( $option['desc'] ) )
					echo "<br /> " . esc_html__( $option['desc'] ) . "<br />";
				echo "<input type='text' name='{$option['id']}' value='" . esc_attr__( stripslashes( get_option( $option['id'] ) ) ) ."' /></label></p>";
			}
			
			echo "<input type='submit' name='submit' class='button-primary' value='Save Settings' /></form>";
		
		echo "</div>";
	}
	
	/**
	* Pagination
	* This function takes the total number of results from a set and calculates how many pages is needed to display X (WordPress's posts per page option) results per page.
	* @param int $total The total number of results from a query
	* @return string HTML output containing the pagination links
	*/
	function pagination( $total ) {
		// Only go through the pagination code if the search plugin calls for it	
		if( $this->plugin->options['pagination'] == 1 ) {
			
			// Load some required variables such as the total results, the total needed pages and variable place holders.
			if( $total > 0 )
				$pages = ceil( $total / get_option( 'posts_per_page' ) );

			if( empty ( $_GET['pg'] ) )
				$_GET['pg'] = 1;
							
			$pages = $pages ? $pages : 1;
			$links = "";
			
			// Grab the current URL (minus the query string) since search pages can have many query strings
			$current = "http" . ( empty( $_SERVER["HTTPS"] ) ? "": ( $_SERVER["HTTPS"]=='on' ) ? "s": "" )."://" . esc_attr__( $_SERVER["HTTP_HOST"] ) . esc_attr__( $_SERVER["REQUEST_URI"] );
			$current = preg_replace( "/&amp;pg=([0-9]+)/i", "", $current );
			
			// Create the previous, next and number links
			if( $_GET['pg'] > 1 )
				$previous_link = "<span class=\"searchpglink\"><a href=\"".$current."&amp;pg=".($_GET['pg'] - 1)."\">&lt;</a></span>&nbsp;";
			
			if( $_GET['pg'] < $pages )
				$next_link = "&nbsp;<span class=\"searchpglink\"><a href=\"".$current."&amp;pg=".($_GET['pg'] + 1)."\">&gt;</a></span>";
			
			if( $pages > 1 ) {
				for( $i = 0, $j = $pages - 1; $i <= $j; ++$i ) {
					$pagenum = $i+1;
					$page = ceil( $pagenum );
					
					if ( $pagenum < ( $_GET['pg'] - 4 ) ) {
						$i = $_GET['pg'] - 6;
						continue;
					}
				
					if ( $page == $_GET['pg'] )
						$links .= "&nbsp;<span class=\"searchpgcurrent\">{$page}</span>"; // this is the current page, no need for a link
					else {
						$links .= "&nbsp;<span class=\"searchpglink\"><a href=\"".$current."&amp;pg=".$page."\" title=\"$page\">$page</a></span>";
						if ( $pagenum > ( $_GET['pg'] + 4 ) )
							break;
					}
				}
			}		
		}
		
		// FILTER: search_pagination Allows you to edit the output of the pagination links
		return apply_filters('search_pagination', $previous_link . $links . $next_link);
	}
	
	/**
	* Advanced Search Wrapper
	* This function creates a "virtual" page which is used to display all the advanced search form HTML. The short code [advsearch] is replaced with output from
	* the advanced_search function here.
	* @see search_api::advanced_search()
	* @global class WordPress DB Abstraction Layer
	*/
	function advanced_search_wrapper() {
		global $wp_query;
		
		// set "the loop" to nothing
		$wp_query->posts = NULL;
		
		// Create a blank "result set" for the wordpress query object
		$object = new stdClass();
		$object->post_title = __( "Advanced Search" );
		$object->post_content = "[advsearch]";
			
		$wp_query->posts[0] = $object;	
		
		// replace the advsearch shortcode with the form, and then return the page template
		add_shortcode( 'advsearch', array( &$this, 'advanced_search' ) );
		
		$template = get_page_template();
		
		if ( $template )
			include $template;
		else
			include(TEMPLATEPATH . "/index.php");

		exit;
	}
	
	/**
	* Advanced Link
	* This function returns a link to the advanced search form. If additonal output is passed to it then that is also returned.
	* @param string $output Output to be returned, usually passed from get_search_form or another WordPress search
	* @return string HTML output containing an advanced search link
	*/
	function advanced_link( $output = '' ) {	
		// output usually comes from get_search-form
		if( !empty( $output ) )
			$html = $output;
			
		// return a link to the advanced search form
		$html .= "<small><a href='index.php?advancedsearch=1'>". __( "Advanced Search" ) . "</a></small>";

		// FILTER: search_advanced_link Allows you to edit the output of the advanced link and the search in the sidebar (using the filter get_search_template)
		return apply_filters('search_advanced_link', $html);
	}

	/**
	* Advanced Search
	* This function generates HTML for an avanced search form (author, categories, etc). This function is outputed using a WordPress short code.
	* @return string HTML output for an advanced search form
	*/
	function advanced_search() {
	
		// Content type picker
		$option_output = "<br /> " . __( "Search:" ) ." <input type='checkbox' name='types[]' value='posts' /> " . __( "Posts" ) ."  <input type='checkbox' name='types[]' value='pages' /> " . __( "Pages" ) ." <input type='checkbox' name='types[]' value='comments' /> " . __( "Comments " ) ."<br />";
		
		// author text box
		$author_output .= "<br />" . __( "Author:" ) ." <input type='text' value='' name=\"author\" />";
		
		// category and tag/taxonomy selector (multiple choice box)
		$tax_output = "";
		
		foreach( get_object_taxonomies('post') as $taxonomy ) {
			$data = get_taxonomy( $taxonomy );
			
			if( $data->name == "category" )
				$tax_output = "<br /> <br /> " . __( "Categories: " ) ."<br /><br />" . str_replace( "name='cat'", "name='cats[]' multiple='multiple' size='10'", wp_dropdown_categories( 'hierarchical=1&echo=0' ) );
				
			else {
				$terms = get_terms( $data->name );
				
				if( count ( $terms ) != 0 ) {
					$tax_output .= "<br /> <br /> " . $data->label . "<br /><br /><select name='tags[]' multiple='multiple' size='10'>";
					foreach( $terms as $term ) {
						$tax_output .= "<option value='".$term->term_id."'>" . $term->name . '</option>';
					}
					$tax_output .= "</select>";
				}
			}
		}
		
		// Month Picker, created once and used for start and end dates
		$month_picker = "<option value=''>" . __( 'Select Month' ) . "</option> 
					<option value='1'>" . __( 'January' ) . "</option>
					<option value='2'>" . __( 'February' ) . "</option>
					<option value='3'>" . __( 'March' ) . "</option>
					<option value='4'>" . __( 'April' ) . "</option>
					<option value='5'>" . __( 'May' ) . "</option>
					<option value='6'>" . __( 'June' ) . "</option>
					<option value='7'>" . __( 'July' ) . "</option>
					<option value='8'>" . __( 'August' ) . "</option>
					<option value='9'>" . __( 'September' ) . "</option>
					<option value='10'>" . __( 'October' ) . "</option>
					<option value='11'>" . __( 'November' ) . "</option>
					<option value='12'>" . __( 'December' ) . "</option>";
		
		// Start date dropdowns
		$date_output = "<br /><br />" . __( 'From' ) . "<br /> <select name='startMonth'>{$month_picker}</select>
						<select name='startYear'> <option value=\"\">" . __( 'Select Year' ) . "</option> 
					    " . str_replace( get_option( "siteurl" ) . "/?m=", "", wp_get_archives( 'type=yearly&format=option&show_post_count=0&echo=0') ) . "</select>
						<select name='startDay'> <option value=\"\">" . __( 'Select Day' ) . "</option>";						

		for ( $i = 1; $i <= 31; $i++ ) {
			$date_output .= "<option value='{$i}'>{$i}</option>";
		}

		$date_output .= "</select>";
		
		// End date dropdowns
		$date_output .= "<br />" . __( 'to' ) . "<br /> <select name='endMonth'>{$month_picker}</select>
						<select name='endYear'> <option value=\"\">" . __( 'Select Year' ) . "</option> 
					    " . str_replace( get_option( "siteurl" ) . "/?m=", "", wp_get_archives( 'type=yearly&format=option&show_post_count=0&echo=0' ) ) . "</select>
						<select name='endDay'> <option value=\"\">" . __( 'Select Day' ) . "</option>";						

		for ( $i = 1; $i <= 31; $i++ ) {
			$date_output .= "<option value='{$i}'>{$i}</option>";
		}

		$date_output .= "</select>";
		
		// Return the final output
		
		// FILTER: search_advanced Allows you to edit the output of the advanced search page
		return apply_filters( 'search_advanced', "<br /><form method='get' id='result_search_form' action='' style='text-align: left;'>
		<input type='text' value='{$this->flags['string']}' name='s' id='results' size='50' />
		{$option_output}
		{$author_output}
		{$tax_output}
		{$date_output}
		<p><input type='submit' id='searchsubmit' value='" . __( 'Search' ) . "' /></p>
		</form>" );
	}

	/**
	* Create Flags
	* Cleans up input from the query string and sticks it in an easier to digest format for search plugins
	* @return array An array of search parameters
	*/
	function create_flags() {
		global $wpdb;
		// Clean up and separate the terms
		$this->flags['string'] = $wpdb->escape( $_GET['s'] );
		preg_match_all( '/".*?("|$)|((?<=[\\s",+])|^)[^\\s",+]+/', $this->flags['string'], $matches );
		$this->flags['terms'] = array_map( create_function( '$a', 'return trim($a, "\\"\'\\n\\r ");' ), $matches[0] );
		
		// Clean up the start and end dates
		
		$this->flags['startYear'] = intval( $_GET['startYear'] );
		$this->flags['startMonth'] = intval( $_GET['startMonth'] );
		$this->flags['startDay'] = intval( $_GET['startDay'] );
		
		$this->flags['endYear'] = intval( $_GET['endYear'] );
		$this->flags['endMonth'] = intval( $_GET['endMonth'] );
		$this->flags['endDay'] = intval( $_GET['endDay'] );
		
		// Clean up the author flag
		$this->flags['author'] = $wpdb->escape( $_GET['authors'] );
		
		// Clean up categories
		if( is_array( $_GET['cats'] ) ) {
			foreach( $_GET['cats'] as $i )
				$this->flags['categories'][] = intval( $i );
		}
		
		// Clean up tags
		if( is_array( $_GET['tags'] ) ) {
			foreach( $_GET['tags'] as $i )
				$this->flags['tags'][] = intval( $i );
		}
		
		// Clean up the content types (all, posts, pages, comments)
		if( is_array( $_GET['types'] ) ) {
			foreach( $_GET['types'] as $i ) {
				$this->flags['types'][] = $wpdb->escape( $i );
			}
		}
		
		// Build our sort by/order by flag
		$this->flags['sort'] = "relevance";
		if( $_GET['sort'] == "date" )
			$this->flags['sort'] = "date";
		elseif( $_GET['sort'] == "alpha" )
			$this->flags['sort'] = "alpha";

			
		// Decide ascending or decesnding
		$this->flags['sorttype'] = "DESC";
		if( $_GET['sorttype'] == "ASC" )
			$this->flags['sorttype'] = "ASC";

		// decie which page we are on
		$this->flags['page'] = 1;			
		if( !empty( $_GET['pg'] ) )
			$this->flags['page'] = intval( $_GET['pg'] );

		// return a mass array of all the flags/query strings		
		// FILTER: search_flags Allows you to make any changes to the search flags array
		return apply_filters( 'search_flags', $this->flags );
	}
	
	
	/**
	* Result Search Box
	* This function generates HTML for options to be displayed on a search result page. This includes options for filtering by type, reordering results and an
	* advanced search link
	* @return string HTML output for the various form controls
	*/
	function result_search_box( ) {
		$output = "";

		// Return filters if the feature is enabled. flters are for filtering by content type (posts, pages, comments)
		if( $this->plugin->options['filters'] == 1 ) {
			$option_output = "<br />";
			
			if( is_array( $this->flags['types'] ) ) {
				if( in_array( 'posts', $this->flags['types'] ) )
					$checked['post'] = ' checked="checked"';
					
				if( in_array( 'pages', $this->flags['types'] ) )
					$checked['page'] = ' checked="checked"';
					
				if( in_array( 'comments', $this->flags['types'] ) )
					$checked['comment'] = ' checked="checked"';
			}
			
			// all three types are searched at once
			else {
				$checked['post'] = ' checked="checked"';
				$checked['page'] = ' checked="checked"';
				$checked['comment'] = ' checked="checked"';
			}
			
			// Find the current url for use within javascript (the switch sort and switch sort type dropdowns)	
			$current_url = "http" . ( empty( $_SERVER["HTTPS"] ) ? "": ( $_SERVER["HTTPS"]=='on' ) ? "s": "" )."://" . ( $_SERVER["HTTP_HOST"] ) . ( $_SERVER["REQUEST_URI"] );
		
		
			// Start building the options output
			$option_output .= __( 'Search Only:' ) . "<input type='checkbox' name='types[]' value='posts'{$checked['post']} /> " . __( 'Posts' ) . "
								 <input type='checkbox' name='types[]' value='pages'{$checked['page']}/> " . __( 'Pages ' ) . "
								 <input type='checkbox' name='types[]' value='comments'{$checked['comment']} /> " . __( 'Comments ') . "<br />";	
		}
		
		if( $this->plugin->options['sort'] == 1 ) {
			
			// Decide which sort flag is chosen
			if( $this->flags['sort'] == "date" )
				$checked['date'] = ' selected="selected"';
			elseif( $this->flags['sort'] == "alpha" )
				$checked['alpha'] = ' selected="selected"';
			elseif( $this->flags['sort'] == "relevance" )
				$checked['relevance'] = ' selected="selected"';
				
			// Decide which sort type flag is chosen
			if( $this->flags['sorttype'] == "ASC" )
				$checked['ASC'] = ' selected="selected"';
			elseif( $this->flags['sorttype'] == "DESC" )
				$checked['DESC'] = ' selected="selected"';
				
			// Start building the options output
			$option_output .= "<script type='text/javascript'>			 
									function switchSort(value) {
										document.location.href = '" . preg_replace( "/&sort=([A-Za-z]+)/i", "", $current_url ) . "'+'&sort='+value;
									}
									
									function switchSortType(value) {
										document.location.href = '" . preg_replace( "/&sorttype=([A-Za-z]+)/i", "", $current_url ) . "'+'&sorttype='+value;
									}
								</script>
								
								<br /><div style='text-align: right;'>" . __( 'Order results by:' ) . " 
								
								<select name='sort' onChange='switchSort(this.options[this.selectedIndex].value);'>\n
									<option value='relevance'{$checked['relevance']}>" . __( 'Relevance' ) . "</option>
									<option value='date'{$checked['date']}>" . __( 'Date' ) . "</option>
									<option value='alpha'{$checked['alpha']}>" . __( 'Alphabetical' ) . "</option>
								</select>
								
								<select name='sorttype' onChange='switchSortType(this.options[this.selectedIndex].value);'>\n
								<option value='ASC'{$checked['ASC']}>" . __( 'Ascending' ) . "</option>
								<option value='DESC'{$checked['DESC']}>" . __( 'Descending' ) . "</option>
								</select></div>";
								
		}
		
		if( $this->plugin->options['advanced'] == 1 )
			$advanced = $this->advanced_link();
		
		// Result Box Output
		$output = "<br /><form method='get' id='resultsearchform' action='' style='text-align: left;'>
		<input type='text' value='" . esc_attr( apply_filters( 'the_search_query', get_search_query() ) ) . "' name='s' id='results' />
		<input type='submit' id='searchsubmit' value='" . __( 'Search' ) . "' />
		{$advanced}
		{$option_output}
		</form>";
				
		// FILTER: search_result_box Allows you to make changes to the filter/order box above the search results
		return apply_filters( 'search_result_box', $output );
	}
	
	/**
	* Trim Excerpt
	* If a line of text is longer then $chars characters this functon shortens a line of text and returns it with $end at the end. If a line of text is shorter the whole
	* thing is returned.
	* @param string $content The text to shorten
	* @param int $chars The amount of characters to start shortening content at
	* @param string $end The characters to append to the end of the shortend text
	* @return string The shortend text with appended characters OR full already short text
	*/
	function trim_excerpt( $content, $chars = 150, $end = "..." ) {	
		$content = substr( strip_tags( trim( $content ) ), 0, $chars );
		$content = substr( $content, 0, strrpos( $content, ' ' ) ) . $end;
		apply_filters( 'the_excerpt', $content );
		return $content;
	}
	
	/**
	* Execute Search
	* This function creates a virtual page and then replaces a shortcode [search] with output from another search plugin (the plugin's search() function). This function also
	* outputs all final HTML to the browser for search results.
	* @global class WordPress's DB Abstraction Class
	* @global class WordPress query object (from wp-includes/query.php). used for overloading "the loop" to display in a page.
	*/
	function execute_search() {
		global $wpdb, $wp_query;
		
		// Default the loop to nothing since we are trying to overload the page template
		$wp_query->posts = NULL;
		
		// Try to load the search page which SHOULD contain the short code [search]
		$wp_query->posts = $wpdb->get_results( "SELECT * from {$wpdb->prefix}posts WHERE post_type = 'page' and post_title = 'search'" );
		
		// Create a virtual page if the search page does not exist
		if( empty( $wp_query->posts[0] ) ) {
			$object = new stdClass();
			$object->post_title = __( "Search" );
			$object->post_content = "[search]";
			$wp_query->posts[0] = $object;
		}
		
		// Parse the shortodes for the loop	
		add_shortcode( 'search', array( $this->plugin, 'search' ) );

		// Return the template
		$template = get_page_template();
		
		if ( $template )
			include $template;
		else
			include(TEMPLATEPATH . "/index.php");
			
		exit;
	}
	
}

global $search_plugin;

/**
* Load Search
* This function runs the init_search function
* @global object The class object for the above class
*/
function load_search( $query ) {
	global $search_plugin;
	if( $query->is_search )
		$search_plugin->init_search();
}

// Load the search api and call the create_flags function to be used later. This does nothing until WordPress is told to take the object
// ...

/**
* Load Search API
* Makes sure that we don't try to load a plugin before the plugin is loaded...
* @global object The class object for the above class
*/
function load_search_api( ) {
	global $search_plugin;
	$search_plugin = new search_api();
	
	// Some plugins have advanced search functionality/pages. We need to setup WordPress to overload the template output and create a virtual page if needed
	// AND append an advanced search link to the get_search_form function (general-template.php)
	if( $search_plugin->plugin->options['advanced'] == 1 && $_GET['advancedsearch'] == "1" )
		add_filter( 'template_redirect', array( &$search_plugin, 'advanced_search_wrapper' ) );
	if( $search_plugin->plugin->options['advanced'] == 1 )
		add_filter( 'get_search_form', array( &$search_plugin, 'advanced_link' ) );
	
	if( $search_plugin->plugin != NULL ) {
		$search_plugin->plugin->flags = $search_plugin->create_flags();
		add_filter( 'pre_get_posts', 'load_search' );
	}
}

add_action( 'plugins_loaded', 'load_search_api' );

add_action( 'admin_menu', array( &$search_plugin, 'options_admin_menu' ) );

// END SEARCH API 

// BEGIN SEARCH INDEX CLASS

/**
* Search Index
* This class contains a set of fucntions for "refreshing" (updating) the search index when a piece of content is changed in WordPress.
* The purpose is to only need to search one table instead of 3+ for different pieces of content, have a standard set of data and make it easier for
* search libraries like Sphinx to read.
* @verson 1.0.0
*/
class search_index {

	function search_index() {
		add_filter( 'delete_post', array( &$this, 'delete_post' ) );
		add_filter( 'delete_comment', array( &$this, 'delete_comment' ) );
		add_filter( 'save_post', array( &$this, 'save_post' ) );
		add_filter( 'comment_post', array( &$this, 'comment_post' ) );
		add_filter( 'edit_comment', array( &$this, 'edit_comment' ) );
		add_filter( 'wp_set_comment_status', array( &$this, 'edit_comment' ) );
	}
	/**
	* Delete Post
	* This function is ran when a post or a page is deleted from WordPress. The post or page is then also removed from being searched.
	* This function can be hooked into with the hook 'refreshed_search_index'
	* @param int $id The id of the post or page being deleted
	* @global object WordPress Database Abstraction Layer
	*/
	function delete_post( $id ) {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}search_index WHERE type = 'post' OR type = 'page' AND object = '" . $wpdb->escape( $id ) . "'" );
		do_action( 'refreshed_search_index' );
	}
	
	/**
	* Delete Comment
	* This function is ran when a comment is deleted from WordPress. The comment is then also removed from being searched.
	* This function can be hooked into with the hook 'refreshed_search_index'
	* @param int $id The id of the comment being deleted
	* @global object WordPress Database Abstraction Layer
	*/
	function delete_comment( $id ) {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}search_index WHERE type = 'comment' AND object = '" . intval( $id ) . "'" );
		do_action( 'refreshed_search_index' );
	}
	
	/**
	* Save Post
	* This function is ran when a page or post  is saved or created in WordPress. The data or changed data is then made avaiable for searching.
	* This function can be hooked into with the hook 'refreshed_search_index'
	* @param int $id The id of the page or post
	* @global object WordPress Database Abstraction Layer
	*/
	function save_post( $id ) {
		global $wpdb;
	
		$post = $wpdb->get_results ( "SELECT * from {$wpdb->prefix}posts WHERE ID = '" . intval( $id ) . "'" );
		
//		print_r($post);
		if( $post[0]->post_type != "revision" )
		{			
			$type = $post[0]->post_type;
		
			// Do we have an index for this (are we updating?) or are we creating
			$idx = $wpdb->get_results( "SELECT * from {$wpdb->prefix}search_index WHERE object = '" . intval( $post[0]->ID ) . "' AND ( type = 'post' OR type='page' ) " );

			// Build a string of categories and tags that this object belongs to
			
			$cats = ",";
			$tags = ",";
			
			$terms = $wpdb->get_results( "SELECT r.term_taxonomy_id,t.taxonomy,r.object_id from $wpdb->term_relationships r LEFT JOIN $wpdb->term_taxonomy t ON(t.term_taxonomy_id=r.term_taxonomy_id) WHERE r.object_id = '" . intval( $post[0]->ID ) . "'" );
		
			foreach( $terms as $term ) {
				if( $term->taxonomy == "category" )
					$cats .= intval( $term->term_taxonomy_id ) . ",";
				else
					$tags .= intval( $term->term_taxonomy_id ) . ",";
			}
		
			// Find which author created this object
			$authors = $wpdb->get_results( "SELECT display_name FROM $wpdb->users WHERE ID = '" . intval( $post[0]->post_author ) ."'" );
			$author = $authors[0]->display_name;
			
			// Can this item be searched yet?	
			if( $post[0]->post_status == "publish" && empty( $post[0]->post_password ) )
				$protected = "0";
			else
				$protected = "1";
	
			// update
			if( !empty( $idx[0]->id ) ) {
				$wpdb->query( "UPDATE {$wpdb->prefix}search_index SET title = '" . $wpdb->escape( $post[0]->post_title ) . "', content = '" . $wpdb->escape( $post[0]->post_content ) . "', post_date = '" . $wpdb->escape( $post[0]->post_date ) . "', parent = '', categories = '" . $wpdb->escape ( $cats ) . "', tags = '" . $wpdb->escape( $tags ) . "', author = '" . $wpdb->escape ( $author ) . "', type = '" . $wpdb->escape( $type ) . "', protected = '" . $wpdb->escape ( $protected ) . "' WHERE object = '" . intval( $post[0]->ID ) . "'" );
			}
			
			// create new
			else {
				$wpdb->query( "INSERT INTO {$wpdb->prefix}search_index (object, title, content, post_date, parent, categories, tags, author, type, protected)
				VALUES (
							  '" . intval( $id ) . "', '" . $wpdb->escape( $post[0]->post_title ) . "', '" . $wpdb->escape( $post[0]->post_content ) . "', '" . $wpdb->escape( $post[0]->post_date ) . "', '0', '" . $wpdb->escape( $cat ) . "', '" . $wpdb->escape( $tags ) . "', '" . $wpdb->escape( $author ) . "', '" . $wpdb->escape( $type ) . "', '" . $wpdb->escape( $protected ) . "'
				);" );
		
			}	
			do_action( 'refreshed_search_index' );
		}
	}

	/**
	* Comment Post
	* This function is ran when a comment is made on a post in WordPress. The comment is made avaiable for searching.
	* This function can be hooked into with the hook 'refreshed_search_index'
	* @param int $id The id of the of the comment
	* @global object WordPress Database Abstraction Layer
	*/
	function comment_post( $id ) {
		global $wpdb;
		
		// Load the comment
		$comment = $wpdb->get_results( "SELECT * from {$wpdb->prefix}comments WHERE comment_ID = '" . intval( $id ) . "'" );

		// Load the straight post data
		$post = $wpdb->get_results( "SELECT * from {$wpdb->prefix}posts WHERE ID = '" . intval( $comment[0]->comment_post_ID ) . "'" );
		
		// Load a string of the cats the comment is in
		$cat = ",";
		$cats = $wpdb->get_results( "SELECT term_taxonomy_id from $wpdb->term_relationships WHERE object_id = '" . intval( $comment[0]->comment_post_ID ) . "'" );
	
		foreach( $cats[0] as $catz ) {
			$cat .= $catz.","; 
		}
		
		// Can we search this comment?
		if( $comment[0]->comment_approved == 1 )
			$protected = 0;
		else
			$protected = 1;
			
		// create the index entry
		$wpdb->query( "INSERT INTO {$wpdb->prefix}search_index (object, title, content, post_date, parent, categories, author, type, protected)
				VALUES (
							  '" . intval( $comment[0]->comment_ID ) . "', '', '" . $wpdb->escape( $comment[0]->comment_content ) . "', '" . $wpdb->escape( $comment[0]->comment_date ) . "', '". intval( $post[0]->ID ) ."', '" . $wpdb->escape( $cat ) . "', '" . $wpdb->escape( $comment[0]->comment_author ) . "', 'comment', '" . $wpdb->escape( $protected ) . "'
				);" );	
				
		do_action( 'refreshed_search_index' );
	}


	/**
	* Edit Comment
	* This function is ran when a comment is changed. The updated comment is made avaiable for searching.
	* This function can be hooked into with the hook 'refreshed_search_index'
	* @param int $id The id of the of the comment
	* @param string $status The status of the comment (closed, etc)
	* @global object WordPress Database Abstraction Layer
	*/	
	function edit_comment( $id, $status = '' ) {
		global $wpdb;
		
		// Load the comment data
		$comment = $wpdb->get_results( "SELECT * from {$wpdb->prefix}comments WHERE comment_ID = '" . intval( $id ) . "'" );
		
		// Load the post data for this comment
		$post = $wpdb->get_results(  "SELECT * from {$wpdb->prefix}posts WHERE ID = '" . intval( $comment[0]->comment_post_ID ) . "'" );
	
		// Load a string of the categories the comment is in
		$cat = ",";
		$cats = $wpdb->get_results( "SELECT term_taxonomy_id from $wpdb->term_relationships WHERE object_id = '". intval( $post[0]->ID ) . "'" );

		if( is_array( $cats[0] ) )
		{
			foreach( $cats[0] as $catz ) {
				$cat .= $catz->term_taxonomy_id.","; 
			}
		}
			
		// see if we can search this comment
		if( $comment[0]->comment_approved )
			$protected = 0;
		else
			$protected = 1;
		
		// update the entry 
		$wpdb->query( "UPDATE {$wpdb->prefix}search_index SET title = '', content = '" . $wpdb->escape( $comment[0]->comment_content ) . "', post_date = '" . $wpdb->escape( $comment[0]->comment_date ) . "', parent = '" . intval( $post[0]->ID ) . "', categories = '" . $wpdb->escape( $cat ) . "', author = '" . $wpdb->escape( $comment[0]->comment_author ) . "', protected = '" . $wpdb->escape( $protected ) . "' WHERE object = '" . intval( $id ) . "' AND type = 'comment'" );
		
		do_action( 'refreshed_search_index' );		
	}

	/**
	* Refresh All
	* This function is database resource intensive. It clears out the entire search index and rebuilds it.
	* This function can be hooked into with the hook 'refreshed_search_index'
	* @global object WordPress Database Abstraction Layer
	*/
	function all() {
		global $wpdb;
		
		// delete the current index	
		$wpdb->query( "DELETE FROM {$wpdb->prefix}search_index" );
	
		// Load all the posts and pages stored within WordPress
		$posts = $wpdb->get_results( "SELECT * from {$wpdb->prefix}posts" );
			
		foreach( $posts as $post ) {
			// don't search revisions
			if( $post->post_type == "page" || $post->post_type == "post" ) {

				// Build a string of categories and tags that this object belongs to
				$cats = ",";
				$tags = ",";
	
				$terms = $wpdb->get_results( "SELECT r.term_taxonomy_id,t.taxonomy,r.object_id from $wpdb->term_relationships r LEFT JOIN $wpdb->term_taxonomy t ON(t.term_taxonomy_id=r.term_taxonomy_id) WHERE r.object_id = '" . intval( $post->ID ) . "'" );
			
				foreach( $terms as $term ) {
					if( $term->taxonomy == "category" )
						$cats .= intval( $term->term_taxonomy_id ) . ",";
					else
						$tags .= intval( $term->term_taxonomy_id ) . ",";
				}
				
				// find the author
				$authors = $wpdb->get_results( "SELECT display_name FROM $wpdb->users WHERE ID = '" . intval( $post->post_author ) . "'" );
				$author = $authors[0]->display_name;			
				
				// Can we search this item?
				if( $post->post_status == "publish" && empty( $post->post_password ) )
					$protected = "0";
				else
					$protected = "1";
		
				// Create the entry in the search index
				$wpdb->query( "INSERT INTO {$wpdb->prefix}search_index (object, title, content, post_date, parent, categories, tags, author, type, protected)
				VALUES (
							  '". intval( $post->ID ) . "', '" . $wpdb->escape( $post->post_title ) . "', '" . $wpdb->escape( $post->post_content ) . "', '" . $wpdb->escape( $post->post_date ) . "', '0', '" . $wpdb->escape( $cats ) . "', '" . $wpdb->escape( $tags ) . "', '" . $wpdb->escape( $author ) . "', '" . $wpdb->escape( $post->post_type ) . "', '" . $wpdb->escape( $protected ) . "'
				);" );
				
				do_action( 'refreshed_search_index' );
			}	
		}
			
		// Load up the comments for insertion now
		$comments = $wpdb->get_results( "SELECT * from {$wpdb->prefix}comments" );
		
		foreach($comments as $comment) {
			// load the post data
			$post = $wpdb->get_results( "SELECT * from {$wpdb->prefix}posts WHERE ID = '" . intval( $comment->comment_post_ID ) . "'" );
			
			// load a string of categories that the comment is in
			$cat = ",";
			$cats = $wpdb->get_results( "SELECT term_taxonomy_id from $wpdb->term_relationships WHERE object_id = '" . intval( $comment->comment_post_ID ) . "'" );
		
			foreach( $cats as $catz ) {
				$cat .= intval( $catz->term_taxonomy_id ) . ","; 
			}
				
			// is the comment searchable
			if( $comment->comment_approved )
				$protected = 0;
			else
				$protected = 1;
			
			// create the search index row
			$wpdb->query( "INSERT INTO {$wpdb->prefix}search_index (object, title, content, post_date, parent, categories, author, type, protected)
					VALUES (
								  '" . intval( $comment->comment_ID ) . "', '', '" . $wpdb->escape( $comment->comment_content ) . "', '" . $wpdb->escape( $comment->comment_date ) . "', '" . intval( $post[0]->ID ) . "', '" . $wpdb->escape( $cat ) . "', '" . $wpdb->escape( $comment->comment_author ) . "', 'comment', '" . $wpdb->escape( $protected ) . "'
					);" );	
		}
		
	}
}

// Load the search index and have WordPress call the functions if we have indexing enabled
if( $search_plugin->plugin->options['index'] == 1 )
	$index = new search_index();

// END SEARCH INDEX

// START INSTALLER CODE

/**
* Search API Installer
* This function creates the search index table and sets a version string. It's to allow for future upgrades and to verify a table already exists for any plugin's that
* need the index.
* @see http://codex.wordpress.org/Creating_Tables_with_Plugins#Adding_an_Upgrade_Function for upgrading
* @global object WordPress Database Abstraction Layer
* @global string The current version of the SearchAPI
*/
function searchapi_install () {
	global $wpdb;

	$db_version = "1.0.6";
	$table_name = $wpdb->prefix . "search_index";
	$installed_ver = get_option( "searchapi_db_version" );
	
	// Upgrade path from 1.0.0 to 1
	if( $installed_ver ==  "1.0" ) {
		$wpdb->query( "ALTER TABLE " . $table_name . " ADD tags TEXT NOT NULL;" );
		update_option( "searchapi_db_version", $db_version );		
		include_once( "search.php");
		$index = new search_index();
		$index->all();
	}
   
   // Prepare the create table statement
	if( $wpdb->get_var( "show tables like '{$table_name}'" ) != $table_name ) {
		
		$sql = "CREATE TABLE " . $table_name . " (
			`id` bigint(20) NOT NULL auto_increment,
			`object` bigint(20) NOT NULL,
			`title` text NOT NULL,
			`content` text NOT NULL,
			`post_date` datetime NOT NULL,
			`parent` bigint(20) NOT NULL,
			`categories` text NOT NULL,
			`tags` text NOT NULL,
			`author` text NOT NULL,
			`type` varchar(50) NOT NULL,
			`protected` smallint(6) NOT NULL,
			PRIMARY KEY  (`id`),
			FULLTEXT KEY `title` (`title`),
			FULLTEXT KEY `content` (`content`),
			FULLTEXT KEY `title_and_content` (`title`,`content`)
		) ENGINE MYISAM;";

		// execute the statement	
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		  
		// save the version we just installed (for upgrades)
		add_option( "searchapi_db_version", $db_version );
	}
	
	add_option( "searchapi_custom_options", "" );
	update_option( "searchapi_custom_options", "" );
	
	add_option( "searchapi_plugin", "search/mysql.php" );
	update_option( "searchapi_plugin", "search/mysql.php" );
	
	add_option( "searchapi_help", "" );
	update_option( "searchapi_help", "" );
	
	include_once( "search.php");
	$index = new search_index();
	$index->all();
	
//	$current = get_settings( 'active_plugins' );
//	$current[] = "search/mysql.php";
	//update_option( 'active_plugins', $current );
}
	
register_activation_hook( __FILE__,'searchapi_install' );
// END INSTALLER CODE

/**
* Deactivation
* Disables the search plugin currently using the Search API
*/
function searchapi_deactivate() {
	$current = array();
	
	update_option( 'searchapi_custom_options', '' );
	update_option( 'searchapi_help', '' );
	foreach( get_settings( 'active_plugins' ) as $plugin ) {
		if( $plugin != get_option( "searchapi_plugin") && $plugin != "search/search.php" )
			$current[] = $plugin;
	}
	
	update_option( 'active_plugins', $current );
	
	header('Location: plugins.php?deactivate=true');
	die;
}

register_deactivation_hook( __FILE__, 'searchapi_deactivate' );

/**
* Uninstall Code (Remove all of the data for plugins)
*/
if ( function_exists( 'register_uninstall_hook' ) )
	register_uninstall_hook( __FILE__, 'total_search_uninstall' );
 
/**
* Total Search Uninstall
* This function removes ALL traces of the search plugin
*/
function total_search_uninstall() {
	global $wpdb;
	
	// Remove all the settings from all search plugins
	$ops = $wpdb->get_results( "SELECT option_name FROM {$wpdb->prefix}options WHERE option_name LIKE 'searchapi_%'" );
		
	foreach( $ops as $op ) {
		delete_option( $op->option_name );
	}
	
	// Remove the search index
	$wpdb->query( "DROP TABLE {$wpdb->prefix}search_index" );
}
?>