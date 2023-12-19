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
	 * @subcommand anonymize-users
	 */
	public function anonymize_users() {
		$offset = 0;
		$batch_size = 25;

		do {
			WP_CLI::line( "Updating a batch of $batch_size users." );
			// Query users in batches
			$query = new WP_User_Query( [
				'fields' => 'ID',
				'number' => $batch_size,
				'offset' => $offset,
				'orderby' => 'ID',
				'exclude' => [ 1 ]
			] );

			$users = $query->get_results();

			foreach ( $users as $user_id ) {
				anonymize_user( $user_id );
				WP_CLI::success( "Updated user: $user_id." );
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
		}

		$offset = 0;
		$batch_size = 25;

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
