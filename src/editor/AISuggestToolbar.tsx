/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { BlockControls } from '@wordpress/block-editor';
import {
	ToolbarGroup,
	ToolbarButton,
	Modal,
	Button,
	Spinner,
} from '@wordpress/components';
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
 */
const SUPPORTED_BLOCKS = [ 'core/paragraph', 'core/heading' ];

/**
 * Error marker emitted by the PHP streaming endpoint on failure.
 * Must match the prefix used in RestController::handle_suggest_stream().
 */
const STREAM_ERROR_MARKER = '[WRITEFLOW_STREAM_ERROR]:';

interface BlockEditProps {
	name: string;
	attributes: {
		content?: string;
		[ key: string ]: unknown;
	};
	setAttributes?: ( attrs: Record< string, unknown > ) => void;
	[ key: string ]: unknown;
}

interface SuggestResponse {
	success: boolean;
	data: { suggestion: string };
}

/**
 * Fetches an AI suggestion for the given content (non-streaming fallback).
 * Kept intact so callers that don't need streaming can still use it.
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
 * Streams an AI suggestion from the backend, invoking onChunk for each piece
 * of text as it arrives.
 *
 * Uses the native Fetch + ReadableStream API so text appears progressively
 * in the UI without waiting for the full OpenAI response.
 *
 * Error handling:
 *   - HTTP-level errors (non-2xx) throw immediately before reading.
 *   - Backend pre-stream errors are emitted as a "[WRITEFLOW_STREAM_ERROR]:"
 *     prefixed string; this function detects that marker and throws an Error
 *     with the human-readable message so callers handle it uniformly.
 *
 * @param content Plain-text content to send to the AI endpoint.
 * @param onChunk Called with each decoded text chunk as it arrives.
 */
async function fetchSuggestionStream(
	content: string,
	onChunk: ( chunk: string ) => void
): Promise< void > {
	const response = await fetch( '/wp-json/ai/v1/suggest-stream', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': window.WriteFlowAI?.nonce ?? '',
		},
		body: JSON.stringify( { content } ),
	} );

	if ( ! response.ok ) {
		throw new Error( `Request failed with status ${ response.status }` );
	}

	if ( ! response.body ) {
		throw new Error( 'Response body is not available for streaming.' );
	}

	const reader = response.body.getReader();
	const decoder = new TextDecoder();
	let isFirstChunk = true;

	try {
		while ( true ) {
			const { done, value } = await reader.read();
			if ( done ) {
				break;
			}

			const chunk = decoder.decode( value, { stream: true } );

			// The PHP endpoint emits this marker as the sole response when a
			// pre-stream error occurs (e.g. missing API key). Detect it on the
			// first chunk so we can surface a clean error message to the user.
			if ( isFirstChunk && chunk.startsWith( STREAM_ERROR_MARKER ) ) {
				const message = chunk
					.slice( STREAM_ERROR_MARKER.length )
					.trim();
				throw new Error(
					message ||
						'An error occurred while generating the suggestion.'
				);
			}
			isFirstChunk = false;

			onChunk( chunk );
		}
	} finally {
		reader.releaseLock();
	}
}

/**
 * Loading State Component, shown while waiting for the first streamed chunk.
 */
function LoadingState(): JSX.Element {
	return (
		<div
			style={ {
				display: 'flex',
				flexDirection: 'column',
				alignItems: 'center',
				justifyContent: 'center',
				minHeight: '200px',
				gap: '16px',
			} }
		>
			<Spinner />
			<p>Generating suggestion...</p>
		</div>
	);
}

/**
 * Error State Component
 */
interface ErrorStateProps {
	error: string;
}

function ErrorState( { error }: ErrorStateProps ): JSX.Element {
	return (
		<div
			style={ {
				padding: '16px',
				backgroundColor: '#fff5f5',
				border: '1px solid #d32f2f',
				borderRadius: '4px',
				color: '#d32f2f',
			} }
		>
			<p style={ { fontWeight: 600, marginBottom: '8px' } }>
				Oops! Something went wrong
			</p>
			<p style={ { margin: 0, fontSize: '14px' } }>{ error }</p>
		</div>
	);
}

/**
 * Suggestion Content Component
 */
interface SuggestionContentProps {
	suggestion: string;
	onAccept: () => void;
	onReject: () => void;
	onRegenerate: () => void;
	/** True while the stream is still in progress — disables action buttons. */
	isStreaming: boolean;
}

function SuggestionContent( {
	suggestion,
	onAccept,
	onReject,
	onRegenerate,
	isStreaming,
}: SuggestionContentProps ): JSX.Element {
	return (
		<>
			<div style={ { marginBottom: '24px' } }>
				<h3
					style={ {
						fontSize: '14px',
						fontWeight: 600,
						color: '#444',
						marginBottom: '12px',
						textTransform: 'uppercase',
						letterSpacing: '0.5px',
					} }
				>
					Suggested Text:
				</h3>
				<div
					style={ {
						padding: '16px',
						backgroundColor: '#f5f5f5',
						borderRadius: '4px',
						fontSize: '15px',
						lineHeight: '1.6',
						border: '1px solid #e0e0e0',
						maxHeight: '300px',
						overflowY: 'auto',
						wordWrap: 'break-word',
						whiteSpace: 'pre-wrap',
					} }
				>
					{ suggestion }
					{ isStreaming && (
						<span
							style={ {
								display: 'inline-block',
								width: '2px',
								height: '1em',
								backgroundColor: '#1e1e1e',
								marginLeft: '2px',
								verticalAlign: 'text-bottom',
								animation:
									'writeflow-blink 1s step-end infinite',
							} }
							aria-hidden="true"
						/>
					) }
				</div>
			</div>

			<div
				style={ {
					display: 'flex',
					gap: '12px',
					justifyContent: 'flex-end',
					paddingTop: '16px',
					borderTop: '1px solid #e0e0e0',
				} }
			>
				<Button
					variant="secondary"
					onClick={ onReject }
					disabled={ isStreaming }
				>
					Reject
				</Button>

				<Button
					variant="secondary"
					onClick={ onRegenerate }
					disabled={ isStreaming }
				>
					Regenerate
				</Button>

				<Button
					variant="primary"
					onClick={ onAccept }
					disabled={ isStreaming }
				>
					Accept & Replace
				</Button>
			</div>
		</>
	);
}

/**
 * Modal Content Component — renders the appropriate state based on stream progress.
 *
 * State transitions:
 *   isStreaming + no content yet  →  LoadingState (spinner)
 *   error                         →  ErrorState
 *   otherwise                     →  SuggestionContent (buttons disabled while streaming)
 */
interface ModalContentProps {
	isStreaming: boolean;
	suggestion: string;
	error: string | null;
	onAccept: () => void;
	onReject: () => void;
	onRegenerate: () => void;
}

function ModalContent( {
	isStreaming,
	suggestion,
	error,
	onAccept,
	onReject,
	onRegenerate,
}: ModalContentProps ): JSX.Element {
	// Show the spinner only while waiting for the very first chunk.
	if ( isStreaming && ! suggestion && ! error ) {
		return <LoadingState />;
	}

	if ( error ) {
		return <ErrorState error={ error } />;
	}

	return (
		<SuggestionContent
			suggestion={ suggestion }
			onAccept={ onAccept }
			onReject={ onReject }
			onRegenerate={ onRegenerate }
			isStreaming={ isStreaming }
		/>
	);
}

/**
 * Suggestion Preview Modal
 */
interface SuggestionModalProps {
	isOpen: boolean;
	isStreaming: boolean;
	suggestion: string;
	error: string | null;
	onAccept: () => void;
	onReject: () => void;
	onRegenerate: () => void;
}

function SuggestionModal( {
	isOpen,
	isStreaming,
	suggestion,
	error,
	onAccept,
	onReject,
	onRegenerate,
}: SuggestionModalProps ): JSX.Element | null {
	if ( ! isOpen ) {
		return null;
	}

	return (
		<Modal
			title="AI Suggestion Preview"
			onRequestClose={ onReject }
			size="large"
		>
			{ /* Inline keyframes for the blinking cursor — no external CSS required. */ }
			<style>{ `@keyframes writeflow-blink { 0%,100%{opacity:1} 50%{opacity:0} }` }</style>
			<div style={ { padding: '24px' } }>
				<ModalContent
					isStreaming={ isStreaming }
					suggestion={ suggestion }
					error={ error }
					onAccept={ onAccept }
					onReject={ onReject }
					onRegenerate={ onRegenerate }
				/>
			</div>
		</Modal>
	);
}

/**
 * Higher-order component that adds AI Suggest functionality to block toolbar.
 */
const withAISuggestToolbar = createHigherOrderComponent(
	( BlockEdit: ComponentType< BlockEditProps > ) =>
		function AISuggestBlock( props: BlockEditProps ) {
			const [ isModalOpen, setIsModalOpen ] = useState( false );
			const [ isStreaming, setIsStreaming ] = useState( false );
			const [ suggestion, setSuggestion ] = useState( '' );
			const [ error, setError ] = useState< string | null >( null );

			// Render unsupported blocks unchanged
			if ( ! SUPPORTED_BLOCKS.includes( props.name ) ) {
				return <BlockEdit { ...props } />;
			}

			/**
			 * Extract plain text from block content
			 */
			function getPlainText(): string {
				const rawContent = props.attributes.content ?? '';
				return decodeEntities(
					rawContent.replace( /<[^>]*>/g, '' )
				).trim();
			}

			/**
			 * Fetch a streaming suggestion and open the modal.
			 *
			 * The modal opens immediately with a loading spinner, then switches to
			 * live text as chunks arrive from the backend.
			 */
			async function fetchSuggestionAndOpen(): Promise< void > {
				const plainText = getPlainText();

				if ( ! plainText ) {
					setError( 'Please add some content to the block first.' );
					setSuggestion( '' );
					setIsModalOpen( true );
					return;
				}

				setIsStreaming( true );
				setError( null );
				setSuggestion( '' );
				setIsModalOpen( true );

				try {
					await fetchSuggestionStream( plainText, ( chunk ) => {
						setSuggestion( ( prev ) => prev + chunk );
					} );
				} catch ( err: unknown ) {
					console.error( 'AI suggestion error:', err );
					const message =
						err instanceof Error ? err.message : String( err );
					setError( message );
					setSuggestion( '' );
				} finally {
					setIsStreaming( false );
				}
			}

			/**
			 * Accept suggestion and replace block content
			 */
			function handleAccept(): void {
				if ( props.setAttributes ) {
					props.setAttributes( { content: suggestion } );
				}
				setIsModalOpen( false );
			}

			/**
			 * Reject and close modal
			 */
			function handleReject(): void {
				setIsModalOpen( false );
			}

			/**
			 * Regenerate suggestion via streaming, clears previous text and streams fresh.
			 */
			async function handleRegenerate(): Promise< void > {
				const plainText = getPlainText();
				setIsStreaming( true );
				setError( null );
				setSuggestion( '' );

				try {
					await fetchSuggestionStream( plainText, ( chunk ) => {
						setSuggestion( ( prev ) => prev + chunk );
					} );
				} catch ( err: unknown ) {
					console.error( 'AI suggestion error:', err );
					const message =
						err instanceof Error ? err.message : String( err );
					setError( message );
					setSuggestion( '' );
				} finally {
					setIsStreaming( false );
				}
			}

			return (
				<>
					<BlockEdit { ...props } />
					<BlockControls>
						<ToolbarGroup>
							<ToolbarButton
								onClick={ () => {
									void fetchSuggestionAndOpen();
								} }
								disabled={ isStreaming }
								isBusy={ isStreaming }
							>
								AI Suggest
							</ToolbarButton>
						</ToolbarGroup>
					</BlockControls>

					<SuggestionModal
						isOpen={ isModalOpen }
						isStreaming={ isStreaming }
						suggestion={ suggestion }
						error={ error }
						onAccept={ handleAccept }
						onReject={ handleReject }
						onRegenerate={ handleRegenerate }
					/>
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
