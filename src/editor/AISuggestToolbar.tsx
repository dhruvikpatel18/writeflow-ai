/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import apiFetch from '@wordpress/api-fetch';
import { createHigherOrderComponent } from '@wordpress/compose';

/**
 * External dependencies
 */
import { useState } from 'react';
import type { ComponentType } from 'react';

/**
 * Set up nonce middleware for REST API calls.
 *
 * The nonce is localized by PHP via wp_localize_script().
 * This middleware automatically includes it in all apiFetch requests.
 */
if ( window.WriteFlowAI?.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( window.WriteFlowAI.nonce ) );
}

/**
 * Block types that receive the AI Suggest toolbar button.
 *
 * Keeping this list explicit here makes it trivial to extend
 * to other text blocks (e.g. core/list-item) in a future iteration.
 */
const SUPPORTED_BLOCKS = [ 'core/paragraph', 'core/heading' ];

interface BlockEditProps {
	name: string;
	attributes: {
		/** Paragraph / heading content is stored as an HTML string. */
		content?: string;
		[ key: string ]: unknown;
	};
	/** Function to update block attributes. */
	setAttributes?: ( attrs: Record< string, unknown > ) => void;
	[ key: string ]: unknown;
}

interface SuggestResponse {
	success: boolean;
	data: { suggestion: string };
}

/**
 * Fetches an AI suggestion for the given plain-text content.
 *
 * Isolated from the component so it can be unit-tested or swapped without
 * touching the React tree.
 *
 * @param content Plain text to generate a suggestion for.
 */
async function fetchSuggestion( content: string ): Promise< string > {
	const response = await apiFetch< SuggestResponse >( {
		path: '/ai/v1/suggest',
		method: 'POST',
		data: { content },
	} );

	return response.data.suggestion;
}

/**
 * Higher-order component that injects an "AI Suggest" button into the
 * block toolbar for supported block types.
 *
 * We use the `editor.BlockEdit` filter rather than `registerPlugin` because
 * it lets us scope the button to specific block types cleanly, without
 * conditional rendering scattered across a global plugin component.
 */
const withAISuggestToolbar = createHigherOrderComponent(
	( BlockEdit: ComponentType< BlockEditProps > ) =>
		function AISuggestBlock( props: BlockEditProps ) {
			const [ isLoading, setIsLoading ] = useState( false );

			// Render unsupported blocks unchanged — no extra DOM, no overhead.
			if ( ! SUPPORTED_BLOCKS.includes( props.name ) ) {
				return <BlockEdit { ...props } />;
			}

			async function handleSuggest() {
				// attributes.content is an HTML string (RichText output).
				// Decode HTML entities and strip tags before sending to the API.
				const rawContent = props.attributes.content ?? '';
				const plainText = decodeEntities(
					rawContent.replace( /<[^>]*>/g, '' )
				).trim();

				if ( ! plainText ) {
					// eslint-disable-next-line no-alert -- temporary UI, will be replaced with proper modal/panel.
					alert( 'Add some content to the block first.' );
					return;
				}

				setIsLoading( true );

				try {
					const suggestion = await fetchSuggestion( plainText );
					// Apply the suggestion as the new block content.
					if ( props.setAttributes ) {
						props.setAttributes( { content: suggestion } );
					}
				} catch ( error: unknown ) {
					// Log full error for debugging; show user-friendly message.
					console.error( 'AI suggestion error:', error );
					// eslint-disable-next-line no-alert -- temporary UI per spec.
					alert( 'AI request failed. Check console for details.' );
				} finally {
					setIsLoading( false );
				}
			}

			return (
				<>
					<BlockEdit { ...props } />
					<BlockControls>
						<ToolbarGroup>
							<ToolbarButton
								isBusy={ isLoading }
								disabled={ isLoading }
								onClick={ () => {
									// void: onClick expects void, handleSuggest is async.
									void handleSuggest();
								} }
							>
								AI Suggest
							</ToolbarButton>
						</ToolbarGroup>
					</BlockControls>
				</>
			);
		},
	'withAISuggestToolbar'
);

addFilter(
	'editor.BlockEdit',
	'writeflow-ai/with-ai-suggest-toolbar',
	withAISuggestToolbar
);
