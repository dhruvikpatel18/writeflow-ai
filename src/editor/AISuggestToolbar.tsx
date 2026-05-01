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
 * Fetches an AI suggestion for the given plain-text content.
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
 * Loading State Component
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
	isLoading: boolean;
}

function SuggestionContent( {
	suggestion,
	onAccept,
	onReject,
	onRegenerate,
	isLoading,
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
					disabled={ isLoading }
				>
					Reject
				</Button>

				<Button
					variant="secondary"
					onClick={ onRegenerate }
					disabled={ isLoading }
				>
					Regenerate
				</Button>

				<Button
					variant="primary"
					onClick={ onAccept }
					disabled={ isLoading }
				>
					Accept & Replace
				</Button>
			</div>
		</>
	);
}

/**
 * Modal Content Component - renders appropriate state
 */
interface ModalContentProps {
	isLoading: boolean;
	suggestion: string;
	error: string | null;
	onAccept: () => void;
	onReject: () => void;
	onRegenerate: () => void;
}

function ModalContent( {
	isLoading,
	suggestion,
	error,
	onAccept,
	onReject,
	onRegenerate,
}: ModalContentProps ): JSX.Element {
	if ( isLoading ) {
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
			isLoading={ isLoading }
		/>
	);
}

/**
 * Suggestion Preview Modal
 */
interface SuggestionModalProps {
	isOpen: boolean;
	isLoading: boolean;
	suggestion: string;
	error: string | null;
	onAccept: () => void;
	onReject: () => void;
	onRegenerate: () => void;
}

function SuggestionModal( {
	isOpen,
	isLoading,
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
			<div style={ { padding: '24px' } }>
				<ModalContent
					isLoading={ isLoading }
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
			const [ isLoading, setIsLoading ] = useState( false );
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
			 * Fetch suggestion from API and open modal
			 */
			async function fetchSuggestionAndOpen(): Promise< void > {
				const plainText = getPlainText();

				if ( ! plainText ) {
					setError( 'Please add some content to the block first.' );
					setSuggestion( '' );
					setIsModalOpen( true );
					return;
				}

				setIsLoading( true );
				setError( null );
				setSuggestion( '' );
				setIsModalOpen( true );

				try {
					const result = await fetchSuggestion( plainText );
					setSuggestion( result );
				} catch ( err: unknown ) {
					console.error( 'AI suggestion error:', err );
					setError(
						'Failed to generate suggestion. Check console for details.'
					);
					setSuggestion( '' );
				} finally {
					setIsLoading( false );
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
			 * Regenerate suggestion
			 */
			async function handleRegenerate(): Promise< void > {
				const plainText = getPlainText();
				setIsLoading( true );
				setError( null );

				try {
					const result = await fetchSuggestion( plainText );
					setSuggestion( result );
				} catch ( err: unknown ) {
					console.error( 'AI suggestion error:', err );
					setError(
						'Failed to regenerate. Check console for details.'
					);
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
								onClick={ () => {
									void fetchSuggestionAndOpen();
								} }
								disabled={ isLoading }
								isBusy={ isLoading }
							>
								AI Suggest
							</ToolbarButton>
						</ToolbarGroup>
					</BlockControls>

					<SuggestionModal
						isOpen={ isModalOpen }
						isLoading={ isLoading }
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
