=== Search API ===
Contributors: jshreve, andy
Tags: search,gsoc,api,mysql,google,search-api
Tested up to: 2.8.1
Stable tag: 1.0.8
Requires at least: 2.8
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6969492

Search API is a set of functions that allows developers to easily implement new search functionality for WordPress. A set of plugins to enhance the search functionality is also included.

== Description ==

Note: Developer documentation, bug reporting information, and other miscellaneous notes are included under "Other Notes".

Currently the search engine included in WordPress is very limited as to what types or categories of data it can locate and how a blog user can employ the system to locate them (search mode). For example, you cannot search blog posts with a simple strategy such as “find A string in category C”. You can’t perform a search such as “search all categories, but not category B”. It also doesn’t support advanced boolean search query formats (grouping, OR, NOT, AND).

For these reasons, the WordPress search system needed an overhaul and enhancement. Members of a blog need to be able to find what they need quickly and efficiently. The search capabilities of WordPress should allow for a greater flexibility and power in search configuration and strategy so that blog users retrieve optimal relevant search results.

This plugin creates a basis for a new search engine to replace the simple input box search currently employed. The plugin creates an API to support advanced search capabilities such as boolean search, multiple content searches (posts, tags, pages, authors and any available metadata) and flags (finding posts with A string in category C) through additional plugins.

This archive contains several different default search components. Developers can also create their own or edit the existing ones and distribute under the GPL.

The default search components are: 

* a MySQL/Database plugin as the default database plugin. The plugin includes multiple content searching and some flag search options.
* A Google search module which uses Google as a backend for searching.

Once a search plugin is uploaded, the user can manage it from the admin control panel; the API includes a way to easily add a settings section for the ability to configure any settings that might be associated with the search plugin.

An “advanced search” page feature (depending on which search plugin is selected) is also provided which provides all the available search options such as search by category, posts by a certain author, etc.

== Changelog ==

= 1.0.8 =

* Fixed up a few reported problems / comments
* revert broken change

= 1.0.7 =

* The Search API now activates the MySQL plugin by default
* The Search API deactivates the currently activate search plugin when you deactivate the Search API
* Cleaned up code

= 1.0.6 = 

* Fixed category display so sub categories show under the parents
* Added 'custom taxonomy' search filters (which includes post tags and any custom taxonomys other plugins add)
* new sql field tags
* search index now tracks tags
* upgrade method path from >= 1.0.5 to 1.0.6
* screenshot for new taxonomy feature

= 1.0.5 =

* Uninstall Proccess & Code (Smoothly installs and uninstalls)
* added some missing translation strings
* combined some lines that could be done in fewer code

= 1.0.4 =

* Fixed a problem preventing the Google plugin from loading
* Fixed up FAQ
* Added a screenshots section to the documentation

= 1.0.3 =

* Added 'sort' as an option.
* Fixed a pagination bug (extra pgs= being added to the URL)
* Added FAQ to Documentation
* Documentation Edited & Corrected

= 1.0.2 =

* Documentation Update
* Added TO-DO section.
* Fixed Directory Structure
* Fixed License File

= 1.0.1 =

* Added Readme
* Added GPL text file

== Frequently Asked Questions ==

= I get the following error: Warning: include() [function.include]: Failed opening '' for inclusion (include_path='.:/usr/local/share/pear')... What's wrong? =

The Search API requires the theme you are using to have a page template. The plugin will **not** work without it. Most themese should include this template by default.

== Screenshots ==

1. A list of results for the search 'blog' using the MySQL plugin
2. Editable settings for the Google plugin
3. A list of results for the search 'test' using the Google plugin
4. The advanced search page for the MySQL plugin
5. Integration of custom taxonomy filters

== Installation ==
1. Upload this directory to your plugins directory. It will create a 'wp-content/plugins/search/' directory.
2. Go to the Plugins page and activate "Search API".
3. You should see a notice at the top of the plugin screen telling you that you need to enable a search plugin to work with the API. Choose which plugin you want to use (Google, MySQL, or another) and click "activate".
4. If you have chosen a plugin that needs additional configuration, you should receive a notice at the top. Click the displayed link to configure any settings.

== TO-DO ==

* Final Code Sweap

== Issue Tracker ==

