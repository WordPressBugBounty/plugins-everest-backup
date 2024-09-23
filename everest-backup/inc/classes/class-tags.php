<?php
/**
 * Class for handling backup files tags.
 *
 * @package everest-backup
 * @since 1.0.9
 */

namespace Everest_Backup;

use Everest_Backup\Core\Archiver_V2;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling backup files tags.
 *
 * @since 1.0.9
 */
class Tags extends Backup_Directory {

	/**
	 * Cache tags.
	 *
	 * @var array
	 * @since 1.1.2
	 */
	protected $tags_cached = array();

	/**
	 * Full path to backup zip file.
	 *
	 * @var string
	 * @since 1.1.4
	 */
	protected $zipfile = '';

	/**
	 * Name of backup package without extension.
	 *
	 * @var string
	 */
	protected $filename = '';

	/**
	 * Init class.
	 *
	 * @param string $file Full path to backup package or filename of the backup package.
	 */
	public function __construct( $file = null ) {

		if ( $file ) {
			$this->zipfile  = $file;
			$this->filename = pathinfo( $file, PATHINFO_FILENAME );
		}
		parent::init();
	}

	/**
	 * Set tags using legacy method. i.e: tags.php
	 *
	 * @param array $params Parameters.
	 * @return void
	 */
	public function set_legacy( $params ) {

		if ( ! $this->filename ) {
			return;
		}

		$tags = $this->get_all();

		$tags[ $this->filename ] = everest_backup_generate_tags_from_params( $params );

		$prettify = everest_backup_is_debug_on() ? JSON_PRETTY_PRINT : 0;

		$contents   = array();
		$contents[] = $this->tags_content();
		$contents[] = "return '" . wp_json_encode( $tags, $prettify ) . '\';';
		$contents[] = null;

		file_put_contents( EVEREST_BACKUP_TAGS_PATH, implode( PHP_EOL, $contents ) ); // @phpcs:ignore

		$this->tags_cached = $tags;
	}

	/**
	 * Set using zarchive.
	 *
	 * @param \ZipArchive $lib Archiver.
	 * @param array       $tags Tags.
	 */
	protected function set_using_zarchive( $lib, $tags ) {
		/**
		 * Archiver.
		 *
		 * @var \ZipArchive
		 */
		$z = $lib;

		if ( $z->open( $this->zipfile ) ) {
			$z->addFromString( 'ebwp-tags.json', wp_json_encode( $tags, JSON_PRETTY_PRINT ) );
		}

		$z->close();
	}

