<?php
/**
 * WPML Multilingual Comments
 *
 * @package WPML_Multilingual_Comments
 * @author Hintay <hintay@me.com>
 *
 * @wordpress-plugin
 * Plugin Name: WPML Multilingual Comments
 * Plugin URI: https://kugeek.com
 * Description: This plugin merge comments from all translations of the posts and pages, so that they all are displayed on each other. Comments are internally still attached to the post or page they were made on.
 * Version: 1.0
 * Author: Hintay
 * Author URI: https://kugeek.com
 */

add_filter( 'plugins_loaded', 'remove_comments_clauses' );
function remove_comments_clauses() {
	if( !is_admin() ) {
		global $sitepress;
		remove_filter( 'comments_clauses', array( $sitepress, 'comments_clauses' ), 10 );

		// Merge comments for frontend pages
		add_filter( 'comments_clauses', 'wpml_merge_comments', 999, 2 );
		add_filter( 'get_comments_number', 'wpml_merge_comment_number', 999, 2 );

		// Flush cache when post comment
		add_filter( 'comment_post', 'wpml_flush_comment_count_cache' );
	}
}

function wpml_merge_comments( $pieces, $query ) {
	$post_ID = $query->query_vars['post_id'];
	$cache_value = wp_cache_get( 'merged_comment_where', $post_ID );

	if ( false === $cache_value ) {
		$post = get_post( $post_ID );
		$type = $post->post_type;

		// Get all active languages
		global $active_languages;
		if ( ! $active_languages ) {
			global $wpdb;
			$active_languages = $wpdb->get_results( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active=1" );
		}

		$cache_value = [];
		// Foreach active language get the post_id
		foreach ( $active_languages as $lang ) {
			$other_post = apply_filters( 'wpml_object_id', $post_ID, $type, false, $lang->code );
			if ( $other_post ) {
				$cache_value[] = "comment_post_ID = '" . $other_post . "'";
			}
		}

		wp_cache_set( 'merged_comment_where', $cache_value, $post_ID );
	}

	// Edit the query to include all 'post_id's'
	$pieces['where'] = str_replace( "AND comment_post_ID = " . $post_ID . "", '', $pieces['where'] );
	$pieces['where'] .= ' AND (' . implode( " OR ", $cache_value ) . ')';

	return $pieces;
}

function wpml_merge_comment_number( $count, $post_ID ) {
	$cache_value = wp_cache_get( 'merged_comment_number', $post_ID );

	if ( false === $cache_value ) {
		$cache_value = get_comments( array(
			'post_id' => $post_ID,
			'count' => true
		) );

		wp_cache_set( 'merged_comment_number', $cache_value, $post_ID );
	}
	return $cache_value;
}

function wpml_flush_comment_count_cache () {
	global $post;
	wp_cache_delete( 'merged_comment_number', $post->ID );
}
