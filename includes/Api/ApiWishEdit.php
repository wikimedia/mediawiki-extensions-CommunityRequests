<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\UserFactory;
use RuntimeException;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;

class ApiWishEdit extends ApiWishlistEntityBase {

	public function __construct(
		ApiMain $main,
		string $name,
		WishlistConfig $config,
		AbstractWishlistStore $store,
		TitleParser $titleParser,
		protected readonly UserFactory $userFactory,
	) {
		parent::__construct( $main, $name, $config, $store, $titleParser );
	}

	/** @inheritDoc */
	protected function executeWishlistEntity(): void {
		if ( !$this->config->isWishPage( $this->title ) ) {
			$this->dieWithError( 'apierror-wishedit-notawish' );
		}

		$wish = Wish::newFromWikitextParams(
			$this->title,
			// Edits are only made to the base language page.
			$this->params[Wish::PARAM_BASE_LANG],
			[
				...$this->params,
				Wish::PARAM_TAGS => implode( ',', $this->params[Wish::PARAM_TAGS] ),
				Wish::PARAM_PHAB_TASKS => implode( ',', $this->params[Wish::PARAM_PHAB_TASKS] ?? [] ),
			],
			$this->config,
			$this->userFactory->newFromName( $this->params[Wish::PARAM_PROPOSER] ),
		);

		// Confirm we can parse and then re-create the same wikitext.
		$wikitext = $wish->toWikitext( $this->config );
		$validateWish = Wish::newFromWikitextParams(
			$wish->getPage(),
			$wish->getBaseLang(),
			(array)$this->store->getDataFromWikitextContent( $wikitext ),
			$this->config,
			$wish->getProposer(),
		);
		if ( $wikitext->getText() !== $validateWish->toWikitext( $this->config )->getText() ) {
			$this->dieWithError( 'apierror-wishlist-entity-parse' );
		}

		$saveStatus = $this->save(
			$wish,
			$this->params['token'],
			$this->params[Wish::PARAM_BASE_REV_ID] ?? null
		);

		if ( $saveStatus->isOK() === false ) {
			$this->dieWithError( $saveStatus->getMessages()[0] );
		}

		$resultData = $saveStatus->getValue()->getResultData()['edit'];
		// ApiEditPage adds the 'title' key to the result data, but we want to use 'wish'.
		$resultData['wish'] = $resultData['title'];
		unset( $resultData['title'] );
		// 'newtimestamp' should be 'updated'.
		if ( isset( $resultData['newtimestamp'] ) ) {
			$resultData[Wish::PARAM_UPDATED] = $resultData['newtimestamp'];
			unset( $resultData['newtimestamp'] );
		}
		$ret = [
			...$wish->toArray( $this->config ),
			...$resultData
		];
		$this->getResult()->addValue( null, $this->getModuleName(), $ret );
	}

	/** @inheritDoc */
	public function getEditSummary( AbstractWishlistEntity $entity ): string {
		if ( !$entity instanceof Wish ) {
			throw new RuntimeException( 'Expected a Wish object but got a ' . get_class( $entity ) );
		}

		$summary = trim( $this->params['wish'] ?? '' ) ? $this->editSummarySave() : $this->editSummaryPublish();

		// If there are Phabricator tasks, add them to the edit summary.
		if ( count( $entity->getPhabTasks() ) > 0 ) {
			$taskLinks = array_map(
				static fn ( int $taskId ) => "[[phab:T{$taskId}|T{$taskId}]]",
				$entity->getPhabTasks()
			);
			$summary .= ' ' .
				$this->msg( 'parentheses-start' )->text() .
				$this->getLanguage()->commaList( $taskLinks ) .
				$this->msg( 'parentheses-end' )->text();
		}
		return $summary;
	}

	/** @inheritDoc */
	protected function editSummaryPublish(): string {
		return $this->msg( 'communityrequests-publish-wish-summary', $this->params[Wish::PARAM_TITLE] )
			->inContentLanguage()
			->text();
	}

	/** @inheritDoc */
	protected function editSummarySave(): string {
		return $this->msg( 'communityrequests-save-wish-summary', $this->params[Wish::PARAM_TITLE] )
			->inContentLanguage()
			->text();
	}

	/** @inheritDoc */
	protected function getWishlistEntityTitle(): Title {
		if ( isset( $this->params['wish'] ) ) {
			return Title::newFromText(
				$this->config->getWishPagePrefix() .
				$this->store->getIdFromInput( $this->params['wish'] )
			);
		} else {
			// If this is a new wish, generate a new ID and page title.
			$id = $this->store->getNewId();
			return Title::newFromText( $this->config->getWishPagePrefix() . $id );
		}
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		// NOTE: Keys should match the Wish::PARAM_* constants where possible.
		return [
			'wish' => [ ParamValidator::PARAM_TYPE => 'string' ],
			Wish::PARAM_STATUS => [
				ParamValidator::PARAM_TYPE => array_keys( $this->config->getStatuses() ),
				ParamValidator::PARAM_REQUIRED => true,
			],
			Wish::PARAM_FOCUS_AREA => [ ParamValidator::PARAM_TYPE => 'string' ],
			Wish::PARAM_TITLE => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				StringDef::PARAM_MAX_BYTES => WishStore::TITLE_MAX_BYTES,
			],
			Wish::PARAM_DESCRIPTION => [
				ParamValidator::PARAM_TYPE => 'text',
				ParamValidator::PARAM_REQUIRED => true,
			],
			Wish::PARAM_TYPE => [
				ParamValidator::PARAM_TYPE => array_keys( $this->config->getWishTypes() ),
				ParamValidator::PARAM_REQUIRED => true,
			],
			Wish::PARAM_TAGS => [
				ParamValidator::PARAM_TYPE => array_values( array_map(
					fn ( array $tag ) => $this->config->getTagWikitextValFromId( $tag['id'] ),
					$this->config->getNavigationTags()
				) ),
				ParamValidator::PARAM_ISMULTI => true,
			],
			Wish::PARAM_AUDIENCE => [
				ParamValidator::PARAM_TYPE => 'string',
				StringDef::PARAM_MAX_CHARS => WishStore::AUDIENCE_MAX_CHARS,
			],
			Wish::PARAM_PHAB_TASKS => [
				// TODO: maybe make our own TypeDef for Phab tasks?
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_ISMULTI => true,
			],
			Wish::PARAM_PROPOSER => [
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name' ],
				ParamValidator::PARAM_REQUIRED => true,
			],
			Wish::PARAM_CREATED => [
				ParamValidator::PARAM_TYPE => 'timestamp',
				ParamValidator::PARAM_REQUIRED => true,
			],
			Wish::PARAM_BASE_LANG => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			Wish::PARAM_BASE_REV_ID => [
				ParamValidator::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'apihelp-edit-param-baserevid',
			],
		];
	}
}
