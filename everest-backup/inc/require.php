<?php
/**
 * Include all of our files from here.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Include all of our files from here.
 *
 * @return void
 * @since 1.0.0
 */
function everest_backup_require_files() {
	$path  = EVEREST_BACKUP_PATH;
	$files = array(

		'inc/functions.php',

		/**
		 * Deprecated.
		 */
		'inc/deprecated/1.1.2.php',
		'inc/deprecated/2.1.5.php',

		/**
		 * Traits.
		 */
		'inc/traits/trait-singleton.php',
		'inc/traits/trait-backup.php',
		'inc/traits/trait-export.php',
		'inc/traits/trait-import.php',
		'inc/traits/trait-restore.php',

		/**
		 * Core classes.
		 */
		'inc/classes/class-file-uploader.php',
		'inc/classes/class-proc-lock.php',
		'inc/classes/class-updater.php',
		'inc/classes/class-transient.php',
		'inc/classes/class-migration-clone.php',
		'inc/classes/class-tabs-factory.php',
		'inc/classes/class-cloud.php',
		'inc/classes/class-logs.php',
		'inc/classes/class-filesystem.php',
		'inc/classes/class-backup-directory.php',
		'inc/classes/class-temp-directory.php',
		'inc/classes/class-tags.php',
		'inc/classes/class-admin-menu.php',
		'inc/classes/class-archiver.php',
		'inc/classes/class-compress.php',
		'inc/classes/class-extract.php',
		'inc/classes/class-database.php',
		'inc/classes/class-cron.php',
		'inc/classes/class-email.php',
		'inc/classes/class-ajax.php',
		'inc/classes/class-server-information.php',

		/**
		 * Migration and clone modules.
		 */
		'inc/modules/migration-clone/class-migration.php',
		'inc/modules/migration-clone/class-cloner.php',

		/**
		 * Database child classes.
		 */
		'inc/modules/database/class-export-database.php',
		'inc/modules/database/class-import-database.php',

		/**
		 * All the backup modules.
		 */
		'inc/modules/backup/class-backup-config.php',
		'inc/modules/backup/class-backup-database.php',
		'inc/modules/backup/class-backup-uploads.php',
		'inc/modules/backup/class-backup-themes.php',
		'inc/modules/backup/class-backup-plugins.php',
		'inc/modules/backup/class-backup-content.php',

		/**
		 * All the restore modules.
		 */
		'inc/modules/restore/class-restore-config.php',
		'inc/modules/restore/class-restore-multisite.php',
		'inc/modules/restore/class-restore-database.php',
		'inc/modules/restore/class-restore-users.php',
		'inc/modules/restore/class-restore-uploads.php',
		'inc/modules/restore/class-restore-themes.php',
		'inc/modules/restore/class-restore-plugins.php',
		'inc/modules/restore/class-restore-content.php',

		/**
		 * Tabs modules.
		 */
		'inc/modules/tabs/class-backup-tab.php',
		'inc/modules/tabs/class-restore-tab.php',
		'inc/modules/tabs/class-migration-clone-tab.php',
		'inc/modules/tabs/class-settings-tab.php',

		/**
		 * Other modules.
		 */
		'inc/modules/history/class-history-table.php',
		'inc/modules/logs/class-logs-table.php',
		'inc/modules/cron/class-cron-handler.php',
		'inc/modules/cron/class-cron-actions.php',
		'inc/modules/email/class-send-test-email.php',
		'inc/modules/email/class-email-logs.php',

		'inc/template-functions.php',

		/**
		 * Core directory.
		 *
		 * @since 2.0.0
		 */
		'inc/core/class-init.php',

		'inc/core/class-list-backup.php',

		'inc/classes/class-everest-backup.php',
	);

	if ( is_array( $files ) && ! empty( $files ) ) {
		foreach ( $files as $file ) {
			$file_path = $path . $file;
			require_once wp_normalize_path( $file_path );
		}
	}
}

everest_backup_require_files();
