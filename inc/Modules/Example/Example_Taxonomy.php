<?php
/**
 * Example Taxonomy.
 *
 * @package WriteFlowAI\Modules\Example
 */

declare( strict_types = 1 );

namespace WriteFlowAI\Modules\Example;

use WriteFlowAI\Framework\Contracts\Abstracts\Abstract_Taxonomy;

/**
 * Class - Example_Taxonomy
 */
final class Example_Taxonomy extends Abstract_Taxonomy {
	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'example-tax';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_object_types(): array {
		return [ Example_Post_Type::get_slug() ];
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			self::get_slug(),
			self::get_object_types(),
			array_merge(
				$this->default_args(),
				[
					'hierarchical' => true,
					'label'        => __( 'Example Categories', 'writeflow-ai' ),
					'labels'       => [
						'name'              => __( 'Example Categories', 'writeflow-ai' ),
						'singular_name'     => __( 'Example Category', 'writeflow-ai' ),
						'search_items'      => __( 'Search Example Categories', 'writeflow-ai' ),
						'all_items'         => __( 'All Example Categories', 'writeflow-ai' ),
						'parent_item'       => __( 'Parent Example Category', 'writeflow-ai' ),
						'parent_item_colon' => __( 'Parent Example Category:', 'writeflow-ai' ),
						'edit_item'         => __( 'Edit Example Category', 'writeflow-ai' ),
						'update_item'       => __( 'Update Example Category', 'writeflow-ai' ),
						'add_new_item'      => __( 'Add New Example Category', 'writeflow-ai' ),
						'new_item_name'     => __( 'New Example Category Name', 'writeflow-ai' ),
						'menu_name'         => __( 'Example Categories', 'writeflow-ai' ),
					],
				]
			)
		);
	}
}
