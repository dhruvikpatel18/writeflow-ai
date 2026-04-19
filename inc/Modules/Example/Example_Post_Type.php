<?php
/**
 * Example Post Type.
 *
 * @package WriteFlowAI\Modules\Example
 */

declare( strict_types = 1 );

namespace WriteFlowAI\Modules\Example;

use WriteFlowAI\Framework\Contracts\Abstracts\Abstract_Post_Type;

/**
 * Class - Example_Post_Type
 */
final class Example_Post_Type extends Abstract_Post_Type {
	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'example';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_post_type(): void {
		register_post_type( // phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.NotStringLiteral -- Defined above.
			self::get_slug(),
			array_merge(
				$this->default_args(),
				[
						'label'      => __( 'Examples', 'writeflow-ai' ),
						'labels'     => [
							'name'               => __( 'Examples', 'writeflow-ai' ),
							'singular_name'      => __( 'Example', 'writeflow-ai' ),
							'add_new'            => __( 'Add New Example', 'writeflow-ai' ),
							'add_new_item'       => __( 'Add New Example', 'writeflow-ai' ),
							'edit_item'          => __( 'Edit Example', 'writeflow-ai' ),
							'new_item'           => __( 'New Example', 'writeflow-ai' ),
							'view_item'          => __( 'View Example', 'writeflow-ai' ),
							'search_items'       => __( 'Search Examples', 'writeflow-ai' ),
							'not_found'          => __( 'No examples found.', 'writeflow-ai' ),
							'not_found_in_trash' => __( 'No examples found in Trash.', 'writeflow-ai' ),
					],
					'supports'   => array_merge( $this->default_args()['supports'], [ 'custom-fields' ] ),
					'taxonomies' => [ Example_Taxonomy::get_slug() ],
				]
			)
		);
	}
}
