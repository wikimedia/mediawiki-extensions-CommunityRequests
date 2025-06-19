<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Extension\CommunityRequests\Wish\SpecialWishlistIntake;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\UserFactory;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;

class ApiWishEdit extends ApiBase {

	protected const EDIT_SUMMARY_PUBLISH = 'communityrequests-publish-wish-summary';
	protected const EDIT_SUMMARY_SAVE = 'communityrequests-save-wish-summary';

	public function __construct(
		ApiMain $main,
		string $name,
		private readonly WishlistConfig $config,
		private readonly WishStore $wishStore,
		private readonly UserFactory $userFactory,
		private readonly TitleParser $titleParser
	) {
		parent::__construct( $main, $name );
	}

	/** @inheritDoc */
	public function execute() {
		if ( !$this->config->isEnabled() ) {
			$this->dieWithError( 'communityrequests-disabled' );
		}
		$params = $this->extractRequestParams();

		if ( isset( $params[ 'wish' ] ) ) {
			$title = Title::newFromText(
				$this->config->getWishPagePrefix() .
				$this->wishStore->getWishIdFromInput( $params[ 'wish' ] )
			);
		} else {
			// If this is a new wish, generate a new ID and page title.
			$id = $this->wishStore->getNewId();
			$title = Title::newFromText( $this->config->getWishPagePrefix() . $id );
		}
		if ( !$title ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params[ 'wish' ] ) ] );
		} elseif ( !$title->canExist() ) {
			$this->dieWithError( 'apierror-pagecannotexist' );
		} elseif ( !$this->wishStore->isWishPage( $title ) ) {
			$this->dieWithError( 'apierror-wishedit-notawish' );
		}

		$this->getErrorFormatter()->setContextTitle( $title );

		$wish = Wish::newFromWikitextParams(
			$title,
			// Edits are only made to the base language page.
			$title->getPageLanguage()->getCode(),
			$this->userFactory->newFromName( $params[ 'proposer' ] ),
			[
				...$params,
				'projects' => implode( ',', $params[ 'projects' ] ),
				'phabtasks' => implode( ',', $params[ 'phabtasks' ] ),
			],
			$this->config
		);

		$wishTemplate = $this->titleParser->parseTitle( $this->config->getWishTemplatePage() );
		$saveStatus = $this->save(
			Title::newFromPageIdentity( $wish->getPage() ),
			$wish->toWikitext( $wishTemplate, $this->config ),
			$this->getEditSummary( $wish, $params ),
			$params[ 'token' ],
			$params[ 'baserevid' ] ?? null
		);

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

	public function getEditSummary( Wish $wish, array $params ): string {
		$isNew = $this->getRequest()->getSession()->get( SpecialWishlistIntake::SESSION_KEY )
			=== SpecialWishlistIntake::SESSION_VALUE_WISH_CREATED;
		$summary = $this->msg(
			$isNew ? self::EDIT_SUMMARY_PUBLISH : self::EDIT_SUMMARY_SAVE,
			$params[ 'title' ]
		)->text();

		// If there are Phabricator tasks, add them to the edit summary.
		if ( count( $wish->getPhabTasks() ) > 0 ) {
			$taskLinks = array_map(
				static fn ( int $taskId ) => "[[phab:T{$taskId}|T{$taskId}]]",
				$wish->getPhabTasks()
			);
			$summary .= ' ' .
				$this->msg( 'parentheses-start' )->text() .
				$this->getLanguage()->commaList( $taskLinks ) .
				$this->msg( 'parentheses-end' )->text();
		}
		return $summary;
	}

	/**
	 * Save the wish content to the wish page.
	 * WishHookHandler will handle updating the CommunityRequests tables.
	 *
	 * @param Title $wishPage
	 * @param WikitextContent $content
	 * @param string $summary
	 * @param string $token
	 * @param ?int $baseRevId
	 * @param string[] $tags
	 * @return Status
	 */
	private function save(
		Title $wishPage,
		WikitextContent $content,
		string $summary,
		string $token,
		?int $baseRevId = null,
		array $tags = []
	): Status {
		$apiParams = [
			'action' => 'edit',
			'title' => $wishPage->getPrefixedDBkey(),
			'text' => $content->getText(),
			'summary' => $summary,
			'token' => $token,
			'baserevid' => $baseRevId,
			'tags' => implode( '|', $tags ),
			'errorformat' => 'html',
			'notminor' => true,
		];

		$context = new DerivativeContext( $this->getContext() );
		$context->setRequest( new DerivativeRequest( $this->getRequest(), $apiParams ) );
		$api = new ApiMain( $context, true );

		// FIXME: make use of EditFilterMergedContent hook to impose our own edit checks
		//   (Status will show up in SpecialFormPage) Such as a missing proposer or invalid creation date.
		$api->execute();
		return Status::newGood( $api->getResult() );
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
				ParamValidator::PARAM_DEFAULT => [],
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
			'baserevid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'apihelp-edit-param-baserevid',
			],
		];
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}
}
