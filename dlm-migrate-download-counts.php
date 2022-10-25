<?php
/*
	Plugin Name: Download Monitor - Migrate Download Counts
	Plugin URI: https://www.download-monitor.com/
	Description: Migrate DLM download counts
	Version: 1.0.0
	Author: WPChill
	Author URI: https://wpchill.com
	License: GPL v3
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class DLM_Migrate_Counts{

	const VERSION = '1.0.0';

	public function __construct() {

        if ( is_admin() ) {
            add_action( 'admin_notices', array( $this, 'admin_notices' ), 8 );
            add_action( 'admin_init', array( $this, 'action_handler' ) );
        }

	}

	/**
	 * Display admin notices
	 *
	 * @return void
	 */
	public function admin_notices() {
        if( !get_option( 'dlm_mdc_ran', false) ){
            ?>
            <div id="dlm-migrate-download-counts-notice" class="notice notice-warning" style="margin-top:30px;">
                <h2><?php esc_html_e( 'Download Monitor - Migrate Download Counts', 'dlm-migrate-counts' ); ?></h2>
                <p><?php esc_html_e( 'Click the button below to migrate your Download Monitor\'s download counts. This is a one time only action.', 'dlm-migrate-counts' ); ?></p>
                <p>
                    <a href=" <?php echo add_query_arg( 'dlm_migrate_counts', 1, get_admin_url() ); ?> "  class="button button-primary"><?php esc_html_e( 'Sync Manual Counts With Reports', 'dlm-migrate-counts' ); ?></a>
                </p>
            </div>
            <?php
        }
        if( isset( $_GET['dlm_migrate_success'] ) && '1' === $_GET['dlm_migrate_success'] ){
            ?>
            <div id="dlm-migrate-download-counts-notice" class="notice notice-success" style="margin-top:30px;">
                <h2><?php esc_html_e( 'Download Monitor - Migrate Download Counts', 'dlm-migrate-counts' ); ?></h2>
                <p><?php esc_html_e( 'Download Monitor\'s download counts have been migrated.', 'dlm-migrate-counts' ); ?></p>
            </div>
            <?php
        }
	}

    public function action_handler(){

        if( isset( $_GET['dlm_migrate_counts'] ) && '1' === $_GET['dlm_migrate_counts'] ){
			global $wpdb;

			$downloads = $wpdb->get_results( "SELECT count(download_id) as download_count, download_id FROM " . $wpdb->download_log . " GROUP BY download_id LIMIT 0, 999999", 'ARRAY_A' );
           
            foreach( $downloads as $download ){
                $meta_count = absint( get_post_meta( $download['download_id'], '_download_count', true ) );

                if( $meta_count && '' != $meta_count ){

                    if( $meta_count > absint( $download['download_count'] ) ){

                        $meta_count = $meta_count - absint( $download['download_count'] );
                        update_post_meta( $download['download_id'], '_download_count', $meta_count );
                    }elseif( $meta_count < absint( $download['download_count'] ) ){

                        delete_post_meta( $download['download_id'], '_download_count' );
                    }

                }

            }
			add_option(
				'dlm_mdc_ran',
				array(
					'dlm_version'     => DLM_VERSION,
					'dlm_mdc_version' => DLM_MDC_VERSION,
					'upgraded_date' => wp_date( 'Y-m-d' ) . ' 00:00:00',
				)
			);

            wp_redirect( add_query_arg( 'dlm_migrate_success', 1, get_admin_url() ) );
            exit;

        }
    }


}

function _dlm_mdc() {

	define( 'DLM_MDC_PATH', plugin_dir_path( __FILE__ ) );
	define( 'DLM_MDC_URL', plugin_dir_url( __FILE__ ) );
	define( 'DLM_MDC_VERSION', '1.0.0' );

	new DLM_Migrate_Counts();
}

add_action( 'plugins_loaded', '_dlm_mdc', 99 );