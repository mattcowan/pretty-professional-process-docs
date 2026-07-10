<?php
/**
 * Report-type and section-type registry — the plugin's extensibility spine.
 *
 * Registrations are code, not data: the registry is rebuilt on every request.
 * Built-in types register through the same public API a third party uses
 * (pppd_register_report_type / pppd_register_section_type), on init priority
 * 5, after which the pppd_register_types action fires for extensions. Side
 * effects that depend on taxonomies and core meta being registered (term
 * sync, field meta registration, validation) run later, in
 * pppd_registry_apply() at init priority 20.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Holds all registered report types and section types.
 */
final class PPPD_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var PPPD_Registry|null
	 */
	private static $instance = null;

	/**
	 * Registered report types, keyed by slug.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $report_types = array();

	/**
	 * Registered section types, keyed by slug.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $section_types = array();

	/**
	 * Get (and lazily create) the singleton instance.
	 *
	 * @return PPPD_Registry
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register a report type.
	 *
	 * @param string               $slug Report type slug.
	 * @param array<string, mixed> $args {
	 *     Report type arguments.
	 *
	 *     @type string   $label                Human-readable name.
	 *     @type string   $description          Short description.
	 *     @type string[] $section_types        Section type slugs this report type offers.
	 *     @type string   $default_section_type Slug preselected when adding a section.
	 *     @type array    $id_scheme            {prefix, format} used by ID-bearing section
	 *                                          types that do not declare their own prefix.
	 * }
	 * @return array<string, mixed> The resolved arguments.
	 */
	public function register_report_type( $slug, $args = array() ) {
		$slug = sanitize_key( $slug );

		$defaults = array(
			'label'                => $slug,
			'description'          => '',
			'section_types'        => array(),
			'default_section_type' => '',
			'id_scheme'            => array(
				'prefix' => 'FR',
				'format' => '%s-%03d',
			),
		);

		$args              = wp_parse_args( $args, $defaults );
		$args['id_scheme'] = wp_parse_args( is_array( $args['id_scheme'] ) ? $args['id_scheme'] : array(), $defaults['id_scheme'] );

		/**
		 * Filter the arguments of a report type as it is registered.
		 *
		 * @param array<string, mixed> $args Resolved arguments.
		 * @param string               $slug Report type slug.
		 */
		$args = apply_filters( 'pppd_report_type_args', $args, $slug );

		$this->report_types[ $slug ] = $args;

		return $args;
	}

	/**
	 * Register a section type.
	 *
	 * "Item types" are section types flagged is_item — an ID-bearing leaf unit
	 * (requirement, decision-log entry, finding) rather than a narrative
	 * container. There is no separate item entity: an item is a section post.
	 *
	 * @param string               $slug Section type slug (a pppd_section_type term slug).
	 * @param array<string, mixed> $args {
	 *     Section type arguments.
	 *
	 *     @type string $label                  Human-readable name.
	 *     @type bool   $is_item                Leaf item unit vs narrative container.
	 *     @type bool   $has_ids                Participates in item-ID assignment on publish.
	 *     @type string $id_prefix              This type's own ID prefix. Empty string defers
	 *                                          to the report type's id_scheme prefix.
	 *     @type bool   $report_prefix_override An explicit per-report _pppd_req_prefix meta row
	 *                                          overrides the prefix for this type.
	 *     @type bool   $traceable              Appears in the traceability matrix and drift.
	 *     @type string $meta_partial           Absolute path of a template partial rendered
	 *                                          after the section body on the report view.
	 *     @type array  $fields                 Meta fields keyed by meta key: {type, visibility,
	 *                                          sanitize_callback}. visibility is 'client' or
	 *                                          'internal'; internal fields never render on the
	 *                                          client-facing view. Fields not already registered
	 *                                          are registered on pppd_section in
	 *                                          pppd_registry_apply().
	 * }
	 * @return array<string, mixed> The resolved arguments.
	 */
	public function register_section_type( $slug, $args = array() ) {
		$slug = sanitize_key( $slug );

		$defaults = array(
			'label'                  => $slug,
			'is_item'                => false,
			'has_ids'                => false,
			'id_prefix'              => '',
			'report_prefix_override' => false,
			'traceable'              => false,
			'meta_partial'           => '',
			'fields'                 => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filter the arguments of a section type as it is registered.
		 *
		 * @param array<string, mixed> $args Resolved arguments.
		 * @param string               $slug Section type slug.
		 */
		$args = apply_filters( 'pppd_section_type_args', $args, $slug );

		$this->section_types[ $slug ] = $args;

		return $args;
	}

	/**
	 * Get one report type's arguments, or null when unregistered.
	 *
	 * @param string $slug Report type slug.
	 * @return array<string, mixed>|null
	 */
	public function get_report_type( $slug ) {
		return isset( $this->report_types[ $slug ] ) ? $this->report_types[ $slug ] : null;
	}

	/**
	 * Get every registered report type, keyed by slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_report_types() {
		return $this->report_types;
	}

	/**
	 * Get one section type's arguments, or null when unregistered.
	 *
	 * @param string $slug Section type slug.
	 * @return array<string, mixed>|null
	 */
	public function get_section_type( $slug ) {
		return isset( $this->section_types[ $slug ] ) ? $this->section_types[ $slug ] : null;
	}

	/**
	 * Get every registered section type, keyed by slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_section_types() {
		return $this->section_types;
	}

	/**
	 * Whether a section type declares a boolean feature flag.
	 *
	 * Unregistered types support nothing: a section whose type term has no
	 * registration gets no IDs and no traceability row (matching the pre-registry
	 * behavior for non-requirement sections).
	 *
	 * @param string $slug    Section type slug.
	 * @param string $feature Flag name (is_item, has_ids, traceable, ...).
	 * @return bool
	 */
	public function section_type_supports( $slug, $feature ) {
		$type = $this->get_section_type( $slug );

		return null !== $type && ! empty( $type[ $feature ] );
	}
}

