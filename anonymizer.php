<?php
/*
Plugin Name: Anonymizer
Description: This plug-in anonymizes user and commentor names, e-mails and urls.
Version: 1.0.0
Author: Ioannis C. Yessios.com
Author URI: http://itg.yale.edu

/*  Copyright 2008 Ioannis Yessios (email : Ioannis.Yessios <at> yale.ed)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_filter( 'the_author_email', 'itg_anonymizer_email' );
add_filter( 'author_email', 'itg_anonymizer_email' );
add_filter( 'get_comment_email', 'itg_anonymizer_email' );
add_filter( 'get_comment_author', 'itg_anonymizer_name' );
add_filter( 'author_link', 'itg_anonymize_author_url' );
add_filter( 'author_feed_link', 'itg_anonymize_author_url' );
add_filter( 'get_comment_author_url', 'itg_anonymize_url' );
add_filter( 'pre_get_posts', 'itg_deanonymize_authorlink' );
add_filter( 'wp_title', 'itg_anonymize_htmltitle' );


/**
 * Function to anonymize a user e-mail address
 *
 * Simple returns 'email witheld' in place of actual e-mail address
 */
function itg_anonymizer_email( $content ) {
	return 'email withheld';
}

/**
 * Function to anonymize a user's diplay name
 *
 * Simple returns 'email witheld' in place of actual e-mail address
 */
function itg_anonymizer_name( $content ) {
	return itg_generateName( $content );
}

/**
 * Generates a persistent random name from an input string.
 *
 * Takes an incoming string and returns a randomly selected name from
 * an external text file and appends a two digit number.
 */
function itg_generateName($in) {
	$thenames = file( dirname( __FILE__ ).'/names.txt' );
	$num = abs( crc32( get_bloginfo('name', 'display' ) . $in ) );
	srand( $num );
	$dispnum = substr( $num, 0, 2 );
	return rtrim( $thenames[array_rand( $thenames )] )."_$dispnum";
}

/**
 * Generates a URL to an author's posts using their anonimized name.
 *
 * Takes the normal link to an author's Posts and rewrites them to use
 * the anonimized name
 *
 * @todo currently assums apache's mod_rewrite URLS. Needs to be generalized
 */
function itg_anonymize_author_url($url,$nice,$id) {
	$str = substr( $url, 0, -1 );
	$pos = strrpos( $str, '/' );
	$urlstart = substr( $str, 0, $pos+1 );
	$urlend = substr( $str, $pos+1 );
	$urlend = itg_generateName( $urlend );
	return $urlstart.$urlend.'/';
} 

/**
 * Rewrites the HTML page's title to avoid displaying a user's name
 */
function itg_anonymize_htmltitle( $in ) {
	$auth = get_query_var('author_name');
	if ( ($auth == $in || $in = ' &raquo; '.$auth) && $in) {
		$in = str_replace(' &raquo; ', '',$in);
		$in = ' &raquo; '.itg_generateName($in);
	}
	return $in;
}

/**
 * Takes incoming query variables and redirects author values to point at the
 * the author's real name and not the anonymized name.
 */
function itg_deanonymize_authorlink( $in ) {
	global $wpdb;

 	$users = get_users_of_blog();
	$author_ids = array();
	foreach ( (array) $users as $user ) {
		$author_ids[] = $user->user_id;
	}
	if ( count($author_ids) > 0  ) {
		$author_ids=implode(',', $author_ids );
		$authors = $wpdb->get_results( "SELECT ID, user_nicename from $wpdb->users WHERE ID IN($author_ids) " . ($exclude_admin ? "AND user_login <> 'admin' " : '') . "ORDER BY display_name" );
	} else
		$authors = array();

	foreach ( (array) $authors as $author ) {
		$author = get_userdata( $author->ID );
		$fakeID = itg_generateName($author->user_login);

		if ( isset($in->query_vars['author_name']) && $in->query_vars['author_name'] != '' ) {
			if ( $fakeID == $in->query_vars['author_name'] )
				$in->query_vars['author_name'] = $author->user_login;
			if ( $fakeID == $in->query['author_name'] )
				$in->query['author_name'] = $author->user_login;
		}
	}
	
	return $in;
}

/**
 * Prevents a personal URL from being displayed
 */
function itg_anonymize_url( $in ) {
	return false;
}

/**
 * Replaces standard get_userdata function with one that returns an anonymized
 * display name when appropriate.
 */
function get_userdata( $user_id ) {
	global $wpdb;
	global $is_profile_page;

	$user_id = absint( $user_id );
	if ( $user_id == 0 )
		return false;

	$user = wp_cache_get( $user_id, 'users' );

	if ( $user ) {
		promote_if_site_admin($user);
		if ( !$is_profile_page )
			$user->display_name = itg_generateName($user->user_login);
		else {
			if ( !$tmpuser = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->users WHERE ID = %d LIMIT 1", $user_id)) ) {
				$user->display_name = $user->user_login;
				return $user;
			}
			_fill_user($tmpuser);
			return $tmpuser;
		}
	}

	if ( !$user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->users WHERE ID = %d LIMIT 1", $user_id)) )
		return false;
	
	$user->display_name = itg_generateName( $user->user_login );
	_fill_user($user);

	return $user;
}
?>
