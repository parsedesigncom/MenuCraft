<?php
/**
 * MenuCraft REST controller.
 *
 * Registers routes under /wp-json/menucraft/v1/*. Additional resources
 * (tags, allergens, items, offers) will register more routes here or in
 * dedicated controllers once the surface grows.
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
	 * Register every plugin-owned route. Hooked to `rest_api_init`.
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_categories' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_category' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
					'args'                => self::category_args( true ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/categories/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_category' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_category' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
					'args'                => self::category_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_category' ),
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
	 * Argument schema for category create/update.
	 *
	 * @param bool $name_required Set false for updates so partial payloads work.
	 * @return array<string,array<string,mixed>>
	 */
	private static function category_args( $name_required ) {
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
			// On create we want sensible defaults so unset fields don't error.
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
	 * GET /menucraft/v1/categories — list all categories.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_categories() {
		$rows = MenuCraft_Category_Repository::all();
		$rows = array_map( array( __CLASS__, 'present' ), $rows );
		return new WP_REST_Response( $rows, 200 );
	}

	/**
	 * GET /menucraft/v1/categories/{id} — fetch one category.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_category( WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$category = MenuCraft_Category_Repository::find( $id );

		if ( null === $category ) {
			return new WP_Error( 'menucraft_not_found', __( 'Category not found.', 'menucraft' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( self::present( $category ), 200 );
	}

	/**
	 * POST /menucraft/v1/categories — create a category.
	 *
	 * @param WP_REST_Request $request Sanitized REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_category( WP_REST_Request $request ) {
		$name = trim( (string) $request->get_param( 'name' ) );

		if ( '' === $name ) {
			return new WP_Error( 'menucraft_invalid_name', __( 'Name is required.', 'menucraft' ), array( 'status' => 400 ) );
		}

		$validation = self::validate_relations( $request );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$slug = MenuCraft_Slug::generate(
			'categories',
			$name,
			array( 'MenuCraft_Category_Repository', 'slug_exists' )
		);

		$category = MenuCraft_Category_Repository::insert(
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

		if ( null === $category ) {
			return new WP_Error( 'menucraft_insert_failed', __( 'Could not save the category.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( self::present( $category ), 201 );
	}

	/**
	 * PUT/PATCH /menucraft/v1/categories/{id} — update a category.
	 *
	 * @param WP_REST_Request $request Sanitized request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_category( WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$existing = MenuCraft_Category_Repository::find( $id );

		if ( null === $existing ) {
			return new WP_Error( 'menucraft_not_found', __( 'Category not found.', 'menucraft' ), array( 'status' => 404 ) );
		}

		$validation = self::validate_relations( $request, $id );
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
				'categories',
				$name,
				function ( $candidate ) use ( $id ) {
					return MenuCraft_Category_Repository::slug_exists( $candidate, $id );
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

		$updated = MenuCraft_Category_Repository::update( $id, $data );

		if ( null === $updated ) {
			return new WP_Error( 'menucraft_update_failed', __( 'Could not update the category.', 'menucraft' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( self::present( $updated ), 200 );
	}

	/**
	 * DELETE /menucraft/v1/categories/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_category( WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$existing = MenuCraft_Category_Repository::find( $id );

		if ( null === $existing ) {
			return new WP_Error( 'menucraft_not_found', __( 'Category not found.', 'menucraft' ), array( 'status' => 404 ) );
		}

		$ok = MenuCraft_Category_Repository::delete( $id );

		if ( ! $ok ) {
			return new WP_Error( 'menucraft_delete_failed', __( 'Could not delete the category.', 'menucraft' ), array( 'status' => 500 ) );
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
	 * Validate that parent_id and media_id (if provided and > 0) reference
	 * existing rows/attachments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $self_id When updating, disallow parent = self.
	 * @return true|WP_Error
	 */
	private static function validate_relations( WP_REST_Request $request, $self_id = 0 ) {
		if ( $request->has_param( 'parent_id' ) ) {
			$parent_id = (int) $request->get_param( 'parent_id' );
			if ( $parent_id > 0 ) {
				if ( $parent_id === (int) $self_id ) {
					return new WP_Error( 'menucraft_invalid_parent', __( 'A category cannot be its own parent.', 'menucraft' ), array( 'status' => 400 ) );
				}
				if ( null === MenuCraft_Category_Repository::find( $parent_id ) ) {
					return new WP_Error( 'menucraft_invalid_parent', __( 'Parent category does not exist.', 'menucraft' ), array( 'status' => 400 ) );
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
	 * Add presentation-only fields to a category array.
	 *
	 * @param array<string,mixed>|null $category Hydrated category or null.
	 * @return array<string,mixed>|null
	 */
	private static function present( $category ) {
		if ( ! is_array( $category ) ) {
			return $category;
		}

		$category['media_url'] = $category['media_id'] > 0
			? wp_get_attachment_image_url( $category['media_id'], 'thumbnail' )
			: null;

		return $category;
	}
}
