<?php

class MDFA_Content_Signals {

	private const SIGNALS = [ 'ai_train', 'search', 'ai_input' ];
	private const META_PREFIX = '_mdfa_signal_';

	public static function init(): void {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
		add_action( 'save_post', [ __CLASS__, 'save_post_signals' ] );

		$enabled_taxonomies = (array) get_option( 'mdfa_taxonomies', [ 'category', 'post_tag' ] );
		foreach ( $enabled_taxonomies as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", [ __CLASS__, 'render_term_fields' ], 10, 1 );
			add_action( "edited_{$taxonomy}", [ __CLASS__, 'save_term_signals' ], 10, 1 );
		}
	}

	public static function add_meta_box(): void {
		$enabled_types = (array) get_option( 'mdfa_post_types', [ 'post', 'page' ] );
		add_meta_box(
			'mdfa_content_signals',
			'Content-Signal (Markdown for Agents)',
			[ __CLASS__, 'render_meta_box' ],
			$enabled_types,
			'side',
			'default'
		);
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'mdfa_content_signals', 'mdfa_content_signals_nonce' );

		$labels = [
			'ai_train' => 'ai-train',
			'search'   => 'search',
			'ai_input' => 'ai-input',
		];

		foreach ( self::SIGNALS as $signal ) {
			$value  = get_post_meta( $post->ID, self::META_PREFIX . $signal, true );
			if ( $value === '' ) {
				$value = 'global';
			}
			$global = get_option( "mdfa_signal_{$signal}", true ) ? __( 'tak', 'markdown-for-agents' ) : __( 'nie', 'markdown-for-agents' );

			echo '<p><label><strong>' . esc_html( $labels[ $signal ] ) . '</strong><br>';
			echo '<select name="mdfa_signal_' . esc_attr( $signal ) . '" style="width:100%">';
			echo '<option value="global"' . selected( $value, 'global', false ) . '>' . sprintf( __( 'Globalny (%s)', 'markdown-for-agents' ), $global ) . '</option>';
			echo '<option value="yes"' . selected( $value, 'yes', false ) . '>' . __( 'Tak', 'markdown-for-agents' ) . '</option>';
			echo '<option value="no"' . selected( $value, 'no', false ) . '>' . __( 'Nie', 'markdown-for-agents' ) . '</option>';
			echo '</select></label></p>';
		}
	}

	public static function save_post_signals( int $post_id ): void {
		if ( ! isset( $_POST['mdfa_content_signals_nonce'] ) ||
			 ! wp_verify_nonce( $_POST['mdfa_content_signals_nonce'], 'mdfa_content_signals' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( self::SIGNALS as $signal ) {
			$key   = 'mdfa_signal_' . $signal;
			$value = isset( $_POST[ $key ] ) ? sanitize_key( $_POST[ $key ] ) : 'global';

			if ( ! in_array( $value, [ 'global', 'yes', 'no' ], true ) ) {
				$value = 'global';
			}

			if ( $value === 'global' ) {
				delete_post_meta( $post_id, self::META_PREFIX . $signal );
			} else {
				update_post_meta( $post_id, self::META_PREFIX . $signal, $value );
			}
		}
	}

	public static function render_term_fields( WP_Term $term ): void {
		wp_nonce_field( 'mdfa_content_signals_term', 'mdfa_content_signals_term_nonce' );

		$labels = [
			'ai_train' => 'ai-train',
			'search'   => 'search',
			'ai_input' => 'ai-input',
		];

		echo '<tr class="form-field"><th scope="row" colspan="2"><h2>Content-Signal (Markdown for Agents)</h2></th></tr>';

		foreach ( self::SIGNALS as $signal ) {
			$value  = get_term_meta( $term->term_id, self::META_PREFIX . $signal, true );
			if ( $value === '' ) {
				$value = 'global';
			}
			$global = get_option( "mdfa_signal_{$signal}", true ) ? __( 'tak', 'markdown-for-agents' ) : __( 'nie', 'markdown-for-agents' );

			echo '<tr class="form-field">';
			echo '<th scope="row"><label for="mdfa_signal_' . esc_attr( $signal ) . '">' . esc_html( $labels[ $signal ] ) . '</label></th>';
			echo '<td><select name="mdfa_signal_' . esc_attr( $signal ) . '" id="mdfa_signal_' . esc_attr( $signal ) . '">';
			echo '<option value="global"' . selected( $value, 'global', false ) . '>' . sprintf( __( 'Globalny (%s)', 'markdown-for-agents' ), $global ) . '</option>';
			echo '<option value="yes"' . selected( $value, 'yes', false ) . '>' . __( 'Tak', 'markdown-for-agents' ) . '</option>';
			echo '<option value="no"' . selected( $value, 'no', false ) . '>' . __( 'Nie', 'markdown-for-agents' ) . '</option>';
			echo '</select></td>';
			echo '</tr>';
		}
	}

	public static function save_term_signals( int $term_id ): void {
		if ( ! isset( $_POST['mdfa_content_signals_term_nonce'] ) ||
			 ! wp_verify_nonce( $_POST['mdfa_content_signals_term_nonce'], 'mdfa_content_signals_term' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		foreach ( self::SIGNALS as $signal ) {
			$key   = 'mdfa_signal_' . $signal;
			$value = isset( $_POST[ $key ] ) ? sanitize_key( $_POST[ $key ] ) : 'global';

			if ( ! in_array( $value, [ 'global', 'yes', 'no' ], true ) ) {
				$value = 'global';
			}

			if ( $value === 'global' ) {
				delete_term_meta( $term_id, self::META_PREFIX . $signal );
			} else {
				update_term_meta( $term_id, self::META_PREFIX . $signal, $value );
			}
		}
	}

	/**
	 * Resolve Content-Signal values with fallback to global options.
	 *
	 * @return array<string, bool> Keys: ai_train, search, ai_input
	 */
	public static function get_signals( ?WP_Post $post = null, ?WP_Term $term = null ): array {
		$signals = [];

		foreach ( self::SIGNALS as $signal ) {
			$value = '';

			if ( $post !== null ) {
				$value = get_post_meta( $post->ID, self::META_PREFIX . $signal, true );
			} elseif ( $term !== null ) {
				$value = get_term_meta( $term->term_id, self::META_PREFIX . $signal, true );
			}

			if ( $value === 'yes' ) {
				$signals[ $signal ] = true;
			} elseif ( $value === 'no' ) {
				$signals[ $signal ] = false;
			} else {
				$signals[ $signal ] = (bool) get_option( "mdfa_signal_{$signal}", true );
			}
		}

		return $signals;
	}
}