/**
 * Get the registry singleton.
 *
 * @return PPPD_Registry
 */
function pppd_registry() {
	return PPPD_Registry::instance();
}

/**
 * Register a report type. Wrapper around PPPD_Registry::register_report_type().
 *
 * Call on the pppd_register_types action (or init priority 5–19).
 *
 * @param string               $slug Report type slug.
 * @param array<string, mixed> $args Report type arguments.
 * @return array<string, mixed>
 */
function pppd_register_report_type( $slug, $args = array() ) {
	return pppd_registry()->register_report_type( $slug, $args );
}

/**
 * Register a section type. Wrapper around PPPD_Registry::register_section_type().
 *
 * Call on the pppd_register_types action (or init priority 5–19).
 *
 * @param string               $slug Section type slug.
 * @param array<string, mixed> $args Section type arguments.
 * @return array<string, mixed>
 */
function pppd_register_section_type( $slug, $args = array() ) {
	return pppd_registry()->register_section_type( $slug, $args );
}

/**
 * Register the built-in section types and report types, then open the
 * registry to extensions.
 *
 * Hooked to init at priority 5.
 *
 * @return void
 */
function pppd_register_builtin_types() {
	pppd_register_section_type(
		'narrative',
		array(
			'label' => __( 'Narrative', 'pretty-professional-process-docs' ),
		)
	);

	pppd_register_section_type(
		'requirement',
		array(
			'label'                  => __( 'Requirement', 'pretty-professional-process-docs' ),
			'is_item'                => true,
			'has_ids'                => true,
			// No own prefix: requirements take the report type's id_scheme
			// prefix (FR for FRDs), overridable per report via _pppd_req_prefix.
			'id_prefix'              => '',
			'report_prefix_override' => true,
			'traceable'              => true,
			'meta_partial'           => PPPD_PLUGIN_DIR . 'templates/partials/requirement-meta.php',
			'fields'                 => array(
				'_pppd_acceptance' => array(
					'type'       => 'array',
					'visibility' => 'client',
				),
				// Retired from the client view (0.3.0); writes mirror into
				// _pppd_impl_notes while consumers migrate.
				'_pppd_dev_prompt' => array(
					'type'       => 'string',
					'visibility' => 'internal',
				),
				'_pppd_impl_notes' => array(
					'type'       => 'string',
					'visibility' => 'internal',
				),
				'_pppd_code_refs'  => array(
					'type'       => 'array',
					'visibility' => 'internal',
				),
				'_pppd_test_refs'  => array(
					'type'       => 'array',
					'visibility' => 'internal',
				),
			),
		)
	);

	pppd_register_section_type(
		'decision',
		array(
			'label'   => __( 'Decision', 'pretty-professional-process-docs' ),
			'is_item' => true,
		)
	);

	pppd_register_section_type(
		'data-requirement',
		array(
			'label'        => __( 'Data Requirement', 'pretty-professional-process-docs' ),
			'is_item'      => true,
			'has_ids'      => true,
			'id_prefix'    => 'DR',
			'traceable'    => true,
			'meta_partial' => PPPD_PLUGIN_DIR . 'templates/partials/requirement-meta.php',
			'fields'       => array(
				'_pppd_acceptance' => array(
					'type'       => 'array',
					'visibility' => 'client',
				),
				'_pppd_impl_notes' => array(
					'type'       => 'string',
					'visibility' => 'internal',
				),
				'_pppd_code_refs'  => array(
					'type'       => 'array',
					'visibility' => 'internal',
				),
				'_pppd_test_refs'  => array(
					'type'       => 'array',
					'visibility' => 'internal',
				),
			),
		)
	);

	pppd_register_section_type(
		'ui-rule',
		array(
			'label'        => __( 'UI Rule', 'pretty-professional-process-docs' ),
			'is_item'      => true,
			'has_ids'      => true,
			'id_prefix'    => 'UI',
			'traceable'    => true,
			'meta_partial' => PPPD_PLUGIN_DIR . 'templates/partials/requirement-meta.php',
			'fields'       => array(
				'_pppd_acceptance' => array(
					'type'       => 'array',
					'visibility' => 'client',
				),
				'_pppd_impl_notes' => array(
					'type'       => 'string',
					'visibility' => 'internal',
				),
				'_pppd_code_refs'  => array(
					'type'       => 'array',
					'visibility' => 'internal',
				),
				'_pppd_test_refs'  => array(
					'type'       => 'array',
					'visibility' => 'internal',
				),
			),
		)
	);

	pppd_register_report_type(
		'frd',
		array(
			'label'                => __( 'Functional Requirements Document', 'pretty-professional-process-docs' ),
			'section_types'        => array( 'narrative', 'requirement', 'data-requirement', 'ui-rule', 'decision' ),
			'default_section_type' => 'narrative',
			'id_scheme'            => array(
				'prefix' => 'FR',
				'format' => '%s-%03d',
			),
		)
	);

	pppd_register_report_type(
		'user-access-model',
		array(
			'label'                => __( 'User Access Model', 'pretty-professional-process-docs' ),
			'section_types'        => array( 'narrative', 'requirement', 'data-requirement', 'ui-rule', 'decision' ),
			'default_section_type' => 'narrative',
			'id_scheme'            => array(
				'prefix' => 'UAM',
				'format' => '%s-%03d',
			),
		)
	);

	pppd_register_report_type(
		'change-order',
		array(
			'label'                => __( 'Change Order', 'pretty-professional-process-docs' ),
			'section_types'        => array( 'narrative', 'requirement', 'data-requirement', 'ui-rule', 'decision' ),
			'default_section_type' => 'requirement',
			'id_scheme'            => array(
				'prefix' => 'CO',
				'format' => '%s-%03d',
			),
		)
	);

	pppd_register_report_type(
		'content-strategy',
		array(
			'label'                => __( 'Content Strategy', 'pretty-professional-process-docs' ),
			'section_types'        => array( 'narrative', 'decision' ),
			'default_section_type' => 'narrative',
			'id_scheme'            => array(
				'prefix' => 'CS',
				'format' => '%s-%03d',
			),
		)
	);

	/**
	 * Register custom report types and section types.
	 *
	 * The plugin's primary extension point. Fires after the built-ins are
	 * registered, before terms/meta are synced in pppd_registry_apply().
	 *
	 * @param PPPD_Registry $registry The registry.
	 */
	do_action( 'pppd_register_types', pppd_registry() );
}

