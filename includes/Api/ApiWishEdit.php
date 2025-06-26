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
			$this->params[ 'baselang' ],
			[
				...$this->params,
				'projects' => implode( ',', $this->params[ 'projects' ] ),
				'phabtasks' => implode( ',', $this->params[ 'phabtasks' ] ?? [] ),
			],
			$this->config,
			$this->userFactory->newFromName( $this->params[ 'proposer' ] ),
		);
		$wishTemplate = $this->titleParser->parseTitle( $this->config->getWishTemplatePage() );
		$saveStatus = $this->save(
			$wish->toWikitext( $wishTemplate, $this->config ),
			$this->getEditSummary( $wish, $this->params ),
			$this->params[ 'token' ],
			$this->params[ 'baserevid' ] ?? null
		);

		if ( $saveStatus->isOK() === false ) {
			$this->dieWithError( $saveStatus->getMessages()[0] );
		}

		$resultData = $saveStatus->getValue()->getResultData()[ 'edit' ];
		// ApiEditPage adds the 'title' key to the result data, but we want to use 'wish'.
		$resultData[ 'wish' ] = $resultData[ 'title' ];
		unset( $resultData[ 'title' ] );
		// 'newtimestamp' should be 'updated'.
		$resultData[ 'updated' ] = $resultData[ 'newtimestamp' ];
		unset( $resultData[ 'newtimestamp' ] );
		$ret = [
			...$wish->toArray( $this->config, true ),
			...$resultData
		];
		$this->getResult()->addValue( null, $this->getModuleName(), $ret );
	}

	/** @inheritDoc */
	public function getEditSummary( AbstractWishlistEntity $entity, array $params ): string {
		if ( !$entity instanceof Wish ) {
			throw new RuntimeException( 'Expected a Wish object but got a ' . get_class( $entity ) );
		}

		$summary = trim( $params[ 'wish' ] ?? '' ) ? $this->editSummaryPublish() : $this->editSummarySave();

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
		return $this->msg( 'communityrequests-publish-wish-summary' )
			->inContentLanguage()
			->text();
	}

	/** @inheritDoc */
	protected function editSummarySave(): string {
		return $this->msg( 'communityrequests-save-wish-summary' )
			->inContentLanguage()
			->text();
	}

	/** @inheritDoc */
	protected function getWishlistEntityTitle( array $params ): Title {
		if ( isset( $params[ 'wish' ] ) ) {
			return Title::newFromText(
				$this->config->getWishPagePrefix() .
				$this->store->getIdFromInput( $params[ 'wish' ] )
			);
		} else {
			// If this is a new wish, generate a new ID and page title.
			$id = $this->store->getNewId();
			return Title::newFromText( $this->config->getWishPagePrefix() . $id );
		}
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		// NOTE: Keys should match the Wish::TAG_ATTR_* constants where possible.
		return [
			'wish' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'status' => [
				ParamValidator::PARAM_TYPE => array_keys( $this->config->getStatuses() ),
				ParamValidator::PARAM_REQUIRED => true,
			],
			'focusarea' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				StringDef::PARAM_MAX_BYTES => WishStore::TITLE_MAX_BYTES,
			],
			'description' => [
				ParamValidator::PARAM_TYPE => 'text',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'type' => [
				ParamValidator::PARAM_TYPE => array_keys( $this->config->getWishTypes() ),
				ParamValidator::PARAM_REQUIRED => true,
			],
			'projects' => [
				ParamValidator::PARAM_TYPE => [
					Wish::TEMPLATE_VALUE_PROJECTS_ALL,
					...array_values( array_map(
						fn ( array $project ) => $this->config->getProjectWikitextValFromId( $project[ 'id' ] ),
						$this->config->getProjects()
					) )
				],
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => true,
			],
			'otherproject' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'audience' => [
				ParamValidator::PARAM_TYPE => 'string',
				StringDef::PARAM_MAX_CHARS => WishStore::AUDIENCE_MAX_CHARS,
			],
			'phabtasks' => [
				// TODO: maybe make our own TypeDef for Phab tasks?
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_ISMULTI => true,
			],
			'proposer' => [
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name' ],
				ParamValidator::PARAM_REQUIRED => true,
			],
			'created' => [
				ParamValidator::PARAM_TYPE => 'timestamp',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'baselang' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'baserevid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'apihelp-edit-param-baserevid',
			],
		];
	}
}
