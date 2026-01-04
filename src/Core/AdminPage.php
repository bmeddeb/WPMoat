<?php
/**
 * Admin Page Base Class.
 *
 * @package WPMoat\Core
 */

declare(strict_types=1);

namespace WPMoat\Core;

/**
 * Abstract base class for admin pages.
 *
 * Provides common functionality for creating WordPress admin pages,
 * handling form submissions, and rendering templates.
 */
abstract class AdminPage {

	/**
	 * Settings manager instance.
	 *
	 * @var Settings
	 */
	protected Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings manager instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get the page slug.
	 *
	 * @return string The page slug.
	 */
	abstract public function getSlug(): string;

	/**
	 * Get the page title.
	 *
	 * @return string The page title.
	 */
	abstract public function getTitle(): string;

	/**
	 * Get the menu title.
	 *
	 * @return string The menu title.
	 */
	public function getMenuTitle(): string {
		return $this->getTitle();
	}

	/**
	 * Get the required capability to access this page.
	 *
	 * @return string The capability.
	 */
	public function getCapability(): string {
		return 'manage_options';
	}

	/**
	 * Get the parent menu slug.
	 *
	 * Return null for a top-level menu.
	 *
	 * @return string|null The parent slug or null.
	 */
	public function getParentSlug(): ?string {
		return 'wp-moat';
	}

	/**
	 * Get the menu icon (for top-level menus only).
	 *
	 * @return string The dashicon class or URL.
	 */
	public function getIcon(): string {
		return 'dashicons-shield';
	}

	/**
	 * Get the menu position.
	 *
	 * @return int|null The menu position or null for default.
	 */
	public function getPosition(): ?int {
		return null;
	}

	/**
	 * Register the admin page.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'addMenuPage' ] );
		add_action( 'admin_init', [ $this, 'registerSettings' ] );
	}

	/**
	 * Add the menu page.
	 */
	public function addMenuPage(): void {
		$parent = $this->getParentSlug();

		if ( null === $parent ) {
			add_menu_page(
				$this->getTitle(),
				$this->getMenuTitle(),
				$this->getCapability(),
				$this->getSlug(),
				[ $this, 'render' ],
				$this->getIcon(),
				$this->getPosition()
			);
		} else {
			add_submenu_page(
				$parent,
				$this->getTitle(),
				$this->getMenuTitle(),
				$this->getCapability(),
				$this->getSlug(),
				[ $this, 'render' ]
			);
		}
	}

	/**
	 * Register settings for this page.
	 *
	 * Override in subclasses to register settings sections and fields.
	 */
	public function registerSettings(): void {
		// Override in subclasses.
	}

	/**
	 * Render the admin page.
	 */
	public function render(): void {
		if ( ! current_user_can( $this->getCapability() ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-moat' ) );
		}

		$template = $this->getTemplatePath();

		if ( file_exists( $template ) ) {
			// Make variables available to the template.
			$page     = $this;
			$settings = $this->settings;

			include $template;
		} else {
			$this->renderDefault();
		}
	}

	/**
	 * Get the template file path.
	 *
	 * @return string The template path.
	 */
	protected function getTemplatePath(): string {
		$slug = str_replace( 'wp-moat-', '', $this->getSlug() );
		return WP_MOAT_PATH . "templates/{$slug}.php";
	}

	/**
	 * Render default content if no template is found.
	 */
	protected function renderDefault(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $this->getTitle() ) . '</h1>';
		echo '<p>' . esc_html__( 'Page content coming soon.', 'wp-moat' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Output a nonce field for the settings form.
	 *
	 * @param string $action The nonce action.
	 */
	protected function nonceField( string $action ): void {
		wp_nonce_field( $action, 'wp_moat_nonce' );
	}

	/**
	 * Verify the nonce from a form submission.
	 *
	 * @param string $action The nonce action.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	protected function verifyNonce( string $action ): bool {
		$nonce = isset( $_POST['wp_moat_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_moat_nonce'] ) ) : '';
		return wp_verify_nonce( $nonce, $action ) !== false;
	}

	/**
	 * Add a settings error/success message.
	 *
	 * @param string $message The message text.
	 * @param string $type    The message type ('error', 'success', 'warning', 'info').
	 */
	protected function addNotice( string $message, string $type = 'success' ): void {
		add_settings_error(
			$this->getSlug(),
			$this->getSlug() . '_message',
			$message,
			$type
		);
	}

	/**
	 * Display any settings errors/notices.
	 */
	protected function displayNotices(): void {
		settings_errors( $this->getSlug() );
	}

	/**
	 * Render a settings section.
	 *
	 * @param string $id    The section ID.
	 * @param string $title The section title.
	 */
	protected function renderSection( string $id, string $title ): void {
		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<table class="form-table" role="presentation">';
		do_settings_fields( $this->getSlug(), $id );
		echo '</table>';
	}

	/**
	 * Render a text input field.
	 *
	 * @param string $name        The field name.
	 * @param string $value       The current value.
	 * @param string $description Optional description.
	 * @param string $type        Input type (text, password, email, etc.).
	 */
	protected function renderTextField(
		string $name,
		string $value,
		string $description = '',
		string $type = 'text'
	): void {
		printf(
			'<input type="%s" name="%s" value="%s" class="regular-text" />',
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $value )
		);

		if ( $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param string $name        The field name.
	 * @param bool   $checked     Whether the checkbox is checked.
	 * @param string $label       The checkbox label.
	 * @param string $description Optional description.
	 */
	protected function renderCheckbox(
		string $name,
		bool $checked,
		string $label,
		string $description = ''
	): void {
		printf(
			'<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
			esc_attr( $name ),
			checked( $checked, true, false ),
			esc_html( $label )
		);

		if ( $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	/**
	 * Render a select dropdown.
	 *
	 * @param string               $name        The field name.
	 * @param string               $value       The current value.
	 * @param array<string,string> $options     Array of value => label options.
	 * @param string               $description Optional description.
	 */
	protected function renderSelect(
		string $name,
		string $value,
		array $options,
		string $description = ''
	): void {
		echo '<select name="' . esc_attr( $name ) . '">';

		foreach ( $options as $option_value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $option_value ),
				selected( $value, $option_value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';

		if ( $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	/**
	 * Render a textarea field.
	 *
	 * @param string $name        The field name.
	 * @param string $value       The current value.
	 * @param string $description Optional description.
	 * @param int    $rows        Number of rows.
	 */
	protected function renderTextarea(
		string $name,
		string $value,
		string $description = '',
		int $rows = 5
	): void {
		printf(
			'<textarea name="%s" rows="%d" class="large-text">%s</textarea>',
			esc_attr( $name ),
			$rows,
			esc_textarea( $value )
		);

		if ( $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}
}