WordPress Search API has an <a href="http://code.google.com/p/wpsearchapi/issues/list">issue tracker</a>. Please submit any bug reports or feature suggestions here.

== Developer Tutorial ==

<strong>Note: This tutorial is not complete. The data in this tutorial is complete but has not been proofread and still needs to be edited.</strong>

Introduction

This section will show developers how to create a new search plugin that uses the Search API. The tutorial assumes that we are building the the base for the Sphinx search plugin.

Before starting you should become familar with writing WordPress plugins by reading the <a href="http://codex.wordpress.org/Writing_a_Plugin">Writing a Plugin</a> WordPress Codex entry. A search API plugin is a WordPress plugin that is loaded after the Search API plugin is loaded.

Tutorial

First grab the <a href="http://wpsearchapi.googlecode.com/files/phpdoc.zip">PHP Documentation (PHPDocumentor Documentation)</a> for this project to learn some more about the classes, functions, and variables within the project. The documents in this archive will provide you with some background on the source code before we start working with it.

Next  download a dummy plugin that contains the basic plugin elements. This tutorial will take a look at what each line (minus extra phpdoc comments) does and will discuss how to make a few changes to improve the functionality of the plugin.

<a href="http://wpsearchapi.googlecode.com/files/dummy.zip">Download the dummy file.</a>

The first lines in the file are a standard comment block containing some information for WordPress to read. The data here are used for displaying the name of the plugin in both the admin control panel and in the wordpress.org plugin directory if you choose to upload it there.


`<?php
/*
Plugin Name: NAME
Plugin URI: PLUGIN URL
Description: DESCRIPTION
Author: AUTHOR NAME
Version: VERSION
*/`


The <a href="http://codex.wordpress.org/Writing_a_Plugin#File_Headers">WordPress Codex</a> contains additional information on how to format the file header.

The next lines define the class our methods will be wrapped in. The offical search plugins follow the standard convention of SOMENAME_search where SOMENAME is mysql, google, or sphinx. In this case, we would replace dummy with sphinx or another unique and descriptive name.


`class sphinx_search
{`


Next we define two required variables that hold basically share data between the Search API and the search plugin.

`var $results = array();
var $flags = array();`


The first is an array of results. If we are pulling data from a database it is usually in the form of a WP-DB result set. Note: The plugin you create does not need to actually use this variable (the Google plugin simply echos out the results in the form of an iframe). 

The second is an array of flags (query operators) such as start date for searching, keywords, categories to search in, and so on. The Search API populates this array for you so you only need to define it.

The following flags can be used later on in the search plugin:


`$this->flags['string']		A simple escaped value of the query string 's'
$this->flags['terms']		An array of each term in the above string (separated by spaces)
$this->flags['startYear']	The year to begin searching for documentes (Say you only want to search 2008-2009, 2008 would be the value here)
$this->flags['startMonth']	The month to begin searching for documents.
$this->flags['startDay']	The day to begin searching for documents.
... as well as endYear, endMonth and endDay.
$this->flags['author']		The author name (admin, justin, etc) and not the ID
$this->flags['categories']	An array of categories to search in		
$this->flags['types']		An array of the types of content to search (i.e., posts, pages, or comments or an empty array/value for all)		
$this->flags['sort']		How to sort the content (relevance, date, or alpha)		
$this->flags['sorttype']	Sort order (DESC or ASC)
$this->flags['page']		The page of results we are on for LIMIT queries, etc.`
$this->flags['tags']		The taxonomy tags to search for


Next we define a set of options and features our plugin will use. The Search API allows you to disable options and features you do not need. For example, we disable the search indexing feature on the Google plugin because it is a waste of processing power if Google is already indexing. 


`var $options = array(
			'advanced' => 0,
			'filters' => 0,
			'sort' => 0,
			'pagination' => 0,
			'index' => 0,
		);`
		

In order by the listing in code:

The first line enables an advanced search page which allows you to have a dedicated page for searching with extended options (like a category picker). We will want this for the Sphinx plugin or most plugins.

The next line, enables or disables filters, which are simply options for filtering by content type (posts, pages, comments) on the search results page under the search box. We will enable this for most plugins.

The next line, enables or disables sorting on the search results page. We enable this for most plugins.

