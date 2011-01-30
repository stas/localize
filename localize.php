<?php
/*
Plugin Name: Localize WordPress
Plugin URI: https://github.com/stas/localize
Description: Easily switch to any localization from GlotPress
Version: 0.1
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
        add_filter( 'gettext', array( __CLASS__, 'transalte' ) );
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
            
            if( isset( $_POST['lang'] ) && !empty( $_POST['lang'] ) )
                $lang = sanitize_text_field( $_POST['lang'] );
            
            if( isset( $_POST['lang_version'] ) && !empty( $_POST['lang_version'] ) )
                $lang_version = sanitize_key( $_POST['lang_version'] );
            
            if( $lang && strstr( $lang, '_' ) )
                update_option( 'localize_lang', $lang );
            
            if( $lang_version && in_array( $lang_version, array( 'stable', 'dev' ) ) )
                update_option( 'localize_lang_version', $lang_version );
            
            if( self::update_config() )
                $flash = __( 'Localization updated! Please reload this page...','localize' );
            
            if( $lang != 'en_US' )
                if( !self::update_po() )
                    $flash = __( 'There was an error downloading the file!','localize' );
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
     * update_po()
     *
     * Updates the po file from WordPress.org GlotPress repo
     * @return Boolean, false on failre and true on success
     */
    function update_po() {
        $repo = 'http://translate.wordpress.org/projects/wp/%s/%s/default/export-translations?export-format=po';
        $languages_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR;
        $settings = self::get_locale();
        $po_path = $languages_dir . $settings['lang'] . '.po';
        
        if( file_exists( $po_path ) )
            return true;
        
        if( !is_dir( $languages_dir ) )
            @mkdir( $languages_dir, 0755, true );
        
        $locale = explode( '_', $settings['lang'] );
        if( $settings['lang_version'] == 'stable' )
            $version = '3.0.x';
        else
            $version = 'dev';
        
        $po_uri = sprintf( $repo, $version, $locale[0] );
        $tmp_po = download_url( $po_uri );
        
        if ( is_wp_error($tmp_po) ) {
            @unlink( $tmp_po );
            return false;
        }
        
        if( @copy( $tmp_po, $po_path ))
            return @unlink( $tmp_po );
    }
    
    /**
     * translate( $translate )
     *
     * Hooks into `gettext` filter to overwrite existing/lacking translations using new po file
     * @param String $translate, the string that has to be translated
     * @return String, the translated result
     */
    function transalte( $translate = null ) {
        $settings = self::get_locale();
        $locale = $settings['lang'];
        
        $new_locales = self::load_po( $locale );
        
        if( is_array( $new_locales ) && key_exists( $translate, $new_locales ) )
            return $new_locales[$translate];
        else
            return $translate;
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
     * load_po( $lang )
     *
     * Loads the po file strings into memory
     * @url: http://www.rogerdudler.com/?p=342
     * @param String $lang, the locale of the po file
     * @return Mixed array of translations as original -> translated
     */
    function load_po( $lang ) {
        global $localize;
        $po_file = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR . $lang . '.po';
        if( !file_exists( $po_file ) )
            return;
        
        if( $localize && isset( $localize[$lang] ))
            return $localize[$lang];
        
        $translations = array();
        $po = file( $po_file );
        $current = null;
        foreach ( $po as $line ) {
            if ( substr($line,0,5) == 'msgid' ) {
                $current = trim( substr( trim( substr( $line,5 ) ),1,-1 ));
            }
            if ( substr( $line,0,6 ) == 'msgstr' ) {
                $translations[$current] = trim( substr( trim( substr( $line,6 ) ),1,-1 ));
            }
        }
        
        $localize[$lang] = $translations;
        
        return $localize[$lang];
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