/**
 * Apply registry side effects: register declared field meta, sync taxonomy
 * terms, and validate ID-prefix uniqueness.
 *
 * Hooked to init at priority 20, after post types, taxonomies, and core meta
 * are registered (priority 10).
 *
 * @return void
 */
function pppd_registry_apply() {
	pppd_register_registry_fields();
	pppd_sync_registry_terms();
	pppd_validate_registry();
}

/**
 * Register post meta for section-type fields not already registered.
 *
 * Core fields (acceptance, refs, prompts) are registered in
 * pppd_register_meta() and skipped here; this is the path by which a
 * third-party section type gets REST-writable meta without touching core.
 *
 * @return void
 */
function pppd_register_registry_fields() {
	foreach ( pppd_registry()->get_section_types() as $type ) {
		if ( empty( $type['fields'] ) || ! is_array( $type['fields'] ) ) {
			continue;
		}

		foreach ( $type['fields'] as $meta_key => $field ) {
			$meta_key = (string) $meta_key;

			if ( '' === $meta_key || registered_meta_key_exists( 'post', $meta_key, 'pppd_section' ) ) {
				continue;
			}

			$field_type = isset( $field['type'] ) ? (string) $field['type'] : 'string';

			$args = array(
				'single'        => true,
				'auth_callback' => 'pppd_meta_auth_callback',
			);

			switch ( $field_type ) {
				case 'array':
					$args['type']              = 'array';
					$args['sanitize_callback'] = 'pppd_sanitize_string_array';
					$args['show_in_rest']      = pppd_string_array_rest_schema();
					break;
				case 'integer':
					$args['type']              = 'integer';
					$args['sanitize_callback'] = 'absint';
					$args['show_in_rest']      = true;
					break;
				default:
					$args['type']              = 'string';
					$args['sanitize_callback'] = 'sanitize_textarea_field';
					$args['show_in_rest']      = true;
					break;
			}

			if ( isset( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
				$args['sanitize_callback'] = $field['sanitize_callback'];
			}

			register_post_meta( 'pppd_section', $meta_key, $args );
		}
	}
}

/**
 * Ensure a pppd_section_type term exists for every registered section type
 * and a pppd_report_type term for every registered report type.
 *
 * Idempotent and hash-gated: runs only when the set of registered slugs
 * changes, so steady-state requests never hit the terms tables. Existing term
 * slugs are never modified.
 *
 * @return void
 */
function pppd_sync_registry_terms() {
	$registry = pppd_registry();

	$signature = md5(
		(string) wp_json_encode(
			array(
				'section' => array_keys( $registry->get_section_types() ),
				'report'  => array_keys( $registry->get_report_types() ),
			)
		)
	);

	if ( get_option( 'pppd_registered_type_hash' ) === $signature ) {
		return;
	}

	foreach ( $registry->get_section_types() as $slug => $type ) {
		if ( ! term_exists( $slug, 'pppd_section_type' ) ) {
			wp_insert_term( (string) $type['label'], 'pppd_section_type', array( 'slug' => $slug ) );
		}
	}

	foreach ( $registry->get_report_types() as $slug => $type ) {
		if ( ! term_exists( $slug, 'pppd_report_type' ) ) {
			wp_insert_term( (string) $type['label'], 'pppd_report_type', array( 'slug' => $slug ) );
		}
	}

	update_option( 'pppd_registered_type_hash', $signature );
}

/**
 * Warn (via _doing_it_wrong) when two ID-bearing section types offered by the
 * same report type resolve to the same ID prefix — they would issue colliding
 * item IDs from separate counters.
 *
 * @return void
 */
function pppd_validate_registry() {
	$registry = pppd_registry();

	foreach ( $registry->get_report_types() as $report_slug => $report_type ) {
		$prefixes = array();

		foreach ( (array) $report_type['section_types'] as $section_slug ) {
			$section_type = $registry->get_section_type( (string) $section_slug );

			if ( null === $section_type || empty( $section_type['has_ids'] ) ) {
				continue;
			}

			$prefix = '' !== (string) $section_type['id_prefix']
				? (string) $section_type['id_prefix']
				: (string) $report_type['id_scheme']['prefix'];

			if ( isset( $prefixes[ $prefix ] ) ) {
				_doing_it_wrong(
					'pppd_register_report_type',
					sprintf(
						/* translators: 1: report type slug, 2: ID prefix, 3: first section type slug, 4: second section type slug. */
						esc_html__( 'Report type "%1$s" offers two ID-bearing section types ("%3$s" and "%4$s") that both resolve to the ID prefix "%2$s"; their independent counters would issue colliding item IDs.', 'pretty-professional-process-docs' ),
						esc_html( $report_slug ),
						esc_html( $prefix ),
						esc_html( $prefixes[ $prefix ] ),
						esc_html( (string) $section_slug )
					),
					'0.2.0'
				);
			} else {
				$prefixes[ $prefix ] = (string) $section_slug;
			}
		}
	}
}

/**
 * Get a report's report-type slug.
 *
 * Reports without a pppd_report_type term default to 'frd' — every report
 * created before the registry existed is an FRD.
 *
 * @param WP_Post|int $report Report post or ID.
 * @return string
 */
function pppd_get_report_type( $report ) {
	$slug = pppd_get_section_term_slug( $report, 'pppd_report_type' );

	return '' !== $slug ? $slug : 'frd';
}

/**
 * Get the registered arguments of a report's report type.
 *
 * Falls back to the frd registration when the report's term has no
 * registration (e.g. the registering plugin was deactivated).
 *
 * @param WP_Post|int $report Report post or ID.
 * @return array<string, mixed>|null
 */
function pppd_get_report_type_object( $report ) {
	$type = pppd_registry()->get_report_type( pppd_get_report_type( $report ) );

	if ( null === $type ) {
		$type = pppd_registry()->get_report_type( 'frd' );
	}

	return $type;
}

/**
 * Get a section's section-type slug ('' when untyped).
 *
 * @param WP_Post|int $section Section post or ID.
 * @return string
 */
function pppd_get_section_type_slug( $section ) {
	return pppd_get_section_term_slug( $section, 'pppd_section_type' );
}

/**
 * Whether a section's type declares a boolean feature flag.
 *
 * @param WP_Post|int $section Section post or ID.
 * @param string      $feature Flag name (is_item, has_ids, traceable, ...).
 * @return bool
 */
function pppd_section_type_supports( $section, $feature ) {
	return pppd_registry()->section_type_supports( pppd_get_section_type_slug( $section ), $feature );
}

/**
 * Get the meta partial path a section's type declares, or '' when none.
 *
 * @param WP_Post|int $section Section post or ID.
 * @return string Absolute path of an existing file, or ''.
 */
function pppd_get_section_meta_partial( $section ) {
	$slug    = pppd_get_section_type_slug( $section );
	$type    = pppd_registry()->get_section_type( $slug );
	$partial = ( null !== $type ) ? (string) $type['meta_partial'] : '';

	/**
	 * Filter the template partial rendered after a section's body.
	 *
	 * @param string      $partial Absolute path ('' for none).
	 * @param WP_Post|int $section Section post or ID.
	 * @param string      $slug    Section type slug.
	 */
	$partial = (string) apply_filters( 'pppd_section_meta_partial', $partial, $section, $slug );

	return ( '' !== $partial && file_exists( $partial ) ) ? $partial : '';
}

/**
 * Get the meta keys a section type declares as internal-only.
 *
 * Internal fields must never render on the client-facing view or in client
 * exports. (Enforcement lands with the client view; this is the source of
 * truth it reads.)
 *
 * @param string $type_slug Section type slug.
 * @return string[]
 */
function pppd_get_internal_field_keys( $type_slug ) {
	$type = pppd_registry()->get_section_type( $type_slug );

	if ( null === $type || empty( $type['fields'] ) ) {
		return array();
	}

	$keys = array();

	foreach ( (array) $type['fields'] as $meta_key => $field ) {
		if ( isset( $field['visibility'] ) && 'internal' === $field['visibility'] ) {
			$keys[] = (string) $meta_key;
		}
	}

	return $keys;
}
