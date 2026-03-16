<?php
/**
 * Filesystem manager.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Filesystem {

    /**
     * Ensure folders.
     *
     * @return void
     */
    public static function ensure_base_structure(): void {
        $base = WCPI_Helpers::upload_base();
        $dirs = array(
            array( 'path' => $base['dir'], 'sensitive' => false ),
            array( 'path' => $base['dir'] . 'exports/', 'sensitive' => false ),
            array( 'path' => $base['dir'] . 'cache/', 'sensitive' => true ),
            array( 'path' => $base['dir'] . 'logs/', 'sensitive' => true ),
            array( 'path' => $base['dir'] . 'backups/', 'sensitive' => true ),
            array( 'path' => $base['dir'] . 'temp/', 'sensitive' => true ),
        );

        foreach ( $dirs as $dir ) {
            if ( ! is_dir( $dir['path'] ) ) {
                wp_mkdir_p( $dir['path'] );
            }

            self::protect_dir( $dir['path'], (bool) $dir['sensitive'] );
        }
    }

    /**
     * Put blockers in a directory.
     *
     * @param string $dir Directory.
     * @param bool   $sensitive Whether to block file access.
     * @return void
     */
    public static function protect_dir( string $dir, bool $sensitive = true ): void {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $index_file = trailingslashit( $dir ) . 'index.php';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }

        if ( ! $sensitive ) {
            return;
        }

        $htaccess = trailingslashit( $dir ) . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $rules = "Options -Indexes\n<FilesMatch \"\\.(php|phar|phtml|cgi|pl|py|sh)$\">\nDeny from all\n</FilesMatch>\n<Files *>\nRequire all denied\n</Files>\n";
            file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
    }

    /**
     * Secure filename.
     *
     * @param string $prefix Prefix.
     * @param string $ext Extension.
     * @return string
     */
    public static function secure_filename( string $prefix, string $ext ): string {
        $rand = wp_generate_password( 10, false, false );
        return sanitize_file_name( $prefix . '-' . gmdate( 'Ymd-His' ) . '-' . $rand . '.' . ltrim( $ext, '.' ) );
    }

    /**
     * Write file.
     *
     * @param string $subdir Subdir.
     * @param string $filename Name.
     * @param string $contents Contents.
     * @return array<string, string>|WP_Error
     */
    public static function write_file( string $subdir, string $filename, string $contents ) {
        self::ensure_base_structure();
        $base = WCPI_Helpers::upload_base();
        $trim = trim( $subdir, '/' );
        $dir  = trailingslashit( $base['dir'] . $trim );
        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return new WP_Error( 'wcpi_dir_error', __( 'Could not create export directory.', WCPI_TEXT_DOMAIN ) );
        }

        self::protect_dir( $dir, ! in_array( $trim, array( 'exports' ), true ) );

        $filepath = $dir . $filename;
        $result   = file_put_contents( $filepath, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        if ( false === $result ) {
            return new WP_Error( 'wcpi_write_error', __( 'Could not write file.', WCPI_TEXT_DOMAIN ) );
        }

        return array(
            'path' => $filepath,
            'url'  => trailingslashit( $base['url'] . $trim ) . $filename,
        );
    }

    /**
     * Delete matching files older than given days.
     *
     * @param string $subdir Subdir.
     * @param int    $days Age.
     * @return int
     */
    public static function cleanup_old_files( string $subdir, int $days ): int {
        $base  = WCPI_Helpers::upload_base();
        $dir   = trailingslashit( $base['dir'] . trim( $subdir, '/' ) );
        $count = 0;

        if ( ! is_dir( $dir ) ) {
            return $count;
        }

        foreach ( glob( $dir . '*' ) as $file ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob
            if ( is_file( $file ) && filemtime( $file ) < strtotime( '-' . absint( $days ) . ' days' ) ) {
                unlink( $file ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Recursively delete plugin upload directory.
     *
     * @return void
     */
    public static function delete_all(): void {
        $base = WCPI_Helpers::upload_base();
        self::rrmdir( $base['dir'] );
    }

    /**
     * Recursive delete.
     *
     * @param string $dir Directory.
     * @return void
     */
    private static function rrmdir( string $dir ): void {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        foreach ( scandir( $dir ) as $item ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_scandir
            if ( '.' === $item || '..' === $item ) {
                continue;
            }

            $path = $dir . '/' . $item;
            if ( is_dir( $path ) ) {
                self::rrmdir( $path );
            } else {
                unlink( $path ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
            }
        }

        rmdir( $dir ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_rmdir
    }
}
