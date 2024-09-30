<?php
/**
 * Template part for process lock information for other users.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $args['type'] ) ) {
	return;
}

$everest_backup_class = ! empty( $args['class'] ) ? $args['class'] : '';

if ( everest_backup_is_ebwp_page() && $everest_backup_class ) {
	/**
	 * Bail and show only single notice in our pages.
	 */
	return;
}

?>
<style>
	.notice.ebwp-center.ebwp-proc-lock-wrapper {
		background: #ffffff;
		display: flex;
		align-items: center;
		border: 1px solid #a2badd;
		border-left: 4px solid #0b5cd1;
		gap: 20px;
		padding: 1px 12px
	}

	.notice.ebwp-center.ebwp-proc-lock-wrapper img {
		width: 80px;
	}

	.notice.ebwp-center.ebwp-proc-lock-wrapper h1,
	.notice.ebwp-center.ebwp-proc-lock-wrapper p {
		padding: 0;
		margin: 0;
	}

	.ebwp-proc-info {
		padding: 10px;
	}

	.ebwp-proc-stale .button {
		margin: 15px 0 5px;
		background: #ff1616;
		color: #ffffff;
		border-color: #ff1616;
	}
</style>

<div class="<?php echo esc_attr( $everest_backup_class ); ?> ebwp-center ebwp-proc-lock-wrapper">

	<img class="logo-icon" src="<?php echo esc_url( EVEREST_BACKUP_URL . 'assets/images/ebwp-loading.gif' ); ?>">

	<div class="ebwp-proc-info">
		<h1>
			<?php
			if ( everest_backup_cloud_get_option( 'cloud_upload_in_progress' ) ) {
				echo esc_html( wptexturize( __( 'Backup is being uploaded to cloud.', 'everest-backup' ) ) );
			} else {
				echo esc_html( wptexturize( __( "Everest Backup's process is running", 'everest-backup' ) ) );
			}
			?>
		</h1>

		<p class="ebwp-proc-description">
			<?php
			$everest_backup_userdata      = get_userdata( $args['uid'] );
			$everest_backup_process_types = everest_backup_get_process_types();

			if ( ! empty( $everest_backup_userdata->user_nicename ) ) {
				printf(
					/* translators: %1$s is user nicename, %2$s is process type, %3$s is time started and %4$s is time elapsed. */
					esc_html__( 'The user %1$s is performing %2$s since %3$s', 'everest-backup' ),
					'<strong>"' . esc_html( ucwords( $everest_backup_userdata->user_nicename ) ) . '"</strong>',
					'<strong>' . esc_html( $everest_backup_process_types[ $args['type'] ] ) . '</strong>',
					'<strong class="proc-lock-datetime"></strong>'
				);
			} else {
				printf(
					/* translators: %1$s is Everest Backup plugin name, %2$s is process type, %3$s is time started and %4$s is time elapsed. */
					esc_html__( '%1$s is performing %2$s since %3$s', 'everest-backup' ),
					'<strong>"Everest Backup"</strong>',
					'<strong>' . esc_html( $everest_backup_process_types[ $args['type'] ] ) . '</strong>',
					'<strong class="proc-lock-datetime"></strong>'
				);
			}
			?>
		</p>

		<div class="ebwp-proc-lock-info-wrapper">
			<details style="margin: 10px 0 0;cursor: pointer;border: 1px solid;padding: 5px;">
				<summary>
					<?php esc_html_e( 'Click to view details', 'everest-backup' ); ?>
					<progress min="0" max="100" value="0" style="width: 100%"></progress>
				</summary>

				<textarea  wrap="off" readonly style="height: 125px;width: 100%;background: #000;color: #01e901;margin-top:10px;"></textarea>
			</details>
		</div>

		<?php
		$force_abort_url = add_query_arg(
			array(
				'page'        => 'everest-backup-export',
				'force-abort' => true,
				'uid'         => get_current_user_id(),
			),
			network_admin_url( '/admin.php' )
		);
		?>

		<div class="ebwp-proc-stale">
			<a href="<?php echo esc_url( wp_nonce_url( $force_abort_url ) ); ?>" class="button"><?php esc_html_e( 'Force Abort', 'everest-backup' ); ?></a>
		</div>

	</div>


	<script>
	/**
	 * Writing this scripts here in inline because we need to display this template in everywhere needed.
	 */
	(function(){

		document.addEventListener('DOMContentLoaded', function() {

			ebwpProcLockInfoHandler(document.querySelector('.ebwp-proc-lock-info-wrapper details'));

			const dateElement = document.querySelector('.ebwp-proc-info .proc-lock-datetime');

			if (dateElement) {
				const dateTime = new Date(<?php echo absint( "{$args['time']}000" ); ?>);
				dateElement.innerHTML = `${dateTime.toLocaleTimeString()} [ ${dateTime.toDateString()} ]`
			}

		});

		function ebwpProcLockInfoHandler(el) {

			if (!el) {
				return;
			}

			let confirmationShown = false;
			let retry = 1;

			let   lastDetail     = "";
			const processDetails = el.querySelector("textarea");
			const heading        = document.querySelector('.ebwp-proc-info h1');
			const wrapper        = document.querySelector('.ebwp-proc-lock-wrapper');
			const img            = document.querySelector('.ebwp-proc-lock-wrapper .logo-icon');
			const abortBtn       = document.querySelector('.ebwp-proc-stale .button');

			function handleSuccessHeadingsAndImages() {
				heading.innerHTML = "<?php echo esc_html( wptexturize( __( "Everest Backup's process completed", 'everest-backup' ) ) ); ?>";
				img.setAttribute('src', '<?php echo esc_url( EVEREST_BACKUP_URL . 'assets/images/ebwp-stop.png' ); ?>');

				<?php if ( everest_backup_is_ebwp_page() ) { ?>
					setTimeout(function() {
						if (confirmationShown) {
							return;
						}

						confirmationShown = true;
						if ( confirm("<?php echo esc_html( wptexturize( __( 'Everest Backup: Process Completed. Reload Now?', 'everest-backup' ) ) ); ?>") ) {
							return window.location.href = `${window.location.href}&force_reload=true`;
						}
					}, 2000);
				<?php } ?>

				document.querySelector('.ebwp-proc-description').remove();

			}

			function handleErrorHeadingsAndImages() {
				heading.innerHTML = "<?php echo esc_html( wptexturize( __( "Everest Backup's process Error", 'everest-backup' ) ) ); ?>";
				heading.style.color = 'red';
				img.setAttribute('src', '<?php echo esc_url( EVEREST_BACKUP_URL . 'assets/images/ebwp-stop.png' ); ?>');
				wrapper.classList.add('notice-error');
				wrapper.style.borderLeft = '4px solid #d63638';
				document.querySelector('.ebwp-proc-description').remove();
			}

			function handleProcessDetails(details) {
				if (details === lastDetail) {
					return;
				}
				if (!processDetails) {
					return;
				}
				if ("undefined" === typeof details || !details) {
					return;
				}
				processDetails.value = `${details}\n` + processDetails.value;
				lastDetail = details;
			};

			function handleProgress(percent) {

				if (!percent) {
					return;
				}

				const progressEL = el.querySelector('progress');

				if (!progressEL) {
					return;
				}

				progressEL.innerHTML = `${percent}%`;
				progressEL.value = percent;
				progressEL.setAttribute('title', `${percent}%`);

			}

			function sseURL() {
				const url = new URL("<?php echo esc_url( everest_backup_get_sse_url() ); ?>");
				url.searchParams.append('t', `${+new Date()}`);
				return url.toString();
			}

			let timeoutNumber = 0;

			async function onBeaconSent () {
				const response = await fetch(sseURL());
				const result = response.json();
				result
					.then((res) => {
						switch (res.status) {
							case 'done':
								el.remove();
								abortBtn.remove();
								handleSuccessHeadingsAndImages();
								break;
							case 'cloud':
								el.remove();
								abortBtn.remove();
								break;
							case 'error':
								el.remove();
								abortBtn.remove();
								handleErrorHeadingsAndImages();
								break;
							default:
								handleProgress(res.progress);
								handleProcessDetails(res.detail);
								setTimeout(onBeaconSent, 1000);
								break;
						}
					})
					.catch((err) => {
						console.warn(err);

						if (timeoutNumber) clearInterval(timeoutNumber);

						if ( retry > 5 ) {
							return handleProcessDetails("<?php esc_html_e( 'Failed to reconnect 5 times. Try reloading...', 'everest-backup' ); ?>");
						}

						handleProcessDetails("<?php esc_html_e( 'Lost connection with server. Reconnecting...', 'everest-backup' ); ?>");

						const retrySec = retry * 1000;
						timeoutNumber = setTimeout(onBeaconSent, retrySec);

						retry++;
					});
			};

			onBeaconSent();


			abortBtn.addEventListener('click', function(e) {
				e.preventDefault();

				const abortText = prompt("<?php /* translators: %s is replaced with "Abort" */printf( esc_html__( 'Please type `%s` to abort the process.', 'everest-backup' ), 'Abort' ); ?>");

				if (!abortText) {
					return;
				}

				if ('abort' === abortText.trim().toLowerCase()) {
					window.location.href = abortBtn.getAttribute('href');
				} else {
					alert("<?php esc_html_e( 'Prompt did not match.', 'everest-backup' ); ?>");
				}
			});


		}
	}());
	</script>
</div>
