<?php
/**
* The MySQL search plugin uses the "Search API" plugin to enable basic MySQL fulltext searching.
* @author Justin Shreve <jshreve4@kent.edu>
* @version 1.0.8
*/

/*
Plugin Name: MySQL Search Plugin
Plugin URI: http://wordpress.org/
Description: MySQL Search functionality for WordPress
Author: Justin Shreve
Version: 1.0.8
*/

/**
* MySQL Search
* Takes information from the search api and uses it to query the search index table.
* @version 1.0.8
*/
class mysql_search
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
			'advanced' => 1,
			'filters' => 1,
			'pagination' => 1,
			'sort' => 1,
			'index' => 1,
		);
		
		/** 
		* MySQL Search (Constructor)
		* This function passes along the options array to the search api
		*/
		function mysql_search() {
			$this->parent->options =& $this->options;
		}

		/** 
		* Search
		* This function pulls everything from the API and database together. It loads the pagination function, the filter code, calls the results function and outputs the
		* results.
		* @return string Final search output
		*/
		function search()
		{
			// Query the database and format results
			$total = $this->find_results();
			
			if( $this->flags['page'] > 1 )
				$start = intval ( ( ( $this->flags['page'] - 1 ) * get_option( 'posts_per_page' ) ) + 1 );
			else
				$start = 1;
				
			// Return the results
			if( $total > 0 )
			{
				// FILTER: search_results_start Starts the results output
				$result_html = apply_filters( 'search_results_start', "<ol class=\"searchresults\" start=\"{$start}\">\n" );
				
				foreach( $this->results as $results ) {
					// Result is a post
					if( $results->type == "post" ) {
						// FILTER: search_post_result Lets you change a single row for result output
						$result_html .= apply_filters( 'search_post_result', "<li><strong class='result_type'>" . __( 'Post ' ) . "</strong>: <a href=\"" . get_permalink( $results->object ) . "\" class='result_title'>" . $results->title . "</a>\n<p class=\"result_summary\">". $this->parent->trim_excerpt( $results->content ) ."</p></li>" );
					}
					// Result is a page
					elseif( $results->type == "page" ) {
						// FILTER: search_page_result Lets you change a single row for result output
						$result_html .= apply_filters( 'search_page_result', "<li><strong class='result_type'>" . __( 'Page ' ) . "</strong>: <a href=\"" . get_permalink ( $results->object ) . "\" class='result_title'>" . $results->title . "</a>\n<p class=\"result_summary\">". $this->parent->trim_excerpt( $results->content ) ."</p></li>" );
					}
					// Result is a comment
					else {
						// FILTER: search_comment_result Lets you change a single row for result output
						$result_html .= apply_filters( 'search_comment_result', "<li><strong class='result_type'>" . __( 'Comment ' ) . "</strong>: <a href=\"" . get_comment_link( $results->object ) . "\" class='result_title'>" . get_the_title( $results->parent ) ."</a>\n<p class=\"result_summary\">" . $this->parent->trim_excerpt( $results->content ) ."</p></li>" );
					}
				}
				
				// FILTER: search_results_start Ends the results output
				$result_html .= apply_filters( 'search_results_end', "</ol>\n" );
			}
			
			// No results error
			else {
				// FILTER: search_no_results Allows you to change the error message when no results are returned
				$result_html .= apply_filters( 'search_no_results', "<h2>" . __( ' There are no results for this seach.' ) . "</h2>" );
			}
		
			// Return the search output
			// FILTER: search_results Allows you to edit the results
			return apply_filters( 'search_results', $this->parent->result_search_box() . $result_html . $this->parent->pagination( $total ) );
		}
		
		/** 
		* Find Results
		* This function is what physically queries the database and does the search using the flags given to us from the search API
		* @global object WordPress Database Abstraction Layer
		* @return int The number of results
		*/
		function find_results()
		{
			global $wpdb;				
			
			// Start off our query and our counting of results query	
			$start = "SELECT *,MATCH( content,title ) AGAINST( '{$this->flags[string]}') as ranking FROM {$wpdb->prefix}search_index WHERE MATCH ( content,title ) AGAINST('{$this->flags[string]}' IN BOOLEAN MODE) AND protected = '0'";
			$cstart = "SELECT COUNT(*) as total FROM {$wpdb->prefix}search_index  WHERE MATCH ( content,title ) AGAINST('{$this->flags[string]}' IN BOOLEAN MODE) AND protected = '0'";
				
			// Add in any date flags
			if( !empty( $this->flags['startYear'] ) )
				$sql .= " AND YEAR(post_date) >= " . $this->flags['startYear'];
			if ( !empty( $this->flags['startMonth'] ) )
				$sql .= " AND MONTH(post_date) >= " . $this->flags['startMonth'];
			if ( !empty( $this->flags['startDay'] ) )
				$sql .= " AND DAYOFMONTH(post_date) >= " . $this->flags['startDay'];
		
			if( !empty( $this->flags['endYear'] ) )
				$sql .= " AND YEAR(post_date) <= " . $this->flags['endYear'];		
			if ( !empty( $this->flags['endMonth'] ) )
				$sql .= " AND MONTH(post_date) <= " . $this->flags['endMonth'];
			if ( !empty( $this->flags['endDay'] ) )
				$sql .= " AND DAYOFMONTH(post_date) <= " . $this->flags['endDay'];
		
			// add in the author flag
			if( !empty( $this->flags['author'] ) )
				$sql .= " AND author LIKE '%".$this->flags['author']."%'";
						
			// keep going and do the category flags
			if( is_array( $this->flags['categories'] ) )
			{
				$sql .= " AND (";
				foreach( $this->flags['categories'] as $category )
				{
					$sql .= " categories LIKE '%,{$category},%' OR ";
				}
				$sql = substr( $sql, 0, -3 ) . ")";
			}
			
			
			// keep going and do the category flags
			if( is_array( $this->flags['tags'] ) )
			{
				$sql .= " AND (";
				foreach( $this->flags['tags'] as $tag )
				{
					$sql .= " tags LIKE '%,{$tag},%' OR ";
				}
				$sql = substr( $sql, 0, -3 ) . ")";
			}
			
			
			
			// Figure out what type of data we are looking for
			if( is_array( $this->flags['types'] ) ) {
				if( in_array( 'posts', $this->flags['types'] ) && !in_array( 'pages', $this->flags['types'] ) && !in_array( 'comments', $this->flags['types'] ) )
					$sql .= " AND type = 'post'";
				elseif( in_array( 'pages', $this->flags['types'] ) && !in_array( 'posts', $this->flags['types'] ) && !in_array( 'comments', $this->flags['types'] ) )
					$sql .= " AND type = 'page'";
				elseif( in_array( 'comments', $this->flags['types'] ) && !in_array( 'posts', $this->flags['types'] ) && !in_array( 'pages', $this->flags['types'] ) )
					$sql .= " AND type = 'comment'";
				elseif( in_array( 'posts', $this->flags['types'] ) && in_array( 'pages', $this->flags['types'] ) && !in_array( 'comments', $this->flags['types'] ) )
					$sql .= " AND (type = 'post' OR type = 'page')";
				elseif( in_array( 'posts', $this->flags['types'] ) && !in_array( 'pages', $this->flags['types'] ) && in_array( 'comments', $this->flags['types'] ) )
					$sql .= " AND (type = 'post' OR type = 'comment')";
				elseif( !in_array( 'posts', $this->flags['types'] ) && in_array( 'pages', $this->flags['types'] ) && in_array( 'comments', $this->flags['types'] ) )
					$sql .= " AND (type = 'page' OR type = 'comment')";
			}
			
			// How many results do we have total?
			// FILTER: search_count_find_results Allows you do manage the sql query
			$count = $wpdb->get_results( apply_filters( "search_count_find_results", $cstart . $sql ) );
			
			// how we are ordering the data
			if( $this->flags['sort'] == "alpha" )
				$sql .= " ORDER BY title ".$this->flags['sorttype'];
			elseif( $this->flags['sort'] == "date" )
				$sql .= " ORDER BY post_date ".$this->flags['sorttype'];
				
			// Add in the pagination data for the LIMIT part of the query
			$sql .= " LIMIT " . ( $this->flags['page'] - 1 ) * get_option( 'posts_per_page' ) . ",".get_option('posts_per_page').";";

			
			// store the results in an array
			// FILTER: search_find_results Allows you do manage the sql query
			$this->results = $wpdb->get_results( apply_filters( "search_find_results", $start . $sql ) );
			
			// return the total number of results
			return $count[0]->total;
		}
}
	
if( function_exists( "do_search_load" ) ) {
	echo '<div id="message" class="updated fade"><p>';
	_e('You may only have one search plugin using the search api enabled at a time. Please disable the active search plugin first.');
	echo '</p>';
	die;
}
else {	
	/**
	* Search Load
	* This function is ran by a filter in the search API.
	* @return object The search plugin object (the class in this file)
	*/
	function do_search_load() {
		return new mysql_search();
	}
}

register_activation_hook( __FILE__, 'mysql_activate_self' );

/**
* Activate Self
* This function refreshes the search index when the plugin is activated
*/		
function mysql_activate_self() {
	include_once( "search.php");
	$index = new search_index();
	$index->all();
	delete_option( 'searchapi_custom_options' );
	delete_option( 'searchapi_help' );
	
	// tell the api that this plugin is activated
	update_option( 'searchapi_plugin', "search/mysql.php" );
}


// Tell the above function to run in the search api
add_filter('search_load', 'do_search_load' );
?>