<?php

namespace HM\Anonymizer;

use WP_CLI;

function bootstrap() {
	if ( class_exists( 'WP_CLI' ) ) {
		require_once __DIR__ . '/command.php';
		WP_CLI::add_command( 'anonymizer', __NAMESPACE__ . '\\Command' );
	}
}

/**
 * Read word data from file.
 *
 * @param string $data_type Data type. Must match a data file name. e.g. nouns or adjectives.
 * @return array Data.
 */
function get_word_data( string $data_type ) : array {
	return file( dirname( __DIR__ ) . '/data/' . $data_type . '.txt' , FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

/**
 * Generate anonymous user data.
 *
 * @param bool alliterate Should the fist and last names be alliterative e.g. Hazy Hippopotamus.
 * @return array User data can be passed to wp_insert_user.
 */
function generate_user_data( bool $alliterate = false ) : array {
	$adjectives = get_word_data( 'adjectives' );
	$nouns = get_word_data( 'nouns' );
	$last_name = $nouns[ array_rand( $nouns ) ] ?? '';

	// If alliterative, filter last names to only those that start with the first letter of the first name.
	// Cool, but significantly reduces number of variation and will cause more conflicts on large dataset.
	if ( $alliterate ) {
		$first_letter = substr( $last_name, 0, 1 );
		$adjectives = array_values( array_filter( $adjectives, function( $word ) use ( $first_letter ) {
			return ( strtolower( substr( $word, 0, 1 ) ) == strtolower( $first_letter ));
		} ) );
	}

	$first_name = $adjectives[ array_rand( $adjectives ) ];
	$user_meta = [];

	foreach ( array_keys( wp_get_user_contact_methods() ) as $contact_method ) {
		$user_meta[ $contact_method ] = '';
	}

	// Use unique identifier in login and emails to avoid conflicts.
	$login = uniqid( strtolower( sprintf( '%s%s', $first_name, $last_name ) ) );

	return apply_filters( 'hm_anoymizer.user_data', [
		'user_login' => $login,
		'user_pass' => wp_generate_password(),
		'user_nicename' => strtolower( sprintf( '%s-%s', $first_name, $last_name ) ),
		'user_email' => sanitize_email( sprintf( '%s@example.com', $login ) ),
		'user_url' => sprintf( 'http://example.com/%s', sanitize_title( $first_name . '-' . $last_name ) ),
		'display_name' => sprintf( '%s %s', ucfirst( $first_name ), ucfirst( $last_name ) ),
		'first_name' => ucfirst( $first_name ),
		'last_name' => ucfirst( $last_name ),
		'nickname' => sprintf( '%s %s', $first_name, $last_name ),
		'description' => '',
		'meta_input' => $user_meta,
	] );
}

/**
 * Anonymize a user.
 *
 * @param int $user_id User ID.
 * @param bool alliterate Should the fist and last names be alliterative e.g. Hazy Hippopotamus.
 * @return void
 */
function anonymize_user( int $user_id, bool $alliterate = false ) : bool {
	$user_data = generate_user_data( $alliterate );

	// Override to force user_login to be updated.
	$filter_user_data = function( $data_to_insert ) use ( $user_data ) {
		$data_to_insert['user_login'] = $user_data['user_login'];
		return $data_to_insert;
	};

	add_filter( 'wp_pre_insert_user_data', $filter_user_data );

	$updated = wp_insert_user( array_merge( $user_data, [ 'ID' => $user_id ] ) );

	remove_filter( 'wp_pre_insert_user_data', $filter_user_data );

	return ! is_wp_error( $updated );
}
