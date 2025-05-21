const Util = require( '../common/Util.js' );
const { CommunityRequestsHomepage } = require( '../common/config.json' );

class DescriptionField {
	/**
	 * @param {HTMLElement|jQuery|string} node
	 */
	constructor( node ) {
		/**
		 * The textarea that will be replaced by the VisualEditor.
		 *
		 * @type {jQuery}
		 */
		this.$textarea = $( node );
		/**
		 * The wrapper of the textarea and the VisualEditor.
		 *
		 * @type {jQuery}
		 */
		this.$veWrapper = this.$textarea.parent();
		/**
		 * The MediaWiki target.
		 *
		 * @type {ve.init.mw.Target}
		 */
		this.target = null;
		/**
		 * The VisualEditor surface.
		 *
		 * @type {ve.ui.Surface}
		 */
		this.surface = null;
		/**
		 * The contents of the textarea/surface.
		 *
		 * @type {string} Always wikitext.
		 */
		this.content = this.$textarea.val();
	}

	/**
	 * Get the current mode of the VisualEditor,
	 * or the default mode if the ve.ui.Surface is not initialized.
	 *
	 * @return {string} 'source' or 'visual'
	 */
	get mode() {
		return this.surface ? this.surface.getMode() : this.defaultMode;
	}

	/**
	 * Get the default mode of the VisualEditor.
	 *
	 * @return {string} 'source' or 'visual'
	 */
	get defaultMode() {
		return mw.user.options.get( 'visualeditor-editor' ) === 'visualeditor' ?
			'visual' :
			'source';
	}

	/**
	 * Initialize the VisualEditor.
	 */
	init() {
		// Add modes and other tools the toolbar registry.
		const { CwVisualEditModeTool, CwSourceEditModeTool } = this.getEditModeTools();
		ve.ui.toolFactory.register( CwVisualEditModeTool );
		ve.ui.toolFactory.register( CwSourceEditModeTool );

		const veModules = mw.config.get( 'intakeVeModules' );
		if ( veModules.includes( 'ext.cite.visualEditor' ) ) {
			ve.ui.toolFactory.register( ve.ui.MWCitationDialogTool );
			ve.ui.toolFactory.register( ve.ui.MWReferenceDialogTool );
		}
		if ( veModules.includes( 'ext.citoid.visualEditor' ) ) {
			ve.ui.toolFactory.register( ve.ui.CitoidInspectorTool );
		}

		ve.init.mw.Platform.static.initializedPromise
			.catch( () => {
				this.$veWrapper.text( 'Sorry, this browser is not supported.' );
			} )
			.then( this.createTarget.bind( this ) );
	}

	/**
	 * Get the VisualEditor edit mode tools, customized to switch between source/visual.
	 *
	 * @return {Object} { CwVisualEditModeTool, CwSourceEditModeTool }
	 */
	getEditModeTools() {
		const CwEditModeTool = function () {};
		OO.initClass( CwEditModeTool );
		OO.inheritClass( CwEditModeTool, mw.libs.ve.MWEditModeTool );
		CwEditModeTool.prototype.getMode = function () {
			if ( !this.toolbar.getSurface() ) {
				return 'source';
			}
			return this.toolbar.getSurface().getMode();
		};

		const CwVisualEditModeTool = function () {
			CwEditModeTool.super.apply( this, arguments );
			CwEditModeTool.call( this );
		};
		OO.inheritClass( CwVisualEditModeTool, mw.libs.ve.MWEditModeVisualTool );
		OO.mixinClass( CwVisualEditModeTool, CwEditModeTool );

		const CwSourceEditModeTool = function () {
			CwEditModeTool.super.apply( this, arguments );
			CwEditModeTool.call( this );
		};
		OO.inheritClass( CwSourceEditModeTool, mw.libs.ve.MWEditModeSourceTool );
		OO.mixinClass( CwSourceEditModeTool, CwEditModeTool );

		return {
			CwVisualEditModeTool,
			CwSourceEditModeTool
		};
	}

	/**
	 * Get the content of the Surface.
	 *
	 * @return {jQuery.Promise<string>} HTML or wikitext
	 */
	getWikitext() {
		return this.mode === 'source' ?
			ve.createDeferred().resolve( this.surface.getDom() ).promise() :
			this.target.getWikitextFragment( this.surface.getModel().getDocument() );
	}

