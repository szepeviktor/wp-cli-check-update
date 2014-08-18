<?php

use \WP_CLI\Utils;

/**
 * Perform operations on prefixed database tables.
 */
class WP_CLI_Check_Update extends WP_CLI_Command {

    /**
     * Initiate a HTTP request. Cloned from commands/core.php
     *
     * @param string $method The request method.
     * @param string $url The path for the request.
     * @param array $headers The HTTP request headers (optional)
     * @param array $options Options for Requests::get (optional)
     */
    private function request( $method, $url, $headers = array(), $options = array() ) {
        // cURL can't read Phar archives
        if ( 0 === strpos( WP_CLI_ROOT, 'phar://' ) ) {
            $options['verify'] = sys_get_temp_dir() . '/wp-cli-cacert.pem';

            copy(
                WP_CLI_ROOT . '/vendor/rmccue/requests/library/Requests/Transport/cacert.pem',
                $options['verify']
            );
        }

        try {
            return \Requests::get( $url, $headers, $options );
        } catch( \Requests_Exception $ex ) {
            // Handle SSL certificate issues gracefully
            \WP_CLI::warning( $ex->getMessage() );
            $options['verify'] = false;
            try {
                return \Requests::get( $url, $headers, $options );
            } catch( \Requests_Exception $ex ) {
                \WP_CLI::error( $ex->getMessage() );
            }
        }
    }

	/**
	 * Check for update via Github API. Returns latest version if there's an update, or empty if no update available.
	 *
	 * ## OPTIONS
	 *
	 * [--major]
	 * : Compare only the first two parts of the version number.
	 *
	 * @subcommand check-update
	 */
	function check_update( $_, $assoc_args ) {
		$url = 'https://api.github.com/repos/wp-cli/wp-cli/releases';

		$options = array(
			'timeout' => 30
		);

		$headers = array(
			'Accept' => 'application/json'
		);
		$response = $this->request( 'GET', $url, $headers, $options );

		if ( ! $response->success || 200 !== $response->status_code ) {
			WP_CLI::error( "Failed to get latest version." );
		}

		$release_data = json_decode( $response->body );

		$latest = $release_data[0]->tag_name;

		// get rid of leading "v"
		if ( 'v' === substr( $latest, 0, 1 ) ) {
			$latest = ltrim( $latest, 'v' );
		}

		if ( isset( $assoc_args['major'] ) ) {
			$latest_major = explode( '.', $latest );
			$current_major = explode( '.', WP_CLI_VERSION );

			if ( $latest_major[0] !== $current_major[0]
				|| $latest_major[1] !== $current_major[1]
            ) {
				WP_CLI::line( $latest );
			}

		} else {
			WP_CLI::line( $latest );
		}
	}

}

WP_CLI::add_command( 'cli', 'check-update' );