	/**
	 * Set using fallback.
	 *
	 * @param \ZipArchive $lib Archiver.
	 * @param array       $tags Tags.
	 */
	protected function set_using_fallback( $lib, $tags ) {

		/**
		 * Zip file.
		 *
		 * @var \PhpZip\ZipFile
		 */
		$z = $lib;

		try {
			$z->openFile( $this->zipfile );

			$z->addFromString( 'ebwp-tags.json', wp_json_encode( $tags, JSON_PRETTY_PRINT ) );

			$z->saveAsFile( $this->zipfile );

			$z->close();
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Set tags.
	 *
	 * @param array $params Parameters to manipulate backup modules set during start of backup.
	 * @return void
	 */
	public function set( $params ) {
		$tags = everest_backup_generate_tags_from_params( $params );

		$archiver = everest_backup_get_archiver();

		if ( 'ziparchive' === $archiver['type'] ) {
			$this->set_using_zarchive( $archiver['lib'], $tags );
		} else {
			$this->set_using_fallback( $archiver['lib'], $tags );
		}
	}

	/**
	 * Returns included tags.
	 *
	 * @param array $excluded_tags Array of tags or excluded tags.
	 * @return array
	 */
	protected function get_included_tags( $excluded_tags ) {
		$excludes = everest_backup_get_backup_excludes();

		$tags = array();

		if ( is_array( $excludes ) && ! empty( $excludes ) ) {
			foreach ( $excludes as $exclude ) {
				$type = $exclude['type'];

				if ( in_array( $type, $excluded_tags, true ) ) {
					continue;
				}

				$tags[] = $type;
			}
		}

		return $tags;
	}

	/**
	 * Returns tags for legacy code.
	 *
	 * @param string|null $tags_display_type Tags display type.
	 * @return array
	 */
	public function get_legacy( $tags_display_type = null ) {
		$tags = $this->get_all();

		if ( ! isset( $tags[ $this->filename ] ) ) {
			return;
		}

		if ( is_null( $tags_display_type ) ) {
			$general = everest_backup_get_settings( 'general' );

			$tags_display_type = ! empty( $general['tags_display_type'] ) ? $general['tags_display_type'] : 'included';
		}

		if ( 'included' === $tags_display_type ) {
			return $this->get_included_tags( $tags[ $this->filename ] );
		}

		return ! empty( $tags[ $this->filename ] ) ? $tags[ $this->filename ] : array();
	}

	/**
	 * Set using zarchive.
	 *
	 * @param \ZipArchive $lib Archiver.
	 * @param string      $zipfile Tags.
	 */
	protected function get_using_zarchive( $lib, $zipfile ) {
		/**
		 * Archiver.
		 *
		 * @var \ZipArchive
		 */
		$z = $lib;

		$content = '';
		if ( $z->open( $zipfile ) ) {
			$content = $z->getFromName( 'ebwp-tags.json' );
		}

		$z->close();

		return $content;
	}

	/**
	 * Set using zarchive.
	 *
	 * @param \ZipArchive $lib Archiver.
	 * @param string      $zipfile Tags.
	 */
	protected function get_using_fallback( $lib, $zipfile ) {
		/**
		 * Zip file.
		 *
		 * @var \PhpZip\ZipFile
		 */
		$z = $lib;

		$content = '';

		try {
			if ( $z->openFile( $zipfile ) ) {
				$content = $z->getEntryContents( 'ebwp-tags.json' );
			}

			$z->close();
		} catch ( \PhpZip\Exception\ZipEntryNotFoundException $e ) {
			return;
		}

		return $content;
	}

	/**
	 * Support for backups made with Everest Backup 1.1.7 or earlier.
	 *
	 * @param string $tags_display_type Tags display type.
	 * @param string $zipfile           Zip file.
	 * @return array
	 */
	protected function get_before_v200( $tags_display_type, $zipfile ) {

		if ( ! file_exists( $zipfile ) ) {
			return array();
		}

		$archiver = everest_backup_get_archiver();

		$tags_json = '';
		if ( 'ziparchive' === $archiver['type'] ) {
			$tags_json = $this->get_using_zarchive( $archiver['lib'], $zipfile );
		} else {
			$tags_json = $this->get_using_fallback( $archiver['lib'], $zipfile );
		}

		if ( $tags_json ) {

			$tags = json_decode( $tags_json, true );

			if ( is_null( $tags_display_type ) ) {
				$general = everest_backup_get_settings( 'general' );

				$tags_display_type = ! empty( $general['tags_display_type'] ) ? $general['tags_display_type'] : 'included';
			}

			if ( 'included' === $tags_display_type ) {
				return $this->get_included_tags( $tags );
			}

			return $tags;

		}

		return $this->get_legacy( $tags_display_type );
	}

	/**
	 * From v2.0.0 and above.
	 *
	 * @param any $tags_display_type tags display type.
	 * @param any $zipfile tags display type.
	 */
	protected function get_from_v200( $tags_display_type, $zipfile ) {
		$archiver = new Archiver_V2( $zipfile );
		$tags     = $archiver->get_metadata( 'tags', true );

		if ( is_null( $tags_display_type ) ) {
			$general = everest_backup_get_settings( 'general' );

			$tags_display_type = ! empty( $general['tags_display_type'] ) ? $general['tags_display_type'] : 'included';
		}

		if ( 'included' === $tags_display_type ) {
			return $this->get_included_tags( $tags );
		}

		return $tags;
	}

	/**
	 * Returns tags for provided file.
	 *
	 * @param string|null $tags_display_type Tags display type. If null passed, it will use general settings value.
	 * @return array
	 */
	public function get( $tags_display_type = null ) {

		$zipfile = everest_backup_get_backup_full_path( $this->zipfile );

		if ( ! $zipfile ) {
			return;
		}

		return $this->get_from_v200( $tags_display_type, $zipfile );
	}

	/**
	 * Returns tags for all backup files.
	 *
	 * @return array
	 */
	public function get_all() {

		if ( $this->tags_cached ) {
			return $this->tags_cached;
		}

		if ( ! file_exists( EVEREST_BACKUP_TAGS_PATH ) ) {
			return array();
		}

		$json = include EVEREST_BACKUP_TAGS_PATH;

		if ( ! $json ) {
			return array();
		}

		$this->tags_cached = json_decode( $json, true );

		return $this->tags_cached && is_array( $this->tags_cached ) ? $this->tags_cached : array();
	}
}
