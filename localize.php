<?php
/*
Plugin Name: Localize WordPress
Plugin URI: https://github.com/stas/localize
Description: Easily switch to any localization from GlotPress
Version: 0.2
Author: Stas Sușcov
Author URI: http://stas.nerd.ro/
*/
?>
<?php
/*  Copyright 2011  Stas Sușcov <stas@nerd.ro>

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

define( 'LOCALIZE', '0.1' );

class Localize {
    /**
     * init()
     * 
     * Sets the hooks and other initialization stuff
     */
    function init() {
        add_action( 'admin_menu', array( __CLASS__, 'page' ) );
        add_action( 'init', array( __CLASS__, 'localization' ) );
    }

    /**
     * localization()
     * 
     * i18n
     */
    function localization() {
        load_plugin_textdomain( 'localize', false, basename( dirname( __FILE__ ) ) . 'languages' );
    }
    
    /**
     * page()
     * 
     * Adds the options page to existing menu
     */
    function page() {
        add_options_page(
            __( 'Localization', 'localize' ),
            __( 'Localization', 'localize' ),
            'administrator',
            'localize',
            array( __CLASS__, 'page_body' )
        );
    }
    
    /**
     * page()
     * 
     * Callback to render the options page and handle it's form
     */
    function page_body() {
        $flash = null;
        
        if( isset( $_POST['localize_nonce'] ) && wp_verify_nonce( $_POST['localize_nonce'], 'localize' ) ) {
            $lang = null;
            $lang_version = null;
            $locale = null;
            
            if( isset( $_POST['lang'] ) && !empty( $_POST['lang'] ) )
                $lang = sanitize_text_field( $_POST['lang'] );
            
            if( isset( $_POST['lang_version'] ) && !empty( $_POST['lang_version'] ) )
                $lang_version = sanitize_key( $_POST['lang_version'] );
            
            if( $lang && strstr( $lang, '_' ) )
                update_option( 'localize_lang', $lang );
            
            if( $lang_version && in_array( $lang_version, array( 'stable', 'dev' ) ) )
                update_option( 'localize_lang_version', $lang_version );
            
            if( !self::update_config() )
                $flash = __( "Sorry, the <code>wp-config.php</code> could not be updated...", 'localize' );
            
            if( $lang != 'en_US' )
                $locale = self::update_mo();
            else
                $locale = "English";
            
            if( !$locale )
                $flash = __( 'There was an error downloading the file!','localize' );
            else
                $flash = $locale . __( ' localization updated! Please reload this page...','localize' );
        }
        
        $vars = self::get_locale();
        $vars['flash'] = $flash;
        self::render( 'settings', $vars );
    }
    
    /**
     * get_locale()
     *
     * Fetches the current options for custom locale
     * @return Mixed, an array of options as keys
     */
    function get_locale() {
        return array(
            'lang' => get_option( 'localize_lang', get_locale() ),
            'lang_version' => get_option( 'localize_lang_version', 'stable' )
        );
    }
    
    /**
     * update_mo()
     *
     * Updates the po file from WordPress.org GlotPress repo
     * @return String, the name of the updated locale
     */
    function update_mo() {
        $repo = 'http://translate.wordpress.org/projects/wp/%s/%s/default/export-translations?format=mo';
        $languages_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR;
        $settings = self::get_locale();
        $po_path = $languages_dir . $settings['lang'] . '.mo';
        
        if( !is_dir( $languages_dir ) )
            @mkdir( $languages_dir, 0755, true );
        
        $versions = self::get_versions();
        if( !is_array( $versions ) )
            return;
        
        if( $settings['lang_version'] == 'dev' )
            $version = reset( $versions );
        else
            $version = end( $versions );
        
        $locale = self::get_locale_data( $settings['lang'], $version );
        if( !is_array( $locale ) )
            return;
        
        $po_uri = sprintf( $repo, $version, $locale[1] );
        $tmp_po = download_url( $po_uri );
        
        if ( is_wp_error($tmp_po) ) {
            @unlink( $tmp_po );
            return false;
        }
        
        if( @copy( $tmp_po, $po_path ))
            if( @unlink( $tmp_po ) )
                return $locale[0];
    }
    
    /**
     * fetch_glotpress()
     *
     * Uses GlotPress api to get the repository details
     * @return Mixed, decoded json from api, or false on failure
     */
    function fetch_glotpress( $args = '' ) {
        global $wp_version;
        
        $api = "http://translate.wordpress.org/api/projects/wp/";
        $request = new WP_Http;
        
        $request_args = array(
            'timeout' => 30,
            'user-agent' => 'WordPress/' . $wp_version . '; Localize/' . LOCALIZE . '; ' . get_bloginfo( 'url' )
        );
        
        $response = $request->request( $api . $args, $request_args);
        if( !is_wp_error( $response ) )
            return json_decode( $response['body'] );
        else
            return;
    }
    
    /**
     * get_versions()
     *
     * Extracts the repository versions from GlotPress api
     * @return Mixed, an array of `name -> slug` versions
     */
    function get_versions() {
        $versions = null;
        
        $repo_info = self::fetch_glotpress();
        if( is_object( $repo_info ) && isset( $repo_info->sub_projects ))
            foreach( $repo_info->sub_projects as $p )
                $versions[$p->name] = $p->slug;
        
        return $versions;
    }
    
    /**
     * get_locale_data( $locale, $version )
     *
     * Extracts the locale data from GlotPress api
     * @param String $locale, the locale you want to get data about. Ex.: ru_RU
     * @param String $version, the GlotPress version slug
     * @return Mixed, an array of `name -> locale_slug` format
     */
    function get_locale_data( $locale, $version ) {
        $locales_info = self::fetch_glotpress( $version );
        if( is_object( $locales_info ) && isset( $locales_info->translation_sets ))
            foreach( $locales_info->translation_sets as $t )
                if( strstr( $locale, $t->locale ) )
                    return array( $t->name, $t->locale);
        return;
    }
    
    /**
     * update_config()
     *
     * Updates the `wp-config.php` file using new locale
     * @return Boolean, false on failre and true on success
     */
    function update_config() {
        $wp_config_path = ABSPATH . 'wp-config.php';
        $wpc_h = fopen( $wp_config_path, "r+" );
        
        $content = stream_get_contents( $wpc_h );
        if( !$content && !flock( $wpc_h, LOCK_EX ) )
            return false;
        
        $settings = self::get_locale();
        $locale = $settings['lang'];
        
        $source = "/define(.*)WPLANG(.*)\'(.*)\'(.*);(.*)/";
        $target = "define ('WPLANG', '$locale'); // Updated by `Localize` plugin";
        
        $content = preg_replace( $source, $target, $content );
        
        rewind( $wpc_h );
        if( !@fwrite( $wpc_h, $content ) )
            return false;
        flock( $wpc_h, LOCK_UN );
        
        return true;
    }
    
    /**
     * render( $name, $vars = null, $echo = true )
     *
     * Helper to load and render templates easily
     * @param String $name, the name of the template
     * @param Mixed $vars, some variables you want to pass to the template
     * @param Boolean $echo, to echo the results or return as data
     * @return String $data, the resulted data if $echo is `false`
     */
    function render( $name, $vars = null, $echo = true ) {
        ob_start();
        if( !empty( $vars ) )
            extract( $vars );
        
        include dirname( __FILE__ ) . '/templates/' . $name . '.php';
        
        $data = ob_get_clean();
        
        if( $echo )
            echo $data;
        else
            return $data;
    }
}

Localize::init();

?>