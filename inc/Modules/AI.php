<?php
/**
 * AI Module.
 *
 * Initializes all classes for the AI module.
 *
 * @package WriteFlowAI\Modules
 */

declare( strict_types = 1 );

namespace WriteFlowAI\Modules;

use WriteFlowAI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - AI
 */
final class AI implements Registrable {

	/**
	 * Registrable classes for this module.
	 *
	 * Add new AI sub-features (e.g. Summarizer, SEO) here as they are built.
	 *
	 * @var class-string<\WriteFlowAI\Framework\Contracts\Interfaces\Registrable>[]
	 */
	private const REGISTRABLE_CLASSES = [
		AI\RestController::class,
	];

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		foreach ( self::REGISTRABLE_CLASSES as $class_name ) {
			$instance = new $class_name();
			$instance->register_hooks();
		}
	}
}