This line sets pagination, which is useful for large blogs and working with database systems. The sphinx plugin and most database plugins will use this so set the value to 1

This last line sets indexing. which allows the plugin we are writing to use a standard table of data to search instead of searching multiple tables to look for content. Setting this to 1 will create an up-to-date index of posts, comments, and pages that are in the system which can be searched with a simple query. The sphinx plugin and most database plugins will use the index table so set the value to 1

The new options array for our Sphinx plugin should look like this (with everything enabled):


`var $options = array(
			'advanced' => 1,
			'filters' => 1,
			'sort' => 1,
			'pagination' => 1,
			'index' => 1,
		);`
		

After setting the options, we actually need to pass the options array to the Search API. This is done as follows, using the sphinx_search class constructor:


`function sphinx_search() {
	$this->parent->options =& $this->options;
}`


The next two functions


`function search()
{
	global $wpdb;				
}
		
function find_results()
{
	global $wpdb;
}`
		

contain all the power and functionality of the plugin. The search function is called by the Search API to put together the results and then display them in HTML.

The Sphinx plugin (or another database plugin) will work very much like the MySQL plugin in that we load results in the database using a function called find_results. The Google plugin, on the other hand does not do this, and just leaves the function blank. Both functions should be included.

Note: If you are using the find_results function it should do two things:


`$this->results = RESULT_FINDING_METHOD;

// return the total number of results
return TOTAL_NUMBER_OF_RESULTS`



Set the results of the search in the $this->results array and then return the total number of results. The total is needed for things such as pagination.

<em>Note: The other code that would go inside these functions is outside of the scope of this article (because it would be discussing more specific items such as loading documents with Sphinx). However the code can be viewed and used in a new plugin under the GPL license.</em>

Finally, unless you have additional methods or functions after find_results(), you need to make sure that you exit out of the class with an ending bracket.


`}`



Next we have some error checking code to see if another search plugin has already been activated, and if not, the plugin will load itsself.


`if( function_exists( "do_search_load" ) ) {
	echo '<div id="message" class="updated fade"><p>';
	_e('You may only have one search plugin using the search api enabled at a time. Please disable the active search plugin first.');
	echo '</p>';
	die;
}
else {	
	function do_search_load() {
		return new sphinx_search();
	}
}`



The only thing that you need to worry about here is changing the name of the class in the return new line. Make sure it matches the name of the class we just created.

Next we have some code for when the plugin is activated/installed. The most common functionality is resetting options and refreshing the search index:


`register_activation_hook( __FILE__, 'dummy_activate_self' );

function dummy_activate_self() {
//	include_once( "search.php");
//	$index = new search_index();
//	$index->all();
//	delete_option( 'search_custom_options' );
//	delete_option( 'search_help' );

	update_option( 'searchapi_plugin', "search/dummy.php" );
}`



Make sure to replace dummy with the name of your plugin (sphinx, mysql, etc) as it needs to be unique for the plugin.

The line 'update_option( 'searchapi_plugin', "search/dummy.php" );' is required for the activate function and should lead to where the plugin is stored assuming we are in the top level plugins directory (ie: for mysql we would use search/mysql.php)

In addition to these running methods the activate_self function can also load help documentation or build an options screen. The below code is from the Google plugin:


`global $search_plugin;
	
	delete_option( 'search_custom_options' );
	delete_option( 'search_help' );
	
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
				'id' => 'googleid',
				'value' => "",
				'title' => "Google Custom Search ID",
				'desc' => "",
				'required' => 1,
			),
			
			array(
				'id' => "charset",
				'value' => "UTF-8",
				'title' => "Google Custom Search Charset",
				'desc' => "",
				'required' => 1,
			)
	), $help );`



Finally, we set all the code in motion by hooking into WordPress using a filter.


`add_filter('search_load', 'do_search_load' );
?>`


Optionally you can also have a bit of uninstall code within the plugin to delete settings:


`if ( function_exists('register_uninstall_hook') )
	register_uninstall_hook(__FILE__, 'PLUGIN_NAME_uninstall');

function PLUGIN_NAME_uninstall() {
	global $wpdb;
	
	delete_option( 'searchapi_setting );
	// etc
}`