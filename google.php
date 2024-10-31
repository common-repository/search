<?php
/**
 * The Google search plugin uses the "Search API" plugin to enable a hosted google results search.
 * @author Justin Shreve <jshreve4@kent.edu>
 * @version 1.0.8
 */
 
/*
Plugin Name: Google Search Plugin
Plugin URI: http://wordpress.org/
Description: Google Custom Search functionality for WordPress. <a href="options-general.php?page=search/search.php">Edit Settings</a>
Author: Justin Shreve
Version: 1.0.8
*/

/**
* Google Search
* Takes information from the search api and uses it to call google and return results for a search
* @version 1.0.8
*/
class google_search
{
		/**
		* Results
		* Contains an array of MySQL results from a search
		* @var array a list of results from a search
		*/
		var $results = array();
		
		/**
		* Flags
		* An array of search parameters
		* @var array search parameters
		*/
		var $flags = array();
		
		/**
		* Options
		* Options specific to each search plugin. You can enable/disable advanced search, search page filters, pagination and search index support.
		* @var array options to be used by the search api
		*/
		var $options = array(
			'advanced' => 0,
			'filters' => 0,
			'pagination' => 0,
			'index' => 0,
			'sort' => 0,
		);
		
		/** 
		* Google Search (Constructor)
		* This function passes along the options array to the search api
		*/
		function google_search() {
			// if an advanced user just enters 's' as a query... redirect it
			if( empty ( $_GET['q'] ) && !empty( $_GET['s'] ) ) {
				$_GET['cx'] =  esc_attr__( get_option( 'searchapi_googleid' ) );
  				$_GET['q'] = $_GET['s'];
				$_GET['cof'] =  "FORID:10";
				header( "Location: index.php?q=" . $_GET['q'] . "&s=" . $_GET['s'] . "&cx=" . $_GET['cx'] . "&cof=" . $_GET['cof'] );
			}
			else
				$_GET['s'] = $_GET['q'];
			$this->parent->options =& $this->options;
			add_filter('get_search_form', array( &$this, 'search_form' ) );
		}
		
		/**
		* Search Form
		* This function returns the search form to the browser
		*/ 
		function search_form() {
			return '<form action="" id="cse-search-box">
			  <div>
				<input type="hidden" name="cx" value="' . esc_attr__( get_option( 'searchapi_googleid' ) ) . '" />
				<input type="hidden" name="cof" value="FORID:10" />
				<input type="hidden" name="ie" value="' . esc_attr__( get_option ( 'searchapi_charset' ) ) . '" />
				<input type="text" name="q" value="'. esc_attr__( $this->flags['string'] ) .'" />
				<input type="hidden" name="s" value="--" />
				<input type="submit" name="sa" value="Search" />
			  </div>
			</form>';
		}

		/** 
		* Search
		* This function pulls everything from the API and google together to return the results
		* @return string Final search output
		*/
		function search() {
			return apply_filters( 'search_results',
			'<form action="" id="cse-search-box" style="text-align: left;">
			  <div>
				<input type="hidden" name="cx" value="' . esc_attr__( get_option( 'searchapi_googleid' ) ) . '" />
				<input type="hidden" name="cof" value="FORID:10" />
				<input type="hidden" name="ie" value="' . esc_attr__( get_option ( 'searchapi_charset' ) ) . '" />
				<input type="text" name="q" value="'. esc_attr__( $this->flags['string'] ) .'" />
				<input type="hidden" name="s" value="--" />
				<input type="submit" name="sa" value="Search" />
			  </div>
			</form>
			
			<script type="text/javascript" src="http://www.google.com/jsapi"></script>
			<script type="text/javascript">google.load("elements", "1", {packages: "transliteration"});</script>
			<script type="text/javascript" src="http://www.google.com/coop/cse/t13n?form=cse-search-box&t13n_langs=en"></script>
			
			<script type="text/javascript" src="http://www.google.com/coop/cse/brand?form=cse-search-box&lang=en"></script>
			
			<style type="text/css" media="all">
				#srchResult iframe { 
					width: inherit;
				}
			</style>
			
			<div id="srchResult"><div id="cse-search-results"></div></div>
			<script type="text/javascript">
				var googleSearchIframeName = "cse-search-results";
				var googleSearchFormName = "cse-search-box";
				var googleSearchFrameWidth = 600;
				var googleSearchDomain = "www.google.com";
				var googleSearchPath = "/cse";
			</script>
			<script type="text/javascript" src="http://www.google.com/afsonline/show_afs_search.js"></script>' );
		}
		
