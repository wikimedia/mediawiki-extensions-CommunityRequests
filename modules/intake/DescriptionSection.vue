<template>
	<section class="wishlist-intake-description">
		<cdx-field
			:status="titlestatus"
			:messages="titlemessage"
			:disabled="disabled"
			class="community-wishlist-title-field"
		>
			<cdx-text-input
				:model-value="title"
				@input="$emit( 'update:title', $event.target.value.trim() )"
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
			:status="descriptionstatus"
			:messages="descriptionmessage"
			:disabled="disabled"
			class="community-wishlist-description-field"
		>
			<div class="wishlist-intake-textarea-wrapper wishlist-intake-textarea-wrapper--loading">
				<textarea
					class="wishlist-intake-textarea"
					:rows="8"
					:value="description"
					@input="$emit( 'update:description', $event.target.value.trim() )">
				</textarea>
			</div>
			<template #label>
				{{ $i18n( 'communityrequests-description' ).text() }}
			</template>
			<template #description>
				{{ $i18n( 'communityrequests-description-description' ).text() }}
			</template>
		</cdx-field>
	</section>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxField, CdxTextInput } = require( '@wikimedia/codex' );
const DescriptionField = require( './DescriptionField.js' );

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
		titlestatus: { type: String, default: 'default' },
		descriptionstatus: { type: String, default: 'default' },
		disabled: { type: Boolean, default: false }
	},
	emits: [
		'update:title',
		'update:description',
		'update:pre-submit-promise'
	],
	data() {
		return {
			titlemessage: {},
			descriptionmessage: {}
		};
	},
	methods: {
		setupDescriptionField() {
			if ( descriptionField ) {
				return;
			}
			const textarea = document.querySelector( '.wishlist-intake-textarea' );
			descriptionField = new DescriptionField( textarea, this.description );
			descriptionField.setPending( true );
			return mw.loader.using( mw.config.get( 'intakeVeModules' ) ).then( () => {
				descriptionField.init();
				this.$emit(
					'update:pre-submit-promise',
					descriptionField.syncChangesToTextarea.bind( descriptionField )
				);
			} );
		}
	},
	watch: {
		titlestatus: {
			handler( newStatus ) {
				if ( newStatus === 'error' ) {
					this.titlemessage = {
						error: mw.msg( 'communityrequests-title-error', 5, 100 )
					};
				} else {
					this.titlemessage = {};
				}
			}
		},
		descriptionstatus: {
			handler( newStatus ) {
				if ( newStatus === 'error' ) {
					this.descriptionmessage = {
						error: mw.msg( 'communityrequests-description-error', 50 )
					};
				} else {
					this.descriptionmessage = {};
				}
			}
		}
	},
	mounted() {
		this.setupDescriptionField();
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

@min-height-editor: 194px;

/* Overrides to make OOUI sort of mimic Codex */
.wishlist-intake-textarea-wrapper {
	border: @border-base;

	.ve-ui-surface-placeholder,
	.ve-ui-surface .ve-ce-attachedRootNode {
		padding: @size-50 @size-100;
	}

	.ve-ce-surface .ve-ce-attachedRootNode {
		min-height: @min-height-editor;
	}

	.wishlist-intake-ve-surface {
		transition-property: @transition-property-base;
		/* XXX: doesn't appear to be a Codex token for this */
		transition-duration: 0.25s;

		&:has( > .ve-ce-surface-focused ) {
			border: @border-progressive;
			box-sizing: border-box;
			box-shadow: @box-shadow-inset-small @box-shadow-color-progressive--focus;
		}
	}

	/* TODO: Replace with official Codex loading styles once established. */
	/* See https://w.wiki/AHp3 */
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
				background-position: -@size-icon-medium 0;
			}

			100% {
				background-position: 0 0;
			}
		}
	}
}

.cdx-field--disabled .wishlist-intake-textarea-wrapper {
	opacity: 0.5;
	pointer-events: none;
}

.skin-minerva .wishlist-intake-textarea {
	box-sizing: border-box;
	width: 100%;
}
</style>
