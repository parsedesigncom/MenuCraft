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
					'args'                => self::term_args( true ),
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
					'args'                => self::term_args( false ),
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
	 * @param bool $name_required Set false for updates so partial payloads work.
	 * @return array<string,array<string,mixed>>
	 */
	private static function term_args( $name_required ) {
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

		if ( $name_required ) {
			$args['description']['default'] = '';
			$args['color']['default']       = '';
			$args['media_id']['default']    = 0;
			$args['sort_order']['default']  = 0;
			$args['is_active']['default']   = true;
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

		$entity = call_user_func(
			array( $repo, 'insert' ),
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => (string) $request->get_param( 'description' ),
				'color'       => (string) $request->get_param( 'color' ),
				'media_id'    => (int) $request->get_param( 'media_id' ),
				'sort_order'  => (int) $request->get_param( 'sort_order' ),
				'is_active'   => (bool) $request->get_param( 'is_active' ) ? 1 : 0,
			)
		);

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
}
