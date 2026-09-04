<?php
/**
 * MenuCraft REST controller.
 *
 * Routes are registered under /wp-json/menucraft/v1/*. Term-like resources
 * (categories, tags) share identical shape and validation, so registration
 * loops over the resource registry and all handlers delegate to shared
 * `handle_*` methods. Resources with different fields (allergens, items,
 * offers) will need dedicated handlers when added.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
class MenuCraft_REST {

	/**
	 * Namespace for all MenuCraft REST routes.
	 */
	const REST_NAMESPACE = 'menucraft/v1';

	/**
	 * Term-like resources — same schema, same handler logic.
	 *
	 * `slug_prefix` becomes the leading segment of generated slugs
	 * (e.g. "categories-coffee").
	 *
	 * @var array<string,array<string,string>>
	 */
	private static $term_resources = array(
		'categories' => array(
			'repo'        => 'MenuCraft_Category_Repository',
			'slug_prefix' => 'categories',
			'label'       => 'category',
		),
		'tags'       => array(
			'repo'        => 'MenuCraft_Tag_Repository',
			'slug_prefix' => 'tags',
			'label'       => 'tag',
		),
	);

	/**
	 * Register every plugin-owned route. Hooked to `rest_api_init`.
	 */
	public static function register_routes() {
		foreach ( array_keys( self::$term_resources ) as $resource ) {
			self::register_term_routes( $resource );
		}
		self::register_allergen_routes();
		self::register_item_routes();
		self::register_offer_routes();
		self::register_options_routes();
	}

	/**
	 * Options that admins may read/write via REST. Any key not listed here
	 * is silently ignored on write and omitted on read.
	 *
	 * Each entry: 'default' + 'sanitize' callable.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function editable_options() {
		return array(
			'currency' => array(
				'default'  => '€',
				'sanitize' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Register /options routes.
	 */
	private static function register_options_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/options',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_options' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_options' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);
	}

	/**
	 * GET /options — return the current hash of all editable options.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_options() {
		$out = array();
		foreach ( self::editable_options() as $key => $spec ) {
			$out[ $key ] = MenuCraft_Options::get( $key, $spec['default'] );
		}
		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * POST /options — partial update. Body is a hash of option key → value.
	 * Unknown keys are ignored; every value is sanitized per the whitelist.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function save_options( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		$editable = self::editable_options();

		foreach ( $editable as $key => $spec ) {
			if ( ! array_key_exists( $key, $body ) ) {
				continue;
			}
			$sanitized = call_user_func( $spec['sanitize'], $body[ $key ] );
			MenuCraft_Options::update( $key, $sanitized );
		}

		return self::list_options();
	}

	/**
	 * Register /allergens routes. Allergens have a different shape
	 * (code-based, no slug/color/media/parent) so they use dedicated
	 * handlers rather than the term_resources loop.
	 */
	private static function register_allergen_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/allergens',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_allergens' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_allergen' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
					'args'                => self::allergen_args( true ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/allergens/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_allergen' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_allergen' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
					'args'                => self::allergen_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_allergen' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);
	}

	/**
	 * Register the five REST routes (list, create, get, update, delete)
	 * for a term-like resource.
	 *
	 * @param string $resource Resource key from self::$term_resources.
	 */
	private static function register_term_routes( $resource ) {
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . $resource,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function () use ( $resource ) {
						return self::handle_list( $resource );
					},
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => function ( $req ) use ( $resource ) {
						return self::handle_create( $req, $resource );
					},
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
					'args'                => self::term_args( true, $resource ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/' . $resource . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( $req ) use ( $resource ) {
						return self::handle_get( $req, $resource );
					},
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => function ( $req ) use ( $resource ) {
						return self::handle_update( $req, $resource );
					},
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
					'args'                => self::term_args( false, $resource ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => function ( $req ) use ( $resource ) {
						return self::handle_delete( $req, $resource );
					},
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);
	}

	/**
	 * Only admins may write MenuCraft data (for now).
	 *
	 * @return bool
	 */
	public static function permission_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Argument schema for term create/update.
	 *
	 * @param bool        $name_required Set false for updates so partial payloads work.
	 * @param string|null $resource      Resource key — categories may expose extra args (is_default) that tags don't.
	 * @return array<string,array<string,mixed>>
	 */
	private static function term_args( $name_required, $resource = null ) {
		$args = array(
			'name'        => array(
				'required'          => (bool) $name_required,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'color'       => array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_hex_color' ),
			),
			'media_id'    => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'sort_order'  => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'is_active'   => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
		);

		if ( 'categories' === $resource ) {
			$args['is_default'] = array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			);
		}

		if ( $name_required ) {
			$args['description']['default'] = '';
			$args['color']['default']       = '';
			$args['media_id']['default']    = 0;
			$args['sort_order']['default']  = 0;
			$args['is_active']['default']   = true;
			if ( isset( $args['is_default'] ) ) {
				$args['is_default']['default'] = false;
			}
		}

		return $args;
	}

	/**
	 * Hex color validator that never returns null (REST args prefer scalars).
	 *
	 * @param string $value Incoming value.
	 * @return string Sanitized "#rrggbb"/"#rgb" or empty string.
	 */
	public static function sanitize_hex_color( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		return preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value ) ? $value : '';
	}

	/**
	 * GET /{resource} — list.
	 *
	 * @param string $resource Resource key.
	 * @return WP_REST_Response
	 */
	private static function handle_list( $resource ) {
		$repo = self::$term_resources[ $resource ]['repo'];
		$rows = call_user_func( array( $repo, 'all' ) );
		$rows = array_map( array( __CLASS__, 'present' ), $rows );
		return new WP_REST_Response( $rows, 200 );
	}

	/**
	 * GET /{resource}/{id} — fetch one.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $resource Resource key.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function handle_get( WP_REST_Request $request, $resource ) {
		$repo   = self::$term_resources[ $resource ]['repo'];
		$id     = (int) $request->get_param( 'id' );
		$entity = call_user_func( array( $repo, 'find' ), $id );

		if ( null === $entity ) {
			return self::not_found( $resource );
		}

		return new WP_REST_Response( self::present( $entity ), 200 );
	}

	/**
	 * POST /{resource} — create.
	 *
	 * @param WP_REST_Request $request Sanitized REST request.
	 * @param string          $resource Resource key.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function handle_create( WP_REST_Request $request, $resource ) {
		$config = self::$term_resources[ $resource ];
		$repo   = $config['repo'];

		$name = trim( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return new WP_Error( 'menucraft_invalid_name', __( 'Name is required.', 'menucraft' ), array( 'status' => 400 ) );
		}

		$validation = self::validate_relations( $request, $repo, 0 );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$slug = MenuCraft_Slug::generate(
			$config['slug_prefix'],
			$name,
			array( $repo, 'slug_exists' )
		);

		$insert_data = array(
			'name'        => $name,
			'slug'        => $slug,
			'description' => (string) $request->get_param( 'description' ),
			'color'       => (string) $request->get_param( 'color' ),
			'media_id'    => (int) $request->get_param( 'media_id' ),
			'sort_order'  => (int) $request->get_param( 'sort_order' ),
			'is_active'   => (bool) $request->get_param( 'is_active' ) ? 1 : 0,
		);
		// Extra category-only field: pass through only when the resource
		// actually exposes it in term_args().
		if ( 'categories' === $resource && $request->has_param( 'is_default' ) ) {
			$insert_data['is_default'] = (bool) $request->get_param( 'is_default' ) ? 1 : 0;
		}

		$entity = call_user_func( array( $repo, 'insert' ), $insert_data );

		if ( null === $entity ) {
			return new WP_Error( 'menucraft_insert_failed', __( 'Could not save.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( self::present( $entity ), 201 );
	}

	/**
	 * PUT/PATCH /{resource}/{id} — update.
	 *
	 * @param WP_REST_Request $request Sanitized request.
	 * @param string          $resource Resource key.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function handle_update( WP_REST_Request $request, $resource ) {
		$config = self::$term_resources[ $resource ];
		$repo   = $config['repo'];
		$id     = (int) $request->get_param( 'id' );

		$existing = call_user_func( array( $repo, 'find' ), $id );
		if ( null === $existing ) {
			return self::not_found( $resource );
		}

		$validation = self::validate_relations( $request, $repo, $id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$data = array();

		if ( $request->has_param( 'name' ) ) {
			$name = trim( (string) $request->get_param( 'name' ) );
			if ( '' === $name ) {
				return new WP_Error( 'menucraft_invalid_name', __( 'Name is required.', 'menucraft' ), array( 'status' => 400 ) );
			}
			$data['name'] = $name;

			// Regenerate slug from the new name (rule B — slug always follows name).
			$data['slug'] = MenuCraft_Slug::generate(
				$config['slug_prefix'],
				$name,
				function ( $candidate ) use ( $repo, $id ) {
					return call_user_func( array( $repo, 'slug_exists' ), $candidate, $id );
				}
			);
		}

		foreach ( array( 'description', 'color', 'media_id', 'sort_order' ) as $field ) {
			if ( $request->has_param( $field ) ) {
				$data[ $field ] = $request->get_param( $field );
			}
		}

		if ( $request->has_param( 'is_active' ) ) {
			$data['is_active'] = (bool) $request->get_param( 'is_active' ) ? 1 : 0;
		}
		if ( 'categories' === $resource && $request->has_param( 'is_default' ) ) {
			$data['is_default'] = (bool) $request->get_param( 'is_default' ) ? 1 : 0;
		}

		$updated = call_user_func( array( $repo, 'update' ), $id, $data );
		if ( null === $updated ) {
			return new WP_Error( 'menucraft_update_failed', __( 'Could not update.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( self::present( $updated ), 200 );
	}

	/**
	 * DELETE /{resource}/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $resource Resource key.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function handle_delete( WP_REST_Request $request, $resource ) {
		$repo = self::$term_resources[ $resource ]['repo'];
		$id   = (int) $request->get_param( 'id' );

		$existing = call_user_func( array( $repo, 'find' ), $id );
		if ( null === $existing ) {
			return self::not_found( $resource );
		}

		$ok = call_user_func( array( $repo, 'delete' ), $id );
		if ( ! $ok ) {
			return new WP_Error( 'menucraft_delete_failed', __( 'Could not delete.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'deleted'  => true,
				'id'       => $id,
				'previous' => self::present( $existing ),
			),
			200
		);
	}

	/**
	 * Validate that media_id (if provided and > 0) references an image
	 * attachment. Kept for term-like resources that carry an image.
	 *
	 * @param WP_REST_Request $request  Request.
	 * @param string          $repo     Fully qualified repository class name.
	 * @param int             $self_id  Reserved for future relation checks.
	 * @return true|WP_Error
	 */
	private static function validate_relations( WP_REST_Request $request, $repo, $self_id ) {
		unset( $repo, $self_id );

		if ( $request->has_param( 'media_id' ) ) {
			$media_id = (int) $request->get_param( 'media_id' );
			if ( $media_id > 0 && ! wp_attachment_is_image( $media_id ) ) {
				return new WP_Error( 'menucraft_invalid_media', __( 'Selected media is not an image.', 'menucraft' ), array( 'status' => 400 ) );
			}
		}

		return true;
	}

	/**
	 * Build a "not found" WP_Error.
	 *
	 * @param string $resource Resource key.
	 * @return WP_Error
	 */
	private static function not_found( $resource ) {
		unset( $resource );
		return new WP_Error( 'menucraft_not_found', __( 'Not found.', 'menucraft' ), array( 'status' => 404 ) );
	}

	/**
	 * Add presentation-only fields to an entity array.
	 *
	 * @param array<string,mixed>|null $entity Hydrated entity or null.
	 * @return array<string,mixed>|null
	 */
	private static function present( $entity ) {
		if ( ! is_array( $entity ) ) {
			return $entity;
		}

		$entity['media_url'] = $entity['media_id'] > 0
			? wp_get_attachment_image_url( $entity['media_id'], 'thumbnail' )
			: null;

		return $entity;
	}

	// ================================================= Allergen handlers ==

	/**
	 * Argument schema for allergen create/update.
	 *
	 * @param bool $required_fields Set false for updates so partial payloads work.
	 * @return array<string,array<string,mixed>>
	 */
	private static function allergen_args( $required_fields ) {
		$args = array(
			'code'        => array(
				'required'          => (bool) $required_fields,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'name'        => array(
				'required'          => (bool) $required_fields,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'sort_order'  => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'is_active'   => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
		);

		if ( $required_fields ) {
			$args['description']['default'] = '';
			$args['sort_order']['default']  = 0;
			$args['is_active']['default']   = true;
		}

		return $args;
	}

	/**
	 * GET /allergens.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_allergens() {
		$rows = MenuCraft_Allergen_Repository::all();
		return new WP_REST_Response( $rows, 200 );
	}

	/**
	 * GET /allergens/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_allergen( WP_REST_Request $request ) {
		$entity = MenuCraft_Allergen_Repository::find( (int) $request->get_param( 'id' ) );
		if ( null === $entity ) {
			return self::not_found( 'allergen' );
		}
		return new WP_REST_Response( $entity, 200 );
	}

	/**
	 * POST /allergens.
	 *
	 * @param WP_REST_Request $request Sanitized request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_allergen( WP_REST_Request $request ) {
		$code = trim( (string) $request->get_param( 'code' ) );
		$name = trim( (string) $request->get_param( 'name' ) );

		if ( '' === $code ) {
			return new WP_Error( 'menucraft_invalid_code', __( 'Code is required.', 'menucraft' ), array( 'status' => 400 ) );
		}
		if ( '' === $name ) {
			return new WP_Error( 'menucraft_invalid_name', __( 'Name is required.', 'menucraft' ), array( 'status' => 400 ) );
		}
		if ( MenuCraft_Allergen_Repository::code_exists( $code ) ) {
			return new WP_Error( 'menucraft_duplicate_code', __( 'This code is already in use.', 'menucraft' ), array( 'status' => 409 ) );
		}

		$entity = MenuCraft_Allergen_Repository::insert(
			array(
				'code'        => $code,
				'name'        => $name,
				'description' => (string) $request->get_param( 'description' ),
				'sort_order'  => (int) $request->get_param( 'sort_order' ),
				'is_active'   => (bool) $request->get_param( 'is_active' ) ? 1 : 0,
			)
		);

		if ( null === $entity ) {
			return new WP_Error( 'menucraft_insert_failed', __( 'Could not save.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $entity, 201 );
	}

	/**
	 * PUT/PATCH /allergens/{id}.
	 *
	 * @param WP_REST_Request $request Sanitized request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_allergen( WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$existing = MenuCraft_Allergen_Repository::find( $id );
		if ( null === $existing ) {
			return self::not_found( 'allergen' );
		}

		$data = array();

		if ( $request->has_param( 'code' ) ) {
			$code = trim( (string) $request->get_param( 'code' ) );
			if ( '' === $code ) {
				return new WP_Error( 'menucraft_invalid_code', __( 'Code is required.', 'menucraft' ), array( 'status' => 400 ) );
			}
			if ( MenuCraft_Allergen_Repository::code_exists( $code, $id ) ) {
				return new WP_Error( 'menucraft_duplicate_code', __( 'This code is already in use.', 'menucraft' ), array( 'status' => 409 ) );
			}
			$data['code'] = $code;
		}

		if ( $request->has_param( 'name' ) ) {
			$name = trim( (string) $request->get_param( 'name' ) );
			if ( '' === $name ) {
				return new WP_Error( 'menucraft_invalid_name', __( 'Name is required.', 'menucraft' ), array( 'status' => 400 ) );
			}
			$data['name'] = $name;
		}

		foreach ( array( 'description', 'sort_order' ) as $field ) {
			if ( $request->has_param( $field ) ) {
				$data[ $field ] = $request->get_param( $field );
			}
		}

		if ( $request->has_param( 'is_active' ) ) {
			$data['is_active'] = (bool) $request->get_param( 'is_active' ) ? 1 : 0;
		}

		$updated = MenuCraft_Allergen_Repository::update( $id, $data );
		if ( null === $updated ) {
			return new WP_Error( 'menucraft_update_failed', __( 'Could not update.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $updated, 200 );
	}

	/**
	 * DELETE /allergens/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_allergen( WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$existing = MenuCraft_Allergen_Repository::find( $id );
		if ( null === $existing ) {
			return self::not_found( 'allergen' );
		}

		$ok = MenuCraft_Allergen_Repository::delete( $id );
		if ( ! $ok ) {
			return new WP_Error( 'menucraft_delete_failed', __( 'Could not delete.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'deleted'  => true,
				'id'       => $id,
				'previous' => $existing,
			),
			200
		);
	}

	// ===================================================== Item handlers ==

	/**
	 * Register /items routes.
	 */
	private static function register_item_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/items',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_items' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_item' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
					'args'                => self::item_args( true ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/items/bulk-edit',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'bulk_edit_items' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/items/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_item' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_item' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
					'args'                => self::item_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_item' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);
	}

	/**
	 * Argument schema for item create/update.
	 *
	 * Arrays (relations, variants) are declared without strict item
	 * schemas so REST doesn't reject them; deeper validation happens in
	 * the handler.
	 *
	 * @param bool $name_required Set false for updates.
	 * @return array<string,array<string,mixed>>
	 */
	private static function item_args( $name_required ) {
		$args = array(
			'name'              => array(
				'required'          => (bool) $name_required,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description_short' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'description_long'  => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'price'             => array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_nullable_price' ),
			),
			'media_id'          => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'sort_order'        => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'is_active'         => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'category_ids'      => array(
				'type' => 'array',
			),
			'tag_ids'           => array(
				'type' => 'array',
			),
			'allergen_ids'      => array(
				'type' => 'array',
			),
			'variants'          => array(
				'type' => 'array',
			),
		);

		if ( $name_required ) {
			$args['description_short']['default'] = '';
			$args['description_long']['default']  = '';
			$args['media_id']['default']          = 0;
			$args['sort_order']['default']        = 0;
			$args['is_active']['default']         = true;
			$args['category_ids']['default']      = array();
			$args['tag_ids']['default']           = array();
			$args['allergen_ids']['default']      = array();
			$args['variants']['default']          = array();
		}

		return $args;
	}

	/**
	 * Sanitize price: NULL passes through, empty string → null,
	 * numbers → non-negative float.
	 *
	 * @param mixed $value Incoming value.
	 * @return float|null
	 */
	public static function sanitize_nullable_price( $value ) {
		if ( null === $value || '' === $value || 'null' === $value ) {
			return null;
		}
		$num = (float) $value;
		return $num < 0 ? 0.0 : $num;
	}

	/**
	 * GET /items.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_items() {
		$rows = MenuCraft_Item_Repository::all();
		$rows = array_map( array( __CLASS__, 'present' ), $rows );
		return new WP_REST_Response( $rows, 200 );
	}

	/**
	 * GET /items/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_item( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$entity = MenuCraft_Item_Repository::find( $id );
		if ( null === $entity ) {
			return self::not_found( 'item' );
		}
		return new WP_REST_Response( self::present( $entity ), 200 );
	}

	/**
	 * POST /items.
	 *
	 * @param WP_REST_Request $request Sanitized request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_item( WP_REST_Request $request ) {
		$name = trim( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return new WP_Error( 'menucraft_invalid_name', __( 'Name is required.', 'menucraft' ), array( 'status' => 400 ) );
		}

		$validation = self::validate_item_relations( $request );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$slug = MenuCraft_Slug::generate(
			'items',
			$name,
			array( 'MenuCraft_Item_Repository', 'slug_exists' )
		);

		$entity = MenuCraft_Item_Repository::insert(
			array(
				'name'              => $name,
				'slug'              => $slug,
				'description_short' => (string) $request->get_param( 'description_short' ),
				'description_long'  => (string) $request->get_param( 'description_long' ),
				'price'             => $request->has_param( 'price' ) ? $request->get_param( 'price' ) : null,
				'media_id'          => (int) $request->get_param( 'media_id' ),
				'sort_order'        => (int) $request->get_param( 'sort_order' ),
				'is_active'         => (bool) $request->get_param( 'is_active' ) ? 1 : 0,
				'category_ids'      => (array) $request->get_param( 'category_ids' ),
				'tag_ids'           => (array) $request->get_param( 'tag_ids' ),
				'allergen_ids'      => (array) $request->get_param( 'allergen_ids' ),
				'variants'          => (array) $request->get_param( 'variants' ),
			)
		);

		if ( null === $entity ) {
			return new WP_Error( 'menucraft_insert_failed', __( 'Could not save.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( self::present( $entity ), 201 );
	}

	/**
	 * PUT/PATCH /items/{id}.
	 *
	 * @param WP_REST_Request $request Sanitized request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_item( WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$existing = MenuCraft_Item_Repository::find( $id );
		if ( null === $existing ) {
			return self::not_found( 'item' );
		}

		$validation = self::validate_item_relations( $request );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$data = array();

		if ( $request->has_param( 'name' ) ) {
			$name = trim( (string) $request->get_param( 'name' ) );
			if ( '' === $name ) {
				return new WP_Error( 'menucraft_invalid_name', __( 'Name is required.', 'menucraft' ), array( 'status' => 400 ) );
			}
			$data['name'] = $name;

			$data['slug'] = MenuCraft_Slug::generate(
				'items',
				$name,
				function ( $candidate ) use ( $id ) {
					return MenuCraft_Item_Repository::slug_exists( $candidate, $id );
				}
			);
		}

		foreach ( array( 'description_short', 'description_long', 'media_id', 'sort_order' ) as $field ) {
			if ( $request->has_param( $field ) ) {
				$data[ $field ] = $request->get_param( $field );
			}
		}

		if ( $request->has_param( 'price' ) ) {
			$data['price'] = $request->get_param( 'price' );
		}

		if ( $request->has_param( 'is_active' ) ) {
			$data['is_active'] = (bool) $request->get_param( 'is_active' ) ? 1 : 0;
		}

		foreach ( array( 'category_ids', 'tag_ids', 'allergen_ids', 'variants' ) as $field ) {
			if ( $request->has_param( $field ) ) {
				$data[ $field ] = (array) $request->get_param( $field );
			}
		}

		if ( isset( $data['variants'] ) ) {
			$blocked = self::variants_blocked_by_offers( $id, $data['variants'] );
			if ( is_wp_error( $blocked ) ) {
				return $blocked;
			}
		}

		$updated = MenuCraft_Item_Repository::update( $id, $data );
		if ( null === $updated ) {
			return new WP_Error( 'menucraft_update_failed', __( 'Could not update.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( self::present( $updated ), 200 );
	}

	/**
	 * DELETE /items/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_item( WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$existing = MenuCraft_Item_Repository::find( $id );
		if ( null === $existing ) {
			return self::not_found( 'item' );
		}

		$blocking = MenuCraft_Offer_Repository::offers_using_item( $id );
		if ( ! empty( $blocking ) ) {
			return new WP_Error(
				'menucraft_item_in_offer',
				sprintf(
					/* translators: %s: comma-separated offer names */
					__( 'Cannot delete — this item is used in offer(s): %s.', 'menucraft' ),
					implode( ', ', array_values( $blocking ) )
				),
				array(
					'status' => 409,
					'offers' => $blocking,
				)
			);
		}

		$ok = MenuCraft_Item_Repository::delete( $id );
		if ( ! $ok ) {
			return new WP_Error( 'menucraft_delete_failed', __( 'Could not delete.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'deleted'  => true,
				'id'       => $id,
				'previous' => self::present( $existing ),
			),
			200
		);
	}

	/**
	 * POST /items/bulk-edit — apply the same set of operations to many items.
	 *
	 * @param WP_REST_Request $request Raw request (we read body ourselves
	 *                                 because operations shape is nested and
	 *                                 does not fit REST args nicely).
	 * @return WP_REST_Response|WP_Error
	 */
	public static function bulk_edit_items( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}

		$item_ids   = isset( $body['item_ids'] ) ? (array) $body['item_ids'] : array();
		$operations = isset( $body['operations'] ) ? (array) $body['operations'] : array();

		$item_ids = array_values( array_unique( array_filter( array_map( 'intval', $item_ids ) ) ) );
		if ( empty( $item_ids ) ) {
			return new WP_Error( 'menucraft_no_items', __( 'No items selected.', 'menucraft' ), array( 'status' => 400 ) );
		}

		$validation = self::validate_bulk_operations( $operations );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$sanitized = self::sanitize_bulk_operations( $operations );

		$updated = MenuCraft_Item_Repository::bulk_edit( $item_ids, $sanitized );
		$updated = array_map( array( __CLASS__, 'present' ), $updated );

		return new WP_REST_Response(
			array(
				'updated' => $updated,
				'count'   => count( $updated ),
			),
			200
		);
	}

	/**
	 * Validate the operations dictionary before applying.
	 *
	 * @param array<string,mixed> $operations Raw ops.
	 * @return true|WP_Error
	 */
	private static function validate_bulk_operations( array $operations ) {
		$relation_specs = array(
			'categories' => array( 'MenuCraft_Category_Repository', array( 'replace', 'add', 'remove' ), __( 'Unknown category.', 'menucraft' ) ),
			'tags'       => array( 'MenuCraft_Tag_Repository', array( 'replace', 'add', 'remove' ), __( 'Unknown tag.', 'menucraft' ) ),
			'allergens'  => array( 'MenuCraft_Allergen_Repository', array( 'replace', 'add', 'remove' ), __( 'Unknown allergen.', 'menucraft' ) ),
		);

		foreach ( $relation_specs as $key => $spec ) {
			if ( empty( $operations[ $key ] ) ) {
				continue;
			}
			$mode = isset( $operations[ $key ]['mode'] ) ? (string) $operations[ $key ]['mode'] : '';
			if ( ! in_array( $mode, $spec[1], true ) ) {
				return new WP_Error( 'menucraft_invalid_mode', __( 'Invalid mode for a relation operation.', 'menucraft' ), array( 'status' => 400 ) );
			}
			$ids = isset( $operations[ $key ]['ids'] ) ? (array) $operations[ $key ]['ids'] : array();
			foreach ( $ids as $raw_id ) {
				$id = (int) $raw_id;
				if ( $id <= 0 ) {
					continue;
				}
				if ( null === call_user_func( array( $spec[0], 'find' ), $id ) ) {
					return new WP_Error( 'menucraft_invalid_relation', $spec[2], array( 'status' => 400 ) );
				}
			}
		}

		if ( ! empty( $operations['base_price'] ) ) {
			$mode = isset( $operations['base_price']['mode'] ) ? (string) $operations['base_price']['mode'] : '';
			if ( ! in_array( $mode, array( 'replace', 'increase', 'decrease' ), true ) ) {
				return new WP_Error( 'menucraft_invalid_mode', __( 'Invalid base-price mode.', 'menucraft' ), array( 'status' => 400 ) );
			}
		}

		if ( ! empty( $operations['variant_prices'] ) ) {
			$mode = isset( $operations['variant_prices']['mode'] ) ? (string) $operations['variant_prices']['mode'] : '';
			if ( ! in_array( $mode, array( 'increase', 'decrease' ), true ) ) {
				return new WP_Error( 'menucraft_invalid_mode', __( 'Invalid variant-price mode.', 'menucraft' ), array( 'status' => 400 ) );
			}
		}

		return true;
	}

	/**
	 * Sanitize the operations dictionary — cast IDs to int, prices to
	 * non-negative float, is_active to bool.
	 *
	 * @param array<string,mixed> $operations Raw ops.
	 * @return array<string,mixed>
	 */
	private static function sanitize_bulk_operations( array $operations ) {
		$clean = array();

		foreach ( array( 'categories', 'tags', 'allergens' ) as $key ) {
			if ( empty( $operations[ $key ] ) ) {
				continue;
			}
			$clean[ $key ] = array(
				'mode' => (string) $operations[ $key ]['mode'],
				'ids'  => array_values( array_unique( array_filter( array_map( 'intval', (array) ( $operations[ $key ]['ids'] ?? array() ) ) ) ) ),
			);
		}

		if ( ! empty( $operations['base_price'] ) ) {
			$value = isset( $operations['base_price']['value'] ) ? (float) $operations['base_price']['value'] : 0.0;
			$clean['base_price'] = array(
				'mode'  => (string) $operations['base_price']['mode'],
				'value' => $value < 0 ? 0.0 : $value,
			);
		}

		if ( ! empty( $operations['variant_prices'] ) ) {
			$value = isset( $operations['variant_prices']['value'] ) ? (float) $operations['variant_prices']['value'] : 0.0;
			$clean['variant_prices'] = array(
				'mode'  => (string) $operations['variant_prices']['mode'],
				'value' => $value < 0 ? 0.0 : $value,
			);
		}

		if ( array_key_exists( 'is_active', $operations ) ) {
			$clean['is_active'] = (bool) $operations['is_active'];
		}

		return $clean;
	}

	/**
	 * Validate item-specific relations: media_id must be an image, and
	 * each ID in category_ids/tag_ids/allergen_ids must reference an
	 * existing row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	private static function validate_item_relations( WP_REST_Request $request ) {
		if ( $request->has_param( 'media_id' ) ) {
			$media_id = (int) $request->get_param( 'media_id' );
			if ( $media_id > 0 && ! wp_attachment_is_image( $media_id ) ) {
				return new WP_Error( 'menucraft_invalid_media', __( 'Selected media is not an image.', 'menucraft' ), array( 'status' => 400 ) );
			}
		}

		$relation_specs = array(
			'category_ids' => array( 'MenuCraft_Category_Repository', __( 'Unknown category.', 'menucraft' ) ),
			'tag_ids'      => array( 'MenuCraft_Tag_Repository', __( 'Unknown tag.', 'menucraft' ) ),
			'allergen_ids' => array( 'MenuCraft_Allergen_Repository', __( 'Unknown allergen.', 'menucraft' ) ),
		);

		foreach ( $relation_specs as $param => $spec ) {
			if ( ! $request->has_param( $param ) ) {
				continue;
			}
			$ids = (array) $request->get_param( $param );
			foreach ( $ids as $id ) {
				$int_id = (int) $id;
				if ( $int_id <= 0 ) {
					continue;
				}
				if ( null === call_user_func( array( $spec[0], 'find' ), $int_id ) ) {
					return new WP_Error( 'menucraft_invalid_relation', $spec[1], array( 'status' => 400 ) );
				}
			}
		}

		return true;
	}

	/**
	 * Refuse a variant-set update that would drop any variant currently
	 * referenced by an offer line. Returns the first offending variant as
	 * a WP_Error; otherwise true.
	 *
	 * @param int                            $item_id  Item ID being updated.
	 * @param array<int,array<string,mixed>> $variants Incoming variants payload.
	 * @return true|WP_Error
	 */
	private static function variants_blocked_by_offers( $item_id, array $variants ) {
		$to_remove = MenuCraft_Item_Repository::variants_to_remove( $item_id, $variants );
		foreach ( $to_remove as $vid ) {
			$blocking = MenuCraft_Offer_Repository::offers_using_variant( $vid );
			if ( ! empty( $blocking ) ) {
				return new WP_Error(
					'menucraft_variant_in_offer',
					sprintf(
						/* translators: %s: comma-separated offer names */
						__( 'Cannot remove a variant — it is used in offer(s): %s.', 'menucraft' ),
						implode( ', ', array_values( $blocking ) )
					),
					array(
						'status' => 409,
						'offers' => $blocking,
					)
				);
			}
		}
		return true;
	}

	// ==================================================== Offer handlers ==

	/**
	 * Register /offers routes.
	 */
	private static function register_offer_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/offers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_offers' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_offer' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
					'args'                => self::offer_args( true ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/offers/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_offer' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_offer' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
					'args'                => self::offer_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_offer' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);
	}

	/**
	 * Argument schema for offer create/update.
	 *
	 * items[] carries the offer line items; validated in the handler.
	 * Date fields accept HTML datetime-local ("Y-m-d\TH:i") or MySQL
	 * datetime, and are normalised to MySQL format by the sanitize helper.
	 *
	 * @param bool $required Set false for updates so partial payloads work.
	 * @return array<string,array<string,mixed>>
	 */
	private static function offer_args( $required ) {
		$args = array(
			'name'            => array(
				'required'          => (bool) $required,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'conditions_text' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'price'           => array(
				'required'          => (bool) $required,
				'sanitize_callback' => array( __CLASS__, 'sanitize_non_negative_price' ),
			),
			'media_id'        => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'valid_from'      => array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_datetime_or_null' ),
			),
			'valid_until'     => array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_datetime_or_null' ),
			),
			'sort_order'      => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'is_active'       => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'items'           => array(
				'type' => 'array',
			),
		);

		if ( $required ) {
			$args['description']['default']     = '';
			$args['conditions_text']['default'] = '';
			$args['media_id']['default']        = 0;
			$args['sort_order']['default']      = 0;
			$args['is_active']['default']       = true;
			$args['items']['default']           = array();
		}

		return $args;
	}

	/**
	 * Sanitize a required, non-negative price. Empty / non-numeric → 0.
	 *
	 * @param mixed $value Incoming value.
	 * @return float
	 */
	public static function sanitize_non_negative_price( $value ) {
		if ( null === $value || '' === $value ) {
			return 0.0;
		}
		$num = (float) $value;
		return $num < 0 ? 0.0 : $num;
	}

	/**
	 * Sanitize datetime input from HTML datetime-local ("Y-m-d\TH:i") or
	 * MySQL datetime ("Y-m-d H:i:s") to MySQL format. Empty → null.
	 *
	 * @param mixed $value Incoming value.
	 * @return string|null
	 */
	public static function sanitize_datetime_or_null( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$str = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $str ) {
			return null;
		}
		// datetime-local uses "T" as separator; normalise to space.
		$str  = str_replace( 'T', ' ', $str );
		$time = strtotime( $str );
		if ( false === $time ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', $time );
	}

	/**
	 * GET /offers.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_offers() {
		$rows = MenuCraft_Offer_Repository::all();
		$rows = array_map( array( __CLASS__, 'present' ), $rows );
		return new WP_REST_Response( $rows, 200 );
	}

	/**
	 * GET /offers/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_offer( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$entity = MenuCraft_Offer_Repository::find( $id );
		if ( null === $entity ) {
			return self::not_found( 'offer' );
		}
		return new WP_REST_Response( self::present( $entity ), 200 );
	}

	/**
	 * POST /offers.
	 *
	 * @param WP_REST_Request $request Sanitized request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_offer( WP_REST_Request $request ) {
		$name = trim( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return new WP_Error( 'menucraft_invalid_name', __( 'Name is required.', 'menucraft' ), array( 'status' => 400 ) );
		}

		$items      = self::read_offer_items( $request );
		$validation = self::validate_offer_payload( $request, $items );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$slug = MenuCraft_Slug::generate(
			'offers',
			$name,
			array( 'MenuCraft_Offer_Repository', 'slug_exists' )
		);

		$entity = MenuCraft_Offer_Repository::insert(
			array(
				'name'            => $name,
				'slug'            => $slug,
				'description'     => (string) $request->get_param( 'description' ),
				'conditions_text' => (string) $request->get_param( 'conditions_text' ),
				'price'           => (float) $request->get_param( 'price' ),
				'media_id'        => (int) $request->get_param( 'media_id' ),
				'valid_from'      => $request->get_param( 'valid_from' ),
				'valid_until'     => $request->get_param( 'valid_until' ),
				'sort_order'      => (int) $request->get_param( 'sort_order' ),
				'is_active'       => (bool) $request->get_param( 'is_active' ) ? 1 : 0,
				'items'           => $items,
			)
		);

		if ( null === $entity ) {
			return new WP_Error( 'menucraft_insert_failed', __( 'Could not save.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( self::present( $entity ), 201 );
	}

	/**
	 * PUT/PATCH /offers/{id}.
	 *
	 * @param WP_REST_Request $request Sanitized request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_offer( WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$existing = MenuCraft_Offer_Repository::find( $id );
		if ( null === $existing ) {
			return self::not_found( 'offer' );
		}

		$items      = self::read_offer_items( $request );
		$validation = self::validate_offer_payload( $request, $items );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$data = array();

		if ( $request->has_param( 'name' ) ) {
			$name = trim( (string) $request->get_param( 'name' ) );
			if ( '' === $name ) {
				return new WP_Error( 'menucraft_invalid_name', __( 'Name is required.', 'menucraft' ), array( 'status' => 400 ) );
			}
			$data['name'] = $name;
			$data['slug'] = MenuCraft_Slug::generate(
				'offers',
				$name,
				function ( $candidate ) use ( $id ) {
					return MenuCraft_Offer_Repository::slug_exists( $candidate, $id );
				}
			);
		}

		foreach ( array( 'description', 'conditions_text', 'media_id', 'sort_order', 'valid_from', 'valid_until' ) as $field ) {
			if ( $request->has_param( $field ) ) {
				$data[ $field ] = $request->get_param( $field );
			}
		}

		if ( $request->has_param( 'price' ) ) {
			$data['price'] = (float) $request->get_param( 'price' );
		}

		if ( $request->has_param( 'is_active' ) ) {
			$data['is_active'] = (bool) $request->get_param( 'is_active' ) ? 1 : 0;
		}

		// Only rewrite the offer_items list when the payload actually
		// carried an items key — a bare update (e.g. toggling is_active)
		// must not wipe existing lines.
		$body = $request->get_json_params();
		if ( is_array( $body ) && array_key_exists( 'items', $body ) ) {
			$data['items'] = $items;
		}

		$updated = MenuCraft_Offer_Repository::update( $id, $data );
		if ( null === $updated ) {
			return new WP_Error( 'menucraft_update_failed', __( 'Could not update.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( self::present( $updated ), 200 );
	}

	/**
	 * DELETE /offers/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_offer( WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$existing = MenuCraft_Offer_Repository::find( $id );
		if ( null === $existing ) {
			return self::not_found( 'offer' );
		}

		$ok = MenuCraft_Offer_Repository::delete( $id );
		if ( ! $ok ) {
			return new WP_Error( 'menucraft_delete_failed', __( 'Could not delete.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'deleted'  => true,
				'id'       => $id,
				'previous' => self::present( $existing ),
			),
			200
		);
	}

	/**
	 * Pull the offer_items list straight from the JSON body. WP's REST
	 * arg pipeline was quietly dropping nested-object arrays for us in
	 * some setups, so we bypass it for this one field and coerce every
	 * line to an associative array up-front.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<int,array<string,mixed>>
	 */
	private static function read_offer_items( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( is_array( $body ) && isset( $body['items'] ) && is_array( $body['items'] ) ) {
			$raw = $body['items'];
		} else {
			$raw = $request->get_param( 'items' );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $line ) {
			if ( is_object( $line ) ) {
				$line = (array) $line;
			}
			if ( is_array( $line ) ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * Validate an offer payload: image, date order, and every line item.
	 * If a line's item has variants, variant_id is mandatory and must
	 * belong to that item; if it has none, variant_id must be omitted.
	 *
	 * @param WP_REST_Request                $request Request.
	 * @param array<int,array<string,mixed>> $items   Line items already
	 *                                                normalised by
	 *                                                read_offer_items().
	 * @return true|WP_Error
	 */
	private static function validate_offer_payload( WP_REST_Request $request, array $items ) {
		if ( $request->has_param( 'media_id' ) ) {
			$media_id = (int) $request->get_param( 'media_id' );
			if ( $media_id > 0 && ! wp_attachment_is_image( $media_id ) ) {
				return new WP_Error( 'menucraft_invalid_media', __( 'Selected media is not an image.', 'menucraft' ), array( 'status' => 400 ) );
			}
		}

		$from  = $request->has_param( 'valid_from' ) ? $request->get_param( 'valid_from' ) : null;
		$until = $request->has_param( 'valid_until' ) ? $request->get_param( 'valid_until' ) : null;
		if ( $from && $until && strtotime( $until ) < strtotime( $from ) ) {
			return new WP_Error( 'menucraft_invalid_dates', __( 'Valid-until must be on or after valid-from.', 'menucraft' ), array( 'status' => 400 ) );
		}

		foreach ( $items as $line ) {
			$item_id = isset( $line['item_id'] ) ? (int) $line['item_id'] : 0;
			if ( $item_id <= 0 ) {
				return new WP_Error( 'menucraft_invalid_line', __( 'Each offer line needs an item.', 'menucraft' ), array( 'status' => 400 ) );
			}

			$item = MenuCraft_Item_Repository::find( $item_id );
			if ( null === $item ) {
				return new WP_Error( 'menucraft_unknown_item', __( 'Unknown item in offer.', 'menucraft' ), array( 'status' => 400 ) );
			}

			$variant_id      = isset( $line['variant_id'] ) && $line['variant_id'] ? (int) $line['variant_id'] : 0;
			$has_variants    = ! empty( $item['variants'] );
			$valid_variants  = array();
			if ( $has_variants ) {
				foreach ( $item['variants'] as $v ) {
					$valid_variants[ (int) $v['id'] ] = true;
				}
			}

			if ( $has_variants && $variant_id <= 0 ) {
				return new WP_Error(
					'menucraft_variant_required',
					sprintf(
						/* translators: %s: item name */
						__( 'Pick a variant for "%s".', 'menucraft' ),
						$item['name']
					),
					array( 'status' => 400 )
				);
			}

			if ( ! $has_variants && $variant_id > 0 ) {
				return new WP_Error(
					'menucraft_no_variants',
					sprintf(
						/* translators: %s: item name */
						__( '"%s" has no variants — remove the variant selection.', 'menucraft' ),
						$item['name']
					),
					array( 'status' => 400 )
				);
			}

			if ( $variant_id > 0 && ! isset( $valid_variants[ $variant_id ] ) ) {
				return new WP_Error(
					'menucraft_variant_mismatch',
					sprintf(
						/* translators: %s: item name */
						__( 'Selected variant does not belong to "%s".', 'menucraft' ),
						$item['name']
					),
					array( 'status' => 400 )
				);
			}

			if ( isset( $line['quantity'] ) && (int) $line['quantity'] < 1 ) {
				return new WP_Error( 'menucraft_invalid_quantity', __( 'Quantity must be at least 1.', 'menucraft' ), array( 'status' => 400 ) );
			}
		}

		return true;
	}
}
