<?php
namespace HM\Anonymizer;

use GFAPI;
use WP_CLI;
use WP_CLI_Command;
use WP_User_Query;

class Command extends WP_CLI_Command {

	/**
	 * Anonymize Users.
	 *
	 * Anonymize user data and user meta data.
	 * wp anonymizer anonymize_users
	 *
	 * [--exclude=<exclude>]
	 * : Exclude users by ID.
	 *
	 * [--alliteration]
	 * : Alliterate user names.
	 *
	 * @subcommand anonymize-users
	 */
	public function anonymize_users( $args, $assoc_args ) : void {
		$offset = 0;
		$batch_size = 100;

		$exclude = isset( $assoc_args['exclude'] ) ? array_map( 'absint', explode( ',', $assoc_args['exclude'] ) ) : [];
		$use_alliteration = ! empty( $assoc_args['alliteration'] );

		do {
			WP_CLI::line( "Updating a batch of $batch_size users." );

			$query_args = [
				'fields' => 'ID',
				'number' => $batch_size,
				'offset' => $offset,
				'orderby' => 'ID',
				'exclude' => $exclude,
				'blog_id' => 0,
			];

			$query = new WP_User_Query( $query_args );

			$users = $query->get_results();

			foreach ( $users as $user_id ) {
				if ( anonymize_user( $user_id, $use_alliteration ) ) {
					WP_CLI::success( "Updated user: $user_id." );
				} else {
					WP_CLI::error( "Failed to update user: $user_id.", false );
				}
			}

			$offset += $batch_size;
		} while ( count( $users ) > 0 );

		WP_CLI::success( "Complete." );
	}

	/**
	 * Delete gravity forms entries.
	 *
	 * Delete all entries across all forms without outputting any data to the screen.
	 * GF has some CLI commands but they're quite limited and output too much data to the screen.
	 *
	 * ## Examples
	 *
	 *     Usage on multisite: wp site list --field=url | xargs -n1 -I % wp --url=% anonymizer delete_gravity_forms_entries
	 *
	 * @subcommand delete-gravity-forms-entries
	 * @return void
	 */
	public function delete_gravity_forms_entries() : void {
		if ( ! class_exists( 'GFAPI' ) ) {
			WP_CLI::error( 'Gravity forms API class not found. Is the plugin active?' );
			return;
		}

		$offset = 0;
		$batch_size = 100;

		do {
			WP_CLI::line( "Deleting a batch of $batch_size entries." );

			$entries = GFAPI::get_entries( 0, [], null, [
				'page_size' => $batch_size,
				'offset' => $offset,
			] );

			foreach ( $entries as $entry ) {
				GFAPI::delete_entry( $entry['id'] );
			}


			$offset += $batch_size;
		} while ( count( $entries ) > 0 );
	}
}