	get toolbarGroups() {
		if ( Util.isMobile() ) {
			// The following is identical to ve.init.mw.MobileArticleTarget.static.toolbarGroups.
			// We don't reference it because we don't want to load all of the
			// ext.visualEditor.mobileArticleTarget module and its dependencies.
			return [
				// History
				{
					name: 'history',
					include: [ 'undo' ]
				},
				// Style
				{
					name: 'style',
					classes: [ 've-test-toolbar-style' ],
					type: 'list',
					icon: 'textStyle',
					title: OO.ui.deferMsg( 'visualeditor-toolbar-style-tooltip' ),
					label: OO.ui.deferMsg( 'visualeditor-toolbar-style-tooltip' ),
					invisibleLabel: true,
					include: [ { group: 'textStyle' }, 'language', 'clear' ],
					forceExpand: [ 'bold', 'italic', 'clear' ],
					promote: [ 'bold', 'italic' ],
					demote: [ 'strikethrough', 'code', 'underline', 'language', 'clear' ]
				},
				// Link
				{
					name: 'link',
					include: [ 'link' ]
				}
			];
		}
		return ve.init.mw.Target.static.toolbarGroups;
	}

	/**
	 * Create the VisualEditor target and add initial content to the Surface.
	 *
	 * @param {string} mode 'source' or 'visual'
	 * @return {jQuery.Promise}
	 */
	async createTarget( mode = this.defaultMode ) {
		// HACK: VE looks for page context from wgRelevantPageName.
		// Special pages won't work as they don't have parsable wikitext.
		// Here we use the CommunityRequests homepage as a dummy value.
		if ( Util.isNewWish() ) {
			mw.config.set( 'wgRelevantPageName', CommunityRequestsHomepage );
		}
		this.target = new ve.init.mw.Target( {
			surfaceClasses: [ 'ext-communityrequests-intake__ve-surface' ],
			modes: [ 'visual', 'source' ],
			defaultMode: mode,
			toolbarConfig: { position: 'top' },
			toolbarGroups: [
				...this.toolbarGroups,
				{
					name: 'editMode',
					type: 'list',
					icon: 'edit',
					title: ve.msg( 'visualeditor-mweditmode-tooltip' ),
					label: ve.msg( 'visualeditor-mweditmode-tooltip' ),
					invisibleLabel: true,
					include: [ 'editModeVisual', 'editModeSource' ],
					align: Util.isMobile() ? 'center' : 'after'
				}
			]
		} );

		// Listener for edit mode switch.
		this.target.getToolbar().on( 'switchEditor', this.switchEditor.bind( this ) );

		// Add initial content.
		await this.setSurface( this.content, mode );
		// Add the target to the document and hide the textarea.
		this.$veWrapper.append( this.target.$element );
		this.$textarea.hide();
		this.setPending( false );
	}

	/**
	 * Switch the editor to the specified mode.
	 *
	 * @param {string} mode 'source' or 'visual'
	 * @return {jQuery.Promise}
	 */
	async switchEditor( mode ) {
		if ( mode === this.mode ) {
			return ve.createDeferred().resolve().promise();
		}

		this.setPending( true );
		this.content = await this.getWikitext();
		const oldTarget = this.target;
		await this.createTarget( mode );
		oldTarget.destroy();
		this.surface.focus();

		// Silently save preference.
		const editor = mode === 'source' ? 'wikitext' : 'visualeditor';
		new mw.Api().saveOption( 'visualeditor-editor', editor );
		mw.user.options.set( 'visualeditor-editor', editor );
	}

	/**
	 * Set the content of the Surface.
	 *
	 * @param {string} wikitext
	 * @param {string} mode 'source' or 'visual'
	 * @return {jQuery.Promise}
	 */
	async setSurface( wikitext, mode ) {
		const doc = await this.getDocFromWikitext( wikitext, mode );
		this.target.clearSurfaces();
		this.surface = this.target.addSurface( doc );
	}

