<?php
/**
 * WP-CLI Commands for WriteFlow AI.
 *
 * @package WriteFlowAI\Modules
 */

declare( strict_types = 1 );

namespace WriteFlowAI\Modules;

use WriteFlowAI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - CLI
 *
 * Registers WP-CLI commands for the plugin.
 */
final class CLI implements Registrable {
	/**
	 * {@inheritDoc}
	 *
	 * @uses \WP_CLI::add_command() instead of a hook.
	 */
	public function register_hooks(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		foreach ( $this->get_commands() as $name => $command ) {
			\WP_CLI::add_command(
				"writeflow-ai {$name}",
				$command['callback'],
				[
					'shortdesc' => $command['description'],
				]
			);
		}
	}

	/**
	 * Get available CLI commands.
	 *
	 * @return array<string, array{
	 *   callback: callable( array<int, mixed>, array<string, mixed> ): void,
	 *   description: string
	 * }>
	 */
	private function get_commands(): array {
		$commands = [
			CLI\Healthcheck::class,
		];

		return array_reduce(
			$commands,
			static function ( $carry, $command_class ) {
				$carry[ $command_class::get_name() ] = [
					'callback'    => [ $command_class, 'run' ],
					'description' => $command_class::get_description(),
				];
				return $carry;
			},
			[]
		);
	}
}
