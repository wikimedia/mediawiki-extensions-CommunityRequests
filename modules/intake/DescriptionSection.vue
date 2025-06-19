<template>
	<cdx-field
		class="ext-communityrequests-intake__title"
		:status="titleStatus"
		:messages="titleMessage"
	>
		<cdx-text-input
			name="wishtitle"
			:model-value="title"
			@input="$emit( 'update:title', $event.target.value )"
		>
		</cdx-text-input>
		<template #label>
			{{ $i18n( 'communityrequests-title' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'communityrequests-title-description' ).text() }}
		</template>
	</cdx-field>
	<cdx-field
		class="ext-communityrequests-intake__description"
		:status="descriptionStatus"
		:messages="descriptionMessage"
	>
		<div
			class="ext-communityrequests-intake__textarea-wrapper
				ext-communityrequests-intake__textarea-wrapper--loading"
		>
			<textarea
				class="ext-communityrequests-intake__textarea"
				name="description"
				:rows="8"
				:value="description"
				readonly="readonly"
				@change="$emit( 'update:description', $event.target.value )">
			</textarea>
		</div>
		<template #label>
			{{ $i18n( 'communityrequests-description' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'communityrequests-description-description' ).text() }}
		</template>
	</cdx-field>
</template>

<script>
const { computed, defineComponent, onMounted, ComputedRef } = require( 'vue' );
const { CdxField, CdxTextInput } = require( '../codex.js' );
const DescriptionField = require( './DescriptionField.js' );
const { CommunityRequestsHomepage } = require( '../common/config.json' );
const titleMaxChars = mw.config.get( 'intakeTitleMaxChars' );

// This must live here outside the Vue component to prevent Vue from interfering with VE.
let descriptionField;

module.exports = exports = defineComponent( {
	name: 'DescriptionSection',
	components: {
		CdxField,
		CdxTextInput
	},
	props: {
		title: { type: String, default: '' },
		description: { type: String, default: '' },
		titleStatus: { type: String, default: 'default' },
		descriptionStatus: { type: String, default: 'default' }
	},
	emits: [
		'update:title',
		'update:description',
		'update:pre-submit-promise'
	],
	setup( props, { emit } ) {
		/**
		 * Status message for the title field.
		 *
		 * @type {ComputedRef<Object>}
		 */
		const titleMessage = computed(
			() => props.titleStatus === 'error' ?
				{ error: mw.msg( 'communityrequests-title-error', 5, titleMaxChars ) } :
				{}
		);
		/**
		 * Status message for the description field.
		 *
		 * @type {ComputedRef<Object>}
		 */
		const descriptionMessage = computed(
			() => props.descriptionStatus === 'error' ?
				{ error: mw.msg( 'communityrequests-description-error', 50 ) } :
				{}
		);

		onMounted( async () => {
			if ( descriptionField ) {
				return;
			}
			// Use $wgCommunityRequestsHomepage as the context for VE.
			mw.config.set( 'wgRelevantPageName', CommunityRequestsHomepage );
			const textarea = document.querySelector( '.ext-communityrequests-intake__textarea' );
			descriptionField = new DescriptionField( textarea );
			descriptionField.setPending( true );
			await mw.loader.using( mw.config.get( 'intakeVeModules' ) );
			descriptionField.init();
			emit(
				'update:pre-submit-promise',
				descriptionField.syncChangesToTextarea.bind( descriptionField )
			);
		} );

		return {
			titleMessage,
			descriptionMessage
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

@min-height-editor: 194px;

.ext-communityrequests-intake__textarea {
	opacity: 0.5;
	pointer-events: none;

	.skin-minerva & {
		box-sizing: border-box;
		width: 100%;
	}
}

/* Overrides to make OOUI sort of mimic Codex */
.ext-communityrequests-intake__textarea-wrapper {
	border: @border-base;

	.ve-ui-surface-placeholder,
	.ve-ui-surface .ve-ce-attachedRootNode {
		padding: @size-50 @size-100;
	}

	.ve-ce-surface .ve-ce-attachedRootNode {
		min-height: @min-height-editor;
	}

	.ext-communityrequests-intake__ve-surface {
		transition-property: @transition-property-base;
		/* XXX: doesn't appear to be a Codex token for this */
		transition-duration: 0.25s;

		&:has( > .ve-ce-surface-focused ) {
			border: @border-progressive;
			box-sizing: border-box;
			box-shadow: @box-shadow-inset-small @box-shadow-color-progressive--focus;
		}
	}

	/* TODO: Replace with official Codex loading styles once established. See https://w.wiki/AHp3 */
	&--loading {
		background-color: @background-color-interactive;
		background-image: linear-gradient( 135deg, @background-color-base 25%, @background-color-transparent 25%, @background-color-transparent 50%, @background-color-base 50%, @background-color-base 75%, @background-color-transparent 75%, @background-color-transparent );
		background-size: @size-icon-medium @size-icon-medium;
		animation-name: cdx-animation-pending-stripes;
		animation-duration: 650ms;
		animation-timing-function: @animation-timing-function-base;
		animation-iteration-count: infinite;

		@keyframes cdx-animation-pending-stripes {
			0% {
				background-position: calc( -1 * @size-icon-medium ) 0;
			}

			100% {
				background-position: 0 0;
			}
		}
	}
}
</style>