	/**
	 * Create a document model from the given wikitext.
	 *
	 * @param {string} wikitext
	 * @param {string} mode 'source' or 'visual'
	 * @return {jQuery.Promise<ve.dm.Document>}
	 */
	async getDocFromWikitext( wikitext, mode ) {
		const options = {
			lang: mw.config.get( 'wgContentLanguage' ),
			dir: Util.isRtl() ? 'rtl' : 'ltr'
		};
		if ( mode === 'source' ) {
			return ve.createDeferred().resolve(
				ve.dm.sourceConverter.getModelFromSourceText( wikitext, options )
			).promise();
		}

		const outerPromise = ve.createDeferred();

		// Transform the wikitext to HTML.
		const resp = await this.target.parseWikitextFragment( wikitext );
		const htmlDoc = this.target.parseDocument( resp.visualeditor.content );
		// Avoids issues like T253584 where IDs could clash.
		mw.libs.ve.stripRestbaseIds( htmlDoc );
		const doc = ve.dm.converter.getModelFromDom( htmlDoc, options );
		outerPromise.resolve( doc );

		return outerPromise;
	}

	/**
	 * Synchronize changes from the Surface to the textarea.
	 *
	 * @return {jQuery.Promise}
	 */
	syncChangesToTextarea() {
		return this.getWikitext().then( ( content ) => {
			this.$textarea.val( this.escapePipesInTables( content ) );
			// Propagate the change to the Vue model.
			this.$textarea[ 0 ].dispatchEvent( new Event( 'change' ) );
		} );
	}

	/**
	 * Escape pipes in tables where they may confuse the parser.
	 *
	 * This algorithm is far from perfect, but should be satisfactory in most cases.
	 * Known issues include:
	 * * Template calls within a table, and those calls include a pipe at the beginning of a line.
	 * * Complex or multiline use of <nowiki> or <pre>
	 *
	 * Some code adapted from Extension:VEForAll (GPL-2.0-or-later)
	 * See https://w.wiki/AVB5
	 *
	 * @param {string} wikitext
	 * @return {string}
	 */
	escapePipesInTables( wikitext ) {
		const lines = wikitext.split( '\n' );
		let withinTable = false;

		for ( let i = 0; i < lines.length; i++ ) {
			const curLine = lines[ i ];
			// start of table is {|, but could be also escaped, like this: {{{!}}
			if ( curLine.indexOf( '{|' ) === 0 || curLine.indexOf( '{{{!}}' ) === 0 ) {
				withinTable = true;
				lines[ i ] = curLine.replace( /\|/g, '{{!}}' );
			}
			if ( withinTable && ( curLine.indexOf( '|' ) === 0 || curLine.indexOf( '!' ) === 0 ) ) {
				lines[ i ] = curLine.replace( /\|/g, '{{!}}' );
			}
			// Table caption case (`|+`). See https://www.mediawiki.org/wiki/Help:Tables
			if ( withinTable && lines[ i ].includes( '|+' ) ) {
				lines[ i ] = curLine.replace( /\|\+/g, '{{!}}+' );
			}
			// colspan/rowspan case (`|rowspan=`/`|colspan=`). See https://www.mediawiki.org/wiki/Help:Tables
			if ( withinTable && ( curLine.includes( 'colspan' ) || curLine.includes( 'rowspan' ) ) ) {
				lines[ i ] = curLine.replace( /(colspan|rowspan)="(\d+?)"\s*\|/, '$1="$2" {{!}}' )
					.replace( /^\s*\|/, '{{!}} ' );
			}
			if ( withinTable ) {
				// Unescape pipes in <nowiki>, <pre> and in wiki links.
				const chunks = lines[ i ].match( /<nowiki>.*?<\/nowiki>|<pre>.*?<\/pre>|\[\[.*?]]/g ) || [];
				chunks.forEach( ( chunk ) => {
					lines[ i ] = lines[ i ].replace( chunk, chunk.replace( /\{\{!}}/g, '|' ) );
				} );
			}
			if ( curLine.indexOf( '|}' ) === 0 ) {
				withinTable = false;
			}
		}
		return lines.join( '\n' );
	}

	/**
	 * Mimic pending state.
	 *
	 * @param {boolean} pending
	 */
	setPending( pending ) {
		this.$textarea.prop( 'readonly', pending );
		this.$veWrapper.toggleClass( 'ext-communityrequests-intake__textarea-wrapper--loading', pending );
	}
}

module.exports = DescriptionField;
