<template>
	<footer class="ext-communityrequests-intake__footer">
		<hr>
		<!-- eslint-disable-next-line vue/no-v-html -->
		<div v-html="copyrightWarning"></div>
		<cdx-button
			weight="primary"
			action="progressive"
			class="ext-communityrequests-intake__submit"
			type="submit"
			@click.prevent="$emit( 'submit' )"
		>
			<span v-if="exists">{{ saveMsg }}</span>
			<span v-else>{{ publishMsg }}</span>
		</cdx-button>
		<a
			:href="returnTo"
			class="cdx-button cdx-button--fake-button--enabled
				cdx-button--weight-quiet ext-communityrequests-intake__cancel"
		>
			{{ $i18n( 'cancel' ).text() }}
		</a>
		<cdx-message
			v-if="formError"
			type="error"
			class="ext-communityrequests-intake__form-error"
		>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<p><strong v-html="formErrorMsg"></strong></p>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<div v-html="formError"></div>
		</cdx-message>
	</footer>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxMessage } = require( '../codex.js' );

module.exports = exports = defineComponent( {
	name: 'FooterSection',
	components: {
		CdxButton,
		CdxMessage
	},
	props: {
		exists: { type: Boolean, required: true },
		publishMsg: { type: String, required: true },
		saveMsg: { type: String, required: true },
		returnTo: { type: String, required: true },
		formError: { type: Boolean, default: false },
		formErrorMsg: { type: String, default: '' }
	},
	emits: [ 'submit' ],
	setup() {
		return {
			copyrightWarning: mw.config.get( 'copyrightWarning' )
		};
	}
} );
</script>
