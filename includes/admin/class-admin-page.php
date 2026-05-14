<?php
namespace WA_ACF_PTM\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_Page {
	private static bool $registered = false;
	private static bool $rendered = false;
	private Admin_Page_View_Model $view_model;
	private Template_Renderer $renderer;
	private string $hook_suffix = '';

	public function __construct( Admin_Page_View_Model $view_model, Template_Renderer $renderer ) {
		$this->view_model = $view_model;
		$this->renderer   = $renderer;
	}

	public function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
	}

	private function is_plugin_screen_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing check.
		return isset( $_GET['page'] ) && 'acf-page-text-manager' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	public function add_menu(): void {
		$this->hook_suffix = (string) add_menu_page(
			__( 'Tekstbeheer', 'acf-page-text-manager' ),
			__( 'Tekstbeheer', 'acf-page-text-manager' ),
			'manage_options',
			'acf-page-text-manager',
			array( $this, 'render' ),
			'dashicons-edit-page',
			58
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page state controls whether editor assets are needed.
		$selected_target = isset( $_GET['target'] ) ? sanitize_text_field( wp_unslash( $_GET['target'] ) ) : '';
		if ( '' !== $selected_target ) {
			wp_enqueue_editor();
		}

		wp_enqueue_style(
			'wa-acf-ptm-admin-base',
			WA_ACF_PTM_URL . 'assets/css/admin-base.css',
			array(),
			WA_ACF_PTM_VERSION
		);

		wp_enqueue_style(
			'wa-acf-ptm-admin-components',
			WA_ACF_PTM_URL . 'assets/css/admin-components.css',
			array( 'wa-acf-ptm-admin-base' ),
			WA_ACF_PTM_VERSION
		);

		wp_enqueue_style(
			'wa-acf-ptm-admin-export',
			WA_ACF_PTM_URL . 'assets/css/admin-export.css',
			array( 'wa-acf-ptm-admin-components' ),
			WA_ACF_PTM_VERSION
		);

		wp_enqueue_style(
			'wa-acf-ptm-admin-import',
			WA_ACF_PTM_URL . 'assets/css/admin-import.css',
			array( 'wa-acf-ptm-admin-export' ),
			WA_ACF_PTM_VERSION
		);

		wp_enqueue_script(
			'wa-acf-ptm-admin-core',
			WA_ACF_PTM_URL . 'assets/js/admin-core.js',
			array(),
			WA_ACF_PTM_VERSION,
			true
		);

		wp_enqueue_script(
			'wa-acf-ptm-admin-tabs',
			WA_ACF_PTM_URL . 'assets/js/admin-tabs.js',
			array( 'wa-acf-ptm-admin-core' ),
			WA_ACF_PTM_VERSION,
			true
		);

		wp_enqueue_script(
			'wa-acf-ptm-admin-import',
			WA_ACF_PTM_URL . 'assets/js/admin-import.js',
			array( 'wa-acf-ptm-admin-core' ),
			WA_ACF_PTM_VERSION,
			true
		);

		wp_enqueue_script(
			'wa-acf-ptm-admin',
			WA_ACF_PTM_URL . 'assets/js/admin.js',
			array( 'wa-acf-ptm-admin-core', 'wa-acf-ptm-admin-tabs', 'wa-acf-ptm-admin-import' ),
			WA_ACF_PTM_VERSION,
			true
		);

		wp_localize_script(
			'wa-acf-ptm-admin-core',
			'waAcfPtm',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wa_acf_ptm_ajax_nonce' ),
				'strings' => array(
					'needsFile'                => __( 'Kies eerst een CSV-, XLSX- of ZIP-bestand.', 'acf-page-text-manager' ),
					'needsTarget'              => __( 'Kies eerst een item om veldinhoud te bekijken of te exporteren.', 'acf-page-text-manager' ),
					'invalidFileType'          => __( 'Kies alleen CSV-, XLSX- of ZIP-bestanden.', 'acf-page-text-manager' ),
					'preparing'                => __( 'Voorbeeld wordt voorbereid…', 'acf-page-text-manager' ),
					'previewProcessing'        => __( 'Importvoorbeeld wordt gelezen…', 'acf-page-text-manager' ),
					'previewReading'           => __( 'Het bestand wordt gelezen en gevalideerd…', 'acf-page-text-manager' ),
					'previewCompletedTitle'    => __( 'Importvoorbeeld klaar', 'acf-page-text-manager' ),
					'processing'               => __( 'Import wordt uitgevoerd…', 'acf-page-text-manager' ),
					'previewReady'             => __( 'Voorbeeld is klaar. Controleer de samenvatting en start daarna de import.', 'acf-page-text-manager' ),
					'importDone'               => __( 'Import afgerond.', 'acf-page-text-manager' ),
					'rollbackDownloadLabel'    => __( 'Rollback CSV downloaden', 'acf-page-text-manager' ),
					'confirmRunImport'         => __( 'Weet je zeker dat je deze import wilt uitvoeren? Download eerst het rollbackbestand als je wijzigingen snel wilt kunnen terugzetten.', 'acf-page-text-manager' ),
					'previewFailed'            => __( 'Voorbeeld mislukt.', 'acf-page-text-manager' ),
					'importFailed'             => __( 'Import mislukt.', 'acf-page-text-manager' ),
					'unexpectedImportResponse'  => __( 'Onverwacht serverantwoord tijdens import.', 'acf-page-text-manager' ),
					'multipleFilesSelected'    => __( 'bestanden geselecteerd', 'acf-page-text-manager' ),
					'previewTitle'             => __( 'Importvoorbeeld', 'acf-page-text-manager' ),
					'filesSummaryLabel'        => __( 'Bestanden', 'acf-page-text-manager' ),
					'changesPreviewLabel'      => __( 'Wijzigingsvoorbeeld', 'acf-page-text-manager' ),
					'mappedColumnsLabel'       => __( 'Herkende kolommen', 'acf-page-text-manager' ),
					'unmappedColumnsLabel'     => __( 'Niet-herkende kolommen', 'acf-page-text-manager' ),
					'rowLabel'                 => __( 'Rij', 'acf-page-text-manager' ),
					'fieldLabel'               => __( 'Veld', 'acf-page-text-manager' ),
					'currentValueLabel'        => __( 'Huidig', 'acf-page-text-manager' ),
					'newValueLabel'            => __( 'Nieuw', 'acf-page-text-manager' ),
					'statusLabel'              => __( 'Status', 'acf-page-text-manager' ),
					'statusUpdate'             => __( 'Bijwerken', 'acf-page-text-manager' ),
					'statusSkip'               => __( 'Overslaan', 'acf-page-text-manager' ),
					'updatesShortLabel'        => __( 'updates', 'acf-page-text-manager' ),
					'skipsShortLabel'          => __( 'skips', 'acf-page-text-manager' ),
					'updatesLabel'             => __( 'Rijen om bij te werken', 'acf-page-text-manager' ),
					'skipsLabel'               => __( 'Overgeslagen rijen', 'acf-page-text-manager' ),
					'warningsLabel'            => __( 'Waarschuwingen', 'acf-page-text-manager' ),
					'progressLabel'            => __( 'Importvoortgang', 'acf-page-text-manager' ),
					'runImportLabel'           => __( 'Import nu uitvoeren', 'acf-page-text-manager' ),
					'closeLabel'               => __( 'Sluiten', 'acf-page-text-manager' ),
					'completedTitle'           => __( 'Import afgerond', 'acf-page-text-manager' ),
					'itemsProcessedLabel'      => __( 'velden verwerkt', 'acf-page-text-manager' ),
					'importLabel'              => __( 'Import', 'acf-page-text-manager' ),
					'chooseFile'               => __( 'Bestand kiezen', 'acf-page-text-manager' ),
					'noFileChosen'             => __( 'Nog geen bestand gekozen', 'acf-page-text-manager' ),
					/* translators: %s: field label. */
					'exportFieldIncluded'      => __( '%s wordt meegenomen in export.', 'acf-page-text-manager' ),
					/* translators: %s: field label. */
					'exportFieldExcluded'      => __( '%s wordt niet meegenomen in export.', 'acf-page-text-manager' ),
					/* translators: 1: selected field count, 2: total field count. */
					'exportSelectionCount'     => __( '%1$d van %2$d velden geselecteerd voor export.', 'acf-page-text-manager' ),
					'exportNoFieldsSelected'   => __( 'Selecteer minstens één veld voor export.', 'acf-page-text-manager' ),
					'exportNoItemsSelected'    => __( 'Selecteer minstens één item om te exporteren.', 'acf-page-text-manager' ),
					'filterAllFields'          => __( 'Alle veldgroepen worden getoond.', 'acf-page-text-manager' ),
					'filterSelectionUpdated'   => __( 'Veldfilters bijgewerkt.', 'acf-page-text-manager' ),
					'itemsFoundSingular'       => __( '1 item gevonden', 'acf-page-text-manager' ),
					/* translators: %d: found item count. */
					'itemsFoundPlural'         => __( '%d items gevonden', 'acf-page-text-manager' ),
					'selectedCountLabel'       => __( 'geselecteerd', 'acf-page-text-manager' ),
					'visibleCountLabel'        => __( 'zichtbaar', 'acf-page-text-manager' ),
					'moreLabel'                => __( 'meer', 'acf-page-text-manager' ),
					'typeLabels'               => array(
						'post' => __( 'Bericht', 'acf-page-text-manager' ),
						'page' => __( 'Pagina', 'acf-page-text-manager' ),
					),
					'exportAllFieldsSelected'  => __( 'Alle velden worden meegenomen in export.', 'acf-page-text-manager' ),
					'exportAllFieldsCleared'   => __( 'Alle velden zijn uitgezet voor export.', 'acf-page-text-manager' ),
					'inlineEditHint'           => __( 'Klik om tekst aan te passen', 'acf-page-text-manager' ),
					'inlineSaving'             => __( 'Opslaan…', 'acf-page-text-manager' ),
					'inlineSaved'              => __( 'Automatisch opgeslagen.', 'acf-page-text-manager' ),
					'inlineSaveError'          => __( 'Opslaan mislukt.', 'acf-page-text-manager' ),
					'inlineEmptyPlaceholder'   => __( 'Leeg', 'acf-page-text-manager' ),
					'inlineEditorLabel'        => __( 'Veldinhoud aanpassen', 'acf-page-text-manager' ),
					'confirmInlineMediaRename' => __( 'Deze wijziging past de fysieke media-bestandsnaam aan en kan bestaande media-URL’s wijzigen. Doorgaan?', 'acf-page-text-manager' ),
					'mediaRenameNeedsSave'     => __( 'Gebruik Opslaan om deze media-bestandsnaamwijziging expliciet te bevestigen.', 'acf-page-text-manager' ),
					'exportPreparing'          => __( 'Export wordt voorbereid…', 'acf-page-text-manager' ),
					'exportDownload'           => __( 'Export downloaden', 'acf-page-text-manager' ),
					'exportStarted'            => __( 'Download gestart. Controleer je downloads als er geen nieuw venster verschijnt.', 'acf-page-text-manager' ),
				),
			)
		);
	}

	public function render(): void {
		if ( self::$rendered ) {
			return;
		}

		self::$rendered = true;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Je hebt geen toestemming om deze pagina te bekijken.', 'acf-page-text-manager' ) );
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Template renderer output is escaped inside partial templates.
		echo $this->renderer->render(
			'admin/page.php',
			array_merge(
				$this->view_model->build(),
				array(
					'renderer' => $this->renderer,
				)
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function render_notices(): void {
		if ( ! $this->is_plugin_screen_request() ) {
			return;
		}

		$this->render_dependency_notices();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin notice query args.
		$type    = isset( $_GET['wa_acf_ptm_notice'] ) ? sanitize_key( wp_unslash( $_GET['wa_acf_ptm_notice'] ) ) : '';
		$message = isset( $_GET['wa_acf_ptm_message'] ) ? sanitize_text_field( wp_unslash( $_GET['wa_acf_ptm_message'] ) ) : '';

		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $type || '' === $message ) {
			return;
		}

		$class = 'notice-info';
		if ( 'success' === $type ) {
			$class = 'notice-success';
		} elseif ( 'error' === $type ) {
			$class = 'notice-error';
		}

		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}


	private function render_dependency_notices(): void {
		$minimum_acf_version = '6.7.2';

		if ( ! defined( 'ACF_VERSION' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'ACF Page Text Manager heeft Advanced Custom Fields nodig. Activeer ACF voordat je deze plugin gebruikt.', 'acf-page-text-manager' ) . '</p></div>';
			return;
		}

		if ( version_compare( (string) ACF_VERSION, $minimum_acf_version, '<' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf(
				/* translators: 1: detected ACF version, 2: recommended minimum ACF version. */
				__( 'Je gebruikt ACF versie %1$s. Test en update bij voorkeur naar ACF %2$s of nieuwer voordat je import/export- en media-rename workflows uitvoert.', 'acf-page-text-manager' ),
				(string) ACF_VERSION,
				$minimum_acf_version
			) ) . '</p></div>';
		}
	}

}
