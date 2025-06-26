<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group CommunityRequests
 * @group Database
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore
 */
class FocusAreaStoreTest extends MediaWikiIntegrationTestCase {
	private FocusAreaStore $focusAreaStore;

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'CommunityRequestsFocusAreaPagePrefix' => 'Community Wishlist/FocusAreas/FA',
		] );
		$this->focusAreaStore = $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );
	}

	/**
	 * @covers ::getFocusArea
	 */
	public function testSaveFocusArea(): void {
		$focusArea = $this->getTestFocusArea();
		$this->focusAreaStore->save( $focusArea );

		// Verify that the focus area was saved correctly
		$savedFocusArea = $this->focusAreaStore->getFocusArea( $focusArea->getPage(), $focusArea->getLanguage() );
		$this->assertEquals( $focusArea, $savedFocusArea );
	}

	private function getTestFocusArea( ?string $title = null ): FocusArea {
		if ( $title !== null ) {
			$title = Title::newFromText( $title );
		} else {
			$title = Title::newFromText( $this->getConfVar( 'CommunityRequestsFocusAreaPagePrefix' ) . '1234' );
		}

		$title = $this->insertPage(
			$title,
			'Page content for Test Focus Area',
		)[ 'title' ];

		return new FocusArea(
			$title,
			'en',
			[
				'shortDescription' => 'Test focus area',
				'title' => 'Test Focus Area',
				'created' => '20250101000000',
				'updated' => '20250101000000',
			]
		);
	}
}
