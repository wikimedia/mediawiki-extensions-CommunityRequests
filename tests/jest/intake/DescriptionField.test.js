'use strict';

const DescriptionField = require( '../../../modules/intake/DescriptionField.js' );
const textarea = document.createElement( 'textarea' );

describe( 'DescriptionField', () => {
	it( 'should escape pipes in tables', () => {
		const content = `{| class="wikitable"
|+ Caption
|-
! style="width: 50%;"|You type
! style="width: 50%;"|You get
|-
! Heading 1
| [[Foo|bar]] | Baz
|-
| {{!}} Qux <nowiki>|</nowiki> Corge <pre>|</pre> Grault
| style="padding: 10px;"| <big><nowiki>|+</nowiki></big>
|-
|}`;
		const descriptionField = new DescriptionField( textarea, content, jest.fn() );
		expect( descriptionField.escapePipesInTables( content ) )
			.toBe( `{{{!}} class="wikitable"
{{!}}+ Caption
{{!}}-
! style="width: 50%;"{{!}}You type
! style="width: 50%;"{{!}}You get
{{!}}-
! Heading 1
{{!}} [[Foo|bar]] {{!}} Baz
{{!}}-
{{!}} {{!}} Qux <nowiki>|</nowiki> Corge <pre>|</pre> Grault
{{!}} style="padding: 10px;"{{!}} <big><nowiki>|+</nowiki></big>
{{!}}-
{{!}}}` );
	} );
} );