		/** 
		* Find Results
		* This function is not used by the Google Search Plugin
		*/
		function find_results() {
			// this function does nothing for this search plugin
		}
}

/**
* Search Load
* This function is ran by a filter in the search API.
* @return object The search plugin object (the class in this file)
*/
if( function_exists( "do_search_load" ) ) {
	echo '<div id="message" class="updated fade"><p>';
	_e('You may only have one search plugin using the search api enabled at a time. Please disable the active search plugin first.');
	echo '</p>';
	die;
}
else {
	function do_search_load() {
		return new google_search();
	}
}


register_activation_hook( __FILE__, 'google_activate_self' );

/**
* Activate Self
* This function loads the settings into the admin control panel (if neccessary)
*/		
function google_activate_self() {
	global $search_plugin;
	
	delete_option( 'searchapi_custom_options' );
	delete_option( 'searchapi_help' );
	
	// This plugin can have a help system for settings
	$help = "<strong>" . __( "Before you can use the Google Search Plugin you must have a Google Custom Search Engine setup with Google." ) . "</strong><br />\n";
	$help .= __( "Follow these steps to sign up for a search engine and/or get the correct settings to enter below:" ) . "<br /><br />\n";
	$help .= "<ol>\n";
		$help .= "<li><a href='http://www.google.com/coop/cse/' target='_blank'>" . __( "Visit the Google Custom Search Site" ) . "</a></li>\n";
		$help .= "<li>" . __( "Either click 'Create a Custom Search Engine' if you need to create one for your site or 'manage your existing search engines' if you have one. You can skip to step 5 if you already have a search engine setup for your site." ) . "</li>\n";
		$help .= "<li>" . __( "Enter a search engine name and description, choose a lanaguage and follow the directions on the first page. Under 'Sites to search:' make sure to enter the full url to your blog. (For example: http://myblog.tld/blog/* to index the entire blog). Click continue." ) . "</li>\n";
		$help .= "<li>" . __( "On the next screen read the information and click the finish button." ) . "</li>\n";
		$help .= "<li>" . __( "Select the search engine you want to configure WordPress for by clicking 'control panel'." ) . "</li>\n";
		$help .= "<li>" . __( "You can edit the settings for the search engine here later but for now click 'Get code' on the left hand side." ) . "</li>\n";
		$help .= "<li>" . __( "Check the middle option 'Host results on my website using an iframe' and some code will be generated." ) . "</li>\n";
		$help .= "<li>" . __( "In the first text box you should see the following line of code: &lt;input type=\"hidden\" name=\"cx\" value=\"SEARCH ID HERE\" /&gt; copy the value that should be in place of 'SEARCH ID HERE'." ) . "</li>\n";
		$help .= "<li>" . __( "Paste the value below and click 'Save Changes'" ) . "</li>\n";
	$hep .= "</ol>\n";
		
	$search_plugin->build_options( array(
			array( 
				'id' => 'searchapi_googleid',
				'value' => "",
				'title' => "Google Custom Search ID",
				'desc' => "",
				'required' => 1,
			),
			
			array(
				'id' => "searchapi_charset",
				'value' => "UTF-8",
				'title' => "Google Custom Search Charset",
				'desc' => "",
				'required' => 1,
			)
	), $help );
	
	// tell the api that this plugin is activated
	update_option( 'searchapi_plugin', "search/google.php" );
}

// Tell the above function to run in the search api
add_filter('search_load', 'do_search_load' );
?>