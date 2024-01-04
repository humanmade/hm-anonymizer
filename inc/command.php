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
	 * Delete pending users.
	 *
	 * Delete all rows from signups table

	 * @subcommand delete-pending-users
	 */
	public function delete_pending_users( $args, $assoc_args ) : void {
		global $wpdb;

		$query_result = $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}signups" );

		if ( $query_result ) {
			WP_CLI::success( 'Deleted all pending users.' );
		} else {
			WP_CLI::error( 'Error deleting signups.' );
		}

		$query_result = $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}registration_log" );

		if ( $query_result ) {
			WP_CLI::success( 'Deleted all data from registration log.' );
		} else {
			WP_CLI::error( 'Error deleting registration log data.' );
		}
	}

	/**
	 * Delete gravity forms entries.
	 *
	 * Delete all entries across all forms on a site without outputting any data to the screen.
	 * GF has some CLI commands but they're quite limited and output too much data to the screen.
	 * For sites with a very large number of entries you might want to use the command empty-gravity-forms-entry-tables-network-wide
	 *
	 * ## Examples
	 *
	 *     Usage on multisite: wp site list --field=url | xargs -n1 -I % wp --url=% anonymizer delete-gravity-forms-entries
	 *
	 * @subcommand delete-gravity-forms-entries
	 * @return void
	 */
	public function delete_gravity_forms_entries() : void {
		if ( ! class_exists( 'GFAPI' ) ) {
			WP_CLI::error( 'Gravity forms API class not found. Is the plugin active?' );
			return;
		}

		$batch_size = 500;

		do {
			WP_CLI::line( "Deleting a batch of $batch_size entries." );

			$entries = GFAPI::get_entries( 0, [], null, [
				'page_size' => $batch_size,
			] );

			foreach ( $entries as $entry ) {
				$deleted = GFAPI::delete_entry( $entry['id'] );
				if ( ! is_wp_error( $deleted ) ) {
					WP_CLI::success( 'Deleted entry ' . $entry['id'] . '.' );
				} else  {
					WP_CLI::error( 'Error deleting entry ' . $entry['id'] . '.' );
				}
			}

			sleep( 1 );
		} while ( count( $entries ) > 0 );
	}

	/**
	 * Force deletion of gravity forms submission data across all sites on the network.
	 *
	 * This command just deletes all rows from gravity form entry tables.
	 * It is much quicker on sites with a large number of entries, but does not use GF functions so actions are not fired, and uploads not removed.
	 * It also across all tables in the database with the current prefix. So can clean data even if the plugin is not active, or the site no longer exists.
	 *
	 * ## Examples
	 *
	 *     Usage on multisite: wp site list --field=url | xargs -n1 -I % wp --url=% anonymizer force-delete-gravity-forms-entries-network-wide
	 *
	 * @subcommand force-delete-gravity-forms-entries-network-wide
	 * @return void
	 */
	public function force_delete_gravity_forms_entries_network_wide() : void {
		global $wpdb;

		$table_results = $wpdb->get_results( $wpdb->prepare( "
			SELECT table_name
			FROM information_schema.tables
			WHERE table_schema = %s
			AND (
				table_name REGEXP '^$wpdb->prefix([0-9]+_)?(gf_entry|df_lead)$'
				OR table_name REGEXP '^$wpdb->prefix([0-9]+_)?(gf_entry|df_lead)_.+$'
				OR table_name REGEXP '^$wpdb->prefix([0-9]+_)?gf_draft_submissions$'
			)
		", DB_NAME ) );

		foreach ( $table_results as $result ) {
			$query_result = $wpdb->query( "TRUNCATE TABLE $result->table_name" );

			if ( $query_result ) {
				WP_CLI::success( 'Emptied table ' . $result->table_name . '.' );
			} else {
				WP_CLI::error( 'Error emptying table ' . $result->table_name . '.' );
			}
		}
	}

	/**
	 * Force deletion of stream plugin records
	 *
	 * This command deletes all rows from stream record database tables directly.
	 *
	 * @subcommand force-delete-stream-records
	 * @return void
	 */
	public function force_delete_stream_records() : void {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->stream}" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->streammeta}" );
	}
}
