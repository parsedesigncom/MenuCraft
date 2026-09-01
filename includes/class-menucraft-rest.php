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
			'parent_id'   => array(
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
			$args['parent_id']['default']   = 0;
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
				'parent_id'   => (int) $request->get_param( 'parent_id' ),
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

		foreach ( array( 'description', 'color', 'media_id', 'parent_id', 'sort_order' ) as $field ) {
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
	 * Validate parent_id (must exist, may not be self) and media_id
	 * (must be an image attachment) when the payload provides them.
	 *
	 * @param WP_REST_Request $request  Request.
	 * @param string          $repo     Fully qualified repository class name.
	 * @param int             $self_id  When updating, disallow parent = self.
	 * @return true|WP_Error
	 */
	private static function validate_relations( WP_REST_Request $request, $repo, $self_id ) {
		if ( $request->has_param( 'parent_id' ) ) {
			$parent_id = (int) $request->get_param( 'parent_id' );
			if ( $parent_id > 0 ) {
				if ( $parent_id === (int) $self_id ) {
					return new WP_Error( 'menucraft_invalid_parent', __( 'A record cannot be its own parent.', 'menucraft' ), array( 'status' => 400 ) );
				}
				if ( null === call_user_func( array( $repo, 'find' ), $parent_id ) ) {
					return new WP_Error( 'menucraft_invalid_parent', __( 'Parent does not exist.', 'menucraft' ), array( 'status' => 400 ) );
				}
			}
		}

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
}
