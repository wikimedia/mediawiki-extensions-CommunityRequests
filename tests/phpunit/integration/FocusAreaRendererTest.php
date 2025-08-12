<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Title\Title;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaRenderer
 */
class FocusAreaRendererTest extends CommunityRequestsIntegrationTestCase {

	protected function getStore(): FocusAreaStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );
	}

	/**
	 * Test that a focus area can be created from a wiki page.
	 *
	 */
	public function testCreateFocusAreaFromWikiPage(): void {
		$wikitext = <<<END
{{#CommunityRequests: focus-area
|status = open
|title = Test Focus Area
|shortdescription = A brief description of the focus area.
|created = 2023-10-01T12:00:00Z
|baselang = en
|description = Focus on improving the user experience.}}
END;
		$ret = $this->insertPage(
			Title::newFromText( $this->config->getFocusAreaPagePrefix() . '123' ),
			$wikitext,
			NS_MAIN,
			$this->getTestUser()->getUser()
		);

		$focusArea = $this->getStore()->get( $ret['title'] );
		$this->assertSame( $ret['id'], $focusArea->getPage()->getId() );
		$this->assertSame( $this->config->getStatusIdFromWikitextVal( 'open' ), $focusArea->getStatus() );
		$this->assertSame( 'Test Focus Area', $focusArea->getTitle() );
		$this->assertSame( 'A brief description of the focus area.', $focusArea->getShortDescription() );
		$this->assertSame( '2023-10-01T12:00:00Z', $focusArea->getCreated() );
	}
}
