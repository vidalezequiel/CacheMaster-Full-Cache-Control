<?php
/*
Plugin Name: CacheMaster – Full Cache Control
Description: Limpia completamente la caché de WordPress, incluyendo transients, archivos temporales y caché de plugins compatibles. Desarrollado por Ezequiel Vidal.
Version: 1.0
Author: Ezequiel Vidal
Author URI: https://linkedin.com/in/ezeevidal
License: GPL2
*/

if (!defined('ABSPATH')) exit;

// Agregar menú al admin
add_action('admin_menu', function () {
    add_menu_page(
        'CacheMaster',
        'CacheMaster',
        'manage_options',
        'cachemaster',
        'cachemaster_render_admin_page',
        'dashicons-update',
        80
    );
});

// Renderizar página admin
function cachemaster_render_admin_page() {
    ?>
    <div class="wrap">
        <h1>🧹 CacheMaster – Full Cache Control</h1>
        <p>Seleccioná qué tipos de caché querés borrar:</p>
        <form method="post">
            <?php wp_nonce_field('cachemaster_action', 'cachemaster_nonce'); ?>
            <label><input type="checkbox" name="clear_transients" checked> Borrar transients</label><br>
            <label><input type="checkbox" name="clear_object_cache"> Borrar object cache</label><br>
            <label><input type="checkbox" name="clear_cache_files"> Borrar archivos en /wp-content/cache/</label><br><br>
            <input type="submit" name="cachemaster_clear" class="button button-primary" value="🧹 Borrar Caché Ahora">
        </form>
    </div>
    <?php
}

// Acción del botón
add_action('admin_init', function () {
    if (isset($_POST['cachemaster_clear']) && check_admin_referer('cachemaster_action', 'cachemaster_nonce')) {

        if (!current_user_can('manage_options')) return;

        if (isset($_POST['clear_transients'])) {
            global $wpdb;
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%'");
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_site_transient_%'");
        }

        if (isset($_POST['clear_object_cache']) && function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        if (isset($_POST['clear_cache_files'])) {
            $cache_dirs = [WP_CONTENT_DIR . '/cache/'];
            foreach ($cache_dirs as $dir) {
                if (is_dir($dir)) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ($files as $fileinfo) {
                        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                        @$todo($fileinfo->getRealPath());
                    }
                }
            }
        }

        add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Caché borrada exitosamente.</strong></p></div>';
        });
    }
});


// Agregar botón en la barra superior del admin
add_action('admin_bar_menu', function($admin_bar) {
    if (!current_user_can('manage_options')) return;

    $admin_bar->add_menu(array(
        'id'    => 'cachemaster-clear',
        'title' => '🧹 Borrar Caché',
        'href'  => wp_nonce_url(admin_url('?cachemaster=1'), 'cachemaster_adminbar')
    ));
}, 100);

// Ejecutar acción desde el botón
add_action('init', function() {
    if (
        is_admin() &&
        isset($_GET['cachemaster']) &&
        $_GET['cachemaster'] == 1 &&
        current_user_can('manage_options') &&
        check_admin_referer('cachemaster_adminbar')
    ) {
        global $wpdb;

        // Transients
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_site_transient_%'");

        // Object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Cache de archivos
        $cache_dirs = [WP_CONTENT_DIR . '/cache/'];
        foreach ($cache_dirs as $dir) {
            if (is_dir($dir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    @$todo($fileinfo->getRealPath());
                }
            }
        }

        add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Caché borrada exitosamente desde la barra superior.</strong></p></div>';
        });
    }
});
