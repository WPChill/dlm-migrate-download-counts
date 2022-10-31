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
            add_action( 'tool_box', array( $this, 'csv_export_import' ) );
        }

	}

	/**
	 * Display admin notices
	 *
	 * @return void
	 */
	public function admin_notices() {

        if( !current_user_can( 'manage_options' ) ){
            return false;
        }

        if( !class_exists('WP_DLM')){
            return false;
        }

        if( !get_option( 'dlm_mdc_ran', false) ){
            ?>
            <div id="dlm-migrate-download-counts-notice" class="notice notice-warning" style="margin-top:30px;">
                <h2><?php esc_html_e( 'Download Monitor - Migrate Download Counts', 'dlm-migrate-counts' ); ?></h2>
                <p><?php esc_html_e( 'Click the button below to migrate your Download Monitor\'s download counts. This is a one time only action.', 'dlm-migrate-counts' ); ?></p>
                <p>
                    <a href=" <?php echo esc_url( add_query_arg( array( 'dlm_migrate_counts' => 1, 'dlm_mdc_nonce' => wp_create_nonce( 'dlm_mdc_nonce' ) ), get_admin_url() ) ); ?> "  class="button button-primary"><?php esc_html_e( 'Sync Manual Counts With Reports', 'dlm-migrate-counts' ); ?></a>
                </p>
            </div>
            <?php
        }

        if( isset( $_REQUEST['dlm_migrate_success'] ) && '1' === $_REQUEST['dlm_migrate_success'] ){
            ?>
            <div id="dlm-migrate-download-counts-notice" class="notice notice-success" style="margin-top:30px;">
                <h2><?php esc_html_e( 'Download Monitor - Migrate Download Counts', 'dlm-migrate-counts' ); ?></h2>
                <p><?php esc_html_e( 'Download Monitor\'s download counts have been migrated. You can now delete this plugin.', 'dlm-migrate-counts' ); ?></p>
            </div>
            <?php
        }
        
        if( isset( $_REQUEST['dlm_meta_import_success'] ) && '1' === $_REQUEST['dlm_meta_import_success'] ){
            ?>
            <div id="dlm-migrate-download-counts-notice" class="notice notice-success" style="margin-top:30px;">
                <h2><?php esc_html_e( 'Download Monitor - Migrate Download Counts', 'dlm-migrate-counts' ); ?></h2>
                <p><?php esc_html_e( 'Download Monitor\'s meta import was successfull.', 'dlm-migrate-counts' ); ?></p>
            </div>
            <?php
        }
	}

    public function action_handler(){

        if( isset( $_REQUEST['dlm_migrate_counts'] ) && '1' === $_REQUEST['dlm_migrate_counts'] ){
           
            if( get_option( 'dlm_mdc_ran', false) ){
                return false;
            }

            if ( empty( $_REQUEST['dlm_mdc_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_REQUEST['dlm_mdc_nonce'] ), 'dlm_mdc_nonce' ) ) {
                return false;
            }

            if( !current_user_can( 'manage_options' ) ){
                return false;
            }

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
            
        }elseif( isset( $_REQUEST['dlm_mdc_action'] ) && 'export' === $_REQUEST['dlm_mdc_action'] ){

            $this->csv_export();
        }elseif( isset( $_REQUEST['dlm_mdc_action'] ) && 'import' === $_REQUEST['dlm_mdc_action'] ){

            $this->csv_import();
        }
    }

    public function csv_export_import(){
        ?>
        <div class="card">
			<h2 class="title"><?php esc_html_e( 'Download Monitor - Migrate Download Counts', 'dlm-migrate-counts' ); ?></h2>
			<div>
                <p><?php esc_html_e( 'Export DLM downloads meta', 'dlm-migrate-counts' ); ?></p>
                <a href="<?php echo esc_url( add_query_arg( array( 'dlm_mdc_action' => 'export', 'dlm_mdc_nonce' => wp_create_nonce( 'dlm_mdc_nonce' ) ), get_admin_url() . 'tools.php' ) ); ?> "  class="button button-primary"><?php esc_html_e( 'Export to CSV', 'dlm-migrate-counts' ); ?></a>
            </div>
			<div>
                <form enctype="multipart/form-data" method="POST" action="<?php echo get_admin_url() . 'tools.php'; ?>">
                <p><?php esc_html_e( 'or import DLM downloads meta', 'dlm-migrate-counts' ); ?></p>
                <input type="hidden" value="<?php echo wp_create_nonce( 'dlm_mdc_nonce' ); ?>" name="dlm_mdc_nonce" />
                <input type="hidden" value="import" name="dlm_mdc_action" />
                <input type="file" name="dlm_migrate_counts_csv" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel"/><br>
                <button class="button button-primary"><?php esc_html_e( 'Import from CSV', 'dlm-migrate-counts' ); ?></button>
            </div>
		</div>
        <?php
    }

    public function csv_export(){

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Aborted export request, insufficient user permission to export download data.' );
        }

        // Check nonce
        if ( ! wp_verify_nonce( $_REQUEST['dlm_mdc_nonce'], 'dlm_mdc_nonce' ) ) {
            wp_die( 'Aborted export request, nonce check failed.' );
        }

		// Build array
		$csv_data = array();

		// Add CSV header
		$csv_data['header'] = array(
			'download_id',
			'_download_count',
		);

		$csv_string = '';

		// Headers
		$csv_string .= implode( ',', $csv_data['header'] ) . PHP_EOL;

        // Loop
        global $wpdb;

        $downloads = $wpdb->get_results( "SELECT post_id as download_id, meta_value as download_count FROM " . $wpdb->postmeta . " WHERE meta_key = '_download_count' LIMIT 0, 999999", 'ARRAY_A' );

        foreach( $downloads as $download ){

            $csv_string .= ( isset( $download['download_id'] ) ? $download['download_id'] : 0 ). ',' . ( $download['download_count'] ? $download['download_count'] : '0' ) . PHP_EOL;

        }

        // Check
        if ( '' !== $csv_string ) {

            // Ouput the CSV headers
            $this->output_headers();

            // Output the string
            echo wp_kses_post( $csv_string );
            exit;

        } else {
            wp_die( 'Download Monitor export failed, no data in CSV string found.' );
        }
    }

    /**
     * Output the CSV headers
     */
    public function output_headers() {
        header( "Content-type: text/csv" );
        header( "Content-Disposition: attachment; filename=download-monitor-download-counts.csv" );
        header( "Pragma: no-cache" );
        header( "Expires: 0" );
    }

    private function csv_import(){

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Aborted import request, insufficient user permission to import download data.' );
        }

        if ( ! wp_verify_nonce( $_REQUEST['dlm_mdc_nonce'], 'dlm_mdc_nonce' ) ) {
            wp_die( 'Aborted import request, nonce check failed.' );
        }

        if( !isset( $_FILES['dlm_migrate_counts_csv']['tmp_name'] ) || '' === $_FILES['dlm_migrate_counts_csv']['tmp_name'] ){
            wp_die( 'Aborted import request, CSV file not imported.' );
        }

        $tmpName = $_FILES['dlm_migrate_counts_csv']['tmp_name'];
        $rows = array_map('str_getcsv', file( $tmpName ) );
        $header = array_shift( $rows );

        foreach( $rows as $row ) {
            
            $metas = array_combine($header, $row);

            // No download id: skip.
            if( 0 === absint( $metas['download_id'] ) ){ continue; }

            $check_meta = get_post_meta( $metas['download_id'], '_download_count', true );

            if( $check_meta ){

                // Download count is 0, we delete the meta.
                if( 0 === absint( $metas['_download_count'] ) ){

                    delete_post_meta( $metas['download_id'], '_download_count' );
                }else{

                    update_post_meta( $metas['download_id'], '_download_count', absint( $metas['_download_count'] ) );
                }
            }else{

                if( 0 !== absint( $metas['_download_count'] ) ){

                    add_post_meta( $metas['download_id'], '_download_count', absint( $metas['_download_count'] ) );
                }
            }

        }

        wp_redirect( add_query_arg( 'dlm_meta_import_success', 1, get_admin_url() . 'tools.php' ) );
    }

}



function _dlm_mdc() {

	define( 'DLM_MDC_PATH', plugin_dir_path( __FILE__ ) );
	define( 'DLM_MDC_URL', plugin_dir_url( __FILE__ ) );
	define( 'DLM_MDC_VERSION', '1.0.0' );

	new DLM_Migrate_Counts();
}

add_action( 'plugins_loaded', '_dlm_mdc', 99 );