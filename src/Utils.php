<?php
namespace WPGraphQLAcf;

use Exception;
use WPGraphQL\Model\Comment;
use WPGraphQL\Model\Menu;
use WPGraphQL\Model\MenuItem;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;
use WPGraphQL\Model\User;

class Utils {

	/**
	 * @var null|FieldTypeRegistry
	 */
	protected static $type_registry;

	/**
	 * @param mixed $node
	 *
	 * @return int|mixed|string
	 */
	public static function get_node_acf_id( $node ) {
		if ( is_array( $node ) && isset( $node['node']->ID ) ) {
			return absint( $node['node']->ID );
		}

		switch ( true ) {
			case $node instanceof Term:
				$id = 'term_' . $node->term_id;
				break;
			case $node instanceof Post:
				$id = absint( $node->databaseId );
				break;
			case $node instanceof MenuItem:
				$id = absint( $node->menuItemId );
				break;
			case $node instanceof Menu:
				$id = 'term_' . $node->menuId;
				break;
			case $node instanceof User:
				$id = 'user_' . absint( $node->userId );
				break;
			case $node instanceof Comment:
				// @phpstan-ignore-next-line
				$id = 'comment_' . absint( $node->databaseId );
				break;
			case is_array( $node ) && isset( $node['post_id'] ) && 'options' === $node['post_id']:
				$id = $node['post_id'];
				break;
			default:
				$id = 0;
				break;
		}

		return $id;
	}

	/**
	 * Return the Field Type Registry instance
	 *
	 * @return FieldTypeRegistry
	 */
	public static function get_type_registry(): FieldTypeRegistry {

		if ( self::$type_registry instanceof FieldTypeRegistry ) {
			return self::$type_registry;
		}

		self::$type_registry = new FieldTypeRegistry();
		return self::$type_registry;

	}

	/**
	 * Given the name of an ACF Field Type (text, textarea, etc) return the AcfGraphQLFieldType definition
	 *
	 * @param string $acf_field_type The name of the ACF Field Type (text, textarea, etc)
	 *
	 * @return AcfGraphQLFieldType|null
	 */
	public static function get_graphql_field_type( string $acf_field_type ): ?AcfGraphQLFieldType {

		return self::get_type_registry()->get_field_type( $acf_field_type );

	}

	/**
	 * Get a list of supported fields that WPGraphQL for ACF supports.
	 *
	 * This is helpful for determining whether UI should be output for the field, and whether
	 * the field should be added to the Schema.
	 *
	 * Some fields, such as "Accordion" are not supported currently.
	 *
	 * @return array
	 */
	public static function get_supported_acf_fields_types(): array {

		$registry               = self::get_type_registry();
		$registered_fields      = $registry->get_registered_field_types();
		$registered_field_names = array_keys( $registered_fields );

		/**
		 * Filter the supported fields
		 *
		 * @param array $supported_fields
		 */
		return apply_filters( 'wpgraphql_acf_supported_field_types', $registered_field_names );
	}

	/**
	 * Returns all available GraphQL Types
	 *
	 * @return array
	 * @throws Exception
	 */
	public static function get_all_graphql_types(): array {
		$graphql_types = [];

		// Use GraphQL to get the Interface and the Types that implement them
		$query = '
		query GetPossibleTypes($name:String!){
			__type(name:$name){
				name
				description
				possibleTypes {
					name
					description
				}
			}
		}
		';

		$interfaces = [
			'ContentNode'     => [
				'label'        => __( 'Post Type', 'wp-graphql-acf' ),
				'plural_label' => __( 'All Post Types', 'wp-graphql-acf' ),
			],
			'TermNode'        => [
				'label'        => __( 'Taxonomy', 'wp-graphql-acf' ),
				'plural_label' => __( 'All Taxonomies', 'wp-graphql-acf' ),
			],
			'ContentTemplate' => [
				'label'        => __( 'Page Template', 'wp-graphql-acf' ),
				'plural_label' => __( 'All Templates Assignable to Content', 'wp-graphql-acf' ),
			],
		];

		foreach ( $interfaces as $interface_name => $config ) {

			$interface_query = graphql([
				'query'     => $query,
				'variables' => [
					'name' => $interface_name,
				],
			]);

			$possible_types = is_array( $interface_query ) && isset( $interface_query['data']['__type']['possibleTypes'] ) ? $interface_query['data']['__type']['possibleTypes'] : [];
			asort( $possible_types );

			if ( ! empty( $possible_types ) && is_array( $possible_types ) ) {

				// Intentionally not translating "ContentNode Interface" as this is part of the GraphQL Schema and should not be translated.
				$graphql_types[ $interface_name ] = '<span data-interface="' . $interface_name . '">' . $interface_name . ' Interface (' . $config['plural_label'] . ')</span>';
				$label                            = '<span data-implements="' . $interface_name . '"> (' . $config['label'] . ')</span>';
				foreach ( $possible_types as $type ) {
					$type_label = $type['name'] . '&nbsp;' . $label;
					$type_key   = $type['name'];

					$graphql_types[ $type_key ] = $type_label;
				}
			}
		}

		/**
		 * Add comment to GraphQL types
		 */
		$graphql_types['Comment'] = __( 'Comment', 'wp-graphql-acf' );

		/**
		 * Add menu to GraphQL types
		 */
		$graphql_types['Menu'] = __( 'Menu', 'wp-graphql-acf' );

		/**
		 * Add menu items to GraphQL types
		 */
		$graphql_types['MenuItem'] = __( 'Menu Item', 'wp-graphql-acf' );

		/**
		 * Add users to GraphQL types
		 */
		$graphql_types['User'] = __( 'User', 'wp-graphql-acf' );

		/**
		 * Add options pages to GraphQL types
		 */
		global $acf_options_page;

		if ( isset( $acf_options_page ) && function_exists( 'acf_get_options_pages' ) ) {
			// Get a list of post types that have been registered to show in graphql
			$graphql_options_pages = acf_get_options_pages();

			/**
			 * If there are no post types exposed to GraphQL, bail
			 */
			if ( ! empty( $graphql_options_pages ) && is_array( $graphql_options_pages ) ) {

				/**
				 * Prepare type key prefix and label surfix
				 */
				$label = '<span class="options-page"> (' . __( 'ACF Options Page', 'wp-graphql-acf' ) . ')</span>';

				/**
				 * Loop over the post types exposed to GraphQL
				 */
				foreach ( $graphql_options_pages as $options_page_key => $options_page ) {
					if ( ! isset( $options_page['show_in_graphql'] ) || false === (bool) $options_page['show_in_graphql'] ) {
						continue;
					}

					/**
					 * Get options page properties.
					 */
					$page_title = $options_page['page_title'];
					$type_label = $page_title . $label;
					$type_name  = isset( $options_page['graphql_field_name'] ) ? \WPGraphQL\Utils\Utils::format_type_name( $options_page['graphql_field_name'] ) : \WPGraphQL\Utils\Utils::format_type_name( $options_page['menu_slug'] );

					$graphql_types[ $type_name ] = $type_label;
				}
			}
		}

		return $graphql_types;
	}

	/**
	 * Returns string of the items in the array list. Limit allows string to be limited length.
	 *
	 * @param array $list
	 * @param int $limit
	 *
	 * @return string
	 */
	public static function array_list_by_limit( array $list, $limit = 5 ): string {
		$flat_list = '';
		$total     = count( $list );

		// Labels.
		$labels     = $list;
		$labels     = array_slice( $labels, 0, $limit );
		$flat_list .= implode( ', ', $labels );

		// More.
		if ( $total > $limit ) {
			$flat_list .= ', ...';
		}
		return $flat_list;
	}

}
