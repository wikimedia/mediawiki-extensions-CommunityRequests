<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use InvalidArgumentException;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class ApiQueryWishes extends ApiQueryBase {

	private bool $translateInstalled;

	public function __construct(
		ApiQuery $queryModule,
		string $moduleName,
		private readonly WishlistConfig $config,
		private readonly WishStore $store,
		private readonly FocusAreaStore $focusAreaStore,
		private readonly ExtensionRegistry $extensionRegistry,
	) {
		parent::__construct( $queryModule, $moduleName, 'crw' );
		$this->translateInstalled = $this->extensionRegistry->isLoaded( 'Translate' );
	}

	/** @inheritDoc */
	public function execute() {
		$params = $this->extractRequestParams();

		$order = match ( $params['sort'] ) {
			Wish::PARAM_CREATED => WishStore::createdField(),
			Wish::PARAM_UPDATED => WishStore::updatedField(),
			Wish::PARAM_TITLE => WishStore::titleField(),
			Wish::PARAM_VOTE_COUNT => WishStore::voteCountField(),
			default => throw new InvalidArgumentException( 'Invalid sort parameter.' ),
		};

		$offsetArg = null;
		if ( isset( $params['continue'] ) ) {
			$offsetArg = $this->parseContinueParamOrDie( $params['continue'], [ 'string', 'timestamp', 'int' ] );
		}

		$filterArg = array_filter( [
			Wish::PARAM_TAGS => $params[Wish::PARAM_TAGS] ?? null,
			Wish::PARAM_STATUSES => $params[Wish::PARAM_STATUSES] ?? null,
		] );
		if ( isset( $params[Wish::PARAM_FOCUS_AREAS] ) ) {
			$faPageIds = $this->focusAreaStore->getPageIdsFromWikitextValues( $params[Wish::PARAM_FOCUS_AREAS] );
			if ( count( $faPageIds ) !== count( $params[Wish::PARAM_FOCUS_AREAS] ) ) {
				$this->dieWithError( 'apierror-querywishes-invalidfocusareas' );
			}
			$filterArg[WishStore::FILTER_FOCUS_AREAS] = $faPageIds;
		}

		$wikitextFields = array_intersect( $params['prop'], $this->store->getWikitextFields() );
		$wishes = $this->store->getAll(
			$params[Wish::PARAM_LANG] ?? $this->getLanguage()->getCode(),
			$order,
			$params['dir'] === 'ascending' ? WishStore::SORT_ASC : WishStore::SORT_DESC,
			$params['limit'] + 1,
			$offsetArg,
			$filterArg,
			// Fetch fields that only live in wikitext, and only if requested.
			$wikitextFields ? WishStore::FETCH_WIKITEXT_TRANSLATED : WishStore::FETCH_WIKITEXT_NONE,
		);

		$result = $this->getResult();

		foreach ( $wishes as $index => /** @var Wish $wish */ $wish ) {
			'@phan-var Wish $wish';

			// Do this here to avoid unnecessarily fetching wikitext for wishes that won't be returned.
			if ( $index === $params['limit'] ) {
				$timestamp = match ( $params['sort'] ) {
					Wish::PARAM_UPDATED => wfTimestamp( TS_MW, $wish->getUpdated() ),
					default => wfTimestamp( TS_MW, $wish->getCreated() ),
				};
				// We have more results, so set the continue parameter.
				$this->setContinueEnumParameter(
					'continue',
					"{$wish->getTitle()}|$timestamp|{$wish->getVoteCount()}",
				);
				break;
			}

			$wishData = $wish->toArray( $this->config );

			// Only return requested properties.
			$wishData = array_intersect_key( $wishData, array_flip( $params['prop'] ) );

			// Always include the wish ID.
			$wishData = [
				'id' => $wish->getPage()->getId(),
				...$wishData,
			];

			// We want to link to the translated wish page, not the base language page (T401256).
			$pageRef = $this->translateInstalled && $wish->getLang() !== $wish->getBaseLang() ?
				PageReferenceValue::localReference(
					$wish->getPage()->getNamespace(),
					$wish->getPage()->getDBkey() . '/' . $wish->getLang(),
				) :
				$wish->getPage();

			// Add wish page title and namespace.
			ApiQueryBase::addTitleInfo(
				$wishData,
				Title::newFromPageReference( $pageRef ),
				$this->getModulePrefix()
			);

			// Add focus area page title and namespace, if applicable.
			$focusAreaPage = $wish->getFocusAreaPage();
			if ( $focusAreaPage ) {
				'@phan-var PageIdentity $focusAreaPage';
				ApiQueryBase::addTitleInfo(
					$wishData,
					Title::newFromPageIdentity( $focusAreaPage ),
					'crfa'
				);
				// Focus area title is queried separately
				// in order to not join between the CommunityRequests tables and core.
				$focusArea = $this->focusAreaStore->get(
					$focusAreaPage,
					$params[Wish::PARAM_LANG] ?? $this->getLanguage()->getCode()
				);
				if ( $focusArea ) {
					$wishData['focusareatitle'] = $focusArea->getTitle();
				}
			}

			$result->addValue( [ 'query', $this->getModuleName() ], null, $wishData );
		}

		// If the count parameter is set, include the total number of wishes.
		if ( $params['count'] ) {
			$result->addValue(
				[ 'query', "{$this->getModuleName()}-metadata" ],
				'count',
				$this->store->getCount( $filterArg )
			);
		}

		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'wishes' );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		$apiHelpMsgPrefix = 'apihelp-query+communityrequests-wishes-paramvalue';
		// NOTE: Keys should match the Wish::PARAM_* constants where possible.
		return [
			Wish::PARAM_LANG => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			Wish::PARAM_TAGS => [
				ParamValidator::PARAM_TYPE => array_keys( $this->config->getNavigationTags() ),
				ParamValidator::PARAM_ISMULTI => true,
				ApiBase::PARAM_HELP_MSG => 'apihelp-query+communityrequests-wishes-param-tags',
			],
			Wish::PARAM_STATUSES => [
				ParamValidator::PARAM_TYPE => array_keys( $this->config->getStatuses() ),
				ParamValidator::PARAM_ISMULTI => true,
				ApiBase::PARAM_HELP_MSG => 'apihelp-query+communityrequests-wishes-param-statuses',
			],
			Wish::PARAM_FOCUS_AREAS => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_ISMULTI => true,
				ApiBase::PARAM_HELP_MSG => 'apihelp-query+communityrequests-wishes-param-focusareas',
			],
			'prop' => [
				ParamValidator::PARAM_DEFAULT => 'tags|status|type|title|votecount|created|updated',
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					Wish::PARAM_STATUS,
					Wish::PARAM_TYPE,
					Wish::PARAM_TITLE,
					Wish::PARAM_FOCUS_AREA,
					Wish::PARAM_DESCRIPTION,
					Wish::PARAM_AUDIENCE,
					Wish::PARAM_TAGS,
					Wish::PARAM_PHAB_TASKS,
					Wish::PARAM_PROPOSER,
					Wish::PARAM_VOTE_COUNT,
					Wish::PARAM_CREATED,
					Wish::PARAM_UPDATED,
					Wish::PARAM_BASE_LANG,
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [
					Wish::PARAM_STATUS => "$apiHelpMsgPrefix-prop-status",
					Wish::PARAM_TYPE => "$apiHelpMsgPrefix-prop-type",
					Wish::PARAM_TITLE => "$apiHelpMsgPrefix-prop-title",
					Wish::PARAM_FOCUS_AREA => "$apiHelpMsgPrefix-prop-focusarea",
					Wish::PARAM_DESCRIPTION => "$apiHelpMsgPrefix-prop-description",
					Wish::PARAM_AUDIENCE => "$apiHelpMsgPrefix-prop-audience",
					Wish::PARAM_TAGS => "$apiHelpMsgPrefix-prop-tags",
					Wish::PARAM_PHAB_TASKS => "$apiHelpMsgPrefix-prop-phabtasks",
					Wish::PARAM_PROPOSER => "$apiHelpMsgPrefix-prop-proposer",
					Wish::PARAM_VOTE_COUNT => "$apiHelpMsgPrefix-prop-votecount",
					Wish::PARAM_CREATED => "$apiHelpMsgPrefix-prop-created",
					Wish::PARAM_UPDATED => "$apiHelpMsgPrefix-prop-updated",
				],
			],
			'sort' => [
				ParamValidator::PARAM_DEFAULT => Wish::PARAM_CREATED,
				ParamValidator::PARAM_TYPE => [
					Wish::PARAM_CREATED,
					Wish::PARAM_UPDATED,
					Wish::PARAM_TITLE,
					Wish::PARAM_VOTE_COUNT,
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [
					Wish::PARAM_CREATED => "$apiHelpMsgPrefix-sort-created",
					Wish::PARAM_UPDATED => "$apiHelpMsgPrefix-sort-updated",
					Wish::PARAM_TITLE => "$apiHelpMsgPrefix-sort-title",
					Wish::PARAM_VOTE_COUNT => "$apiHelpMsgPrefix-sort-votecount",
				],
			],
			'dir' => [
				ParamValidator::PARAM_DEFAULT => 'descending',
				ParamValidator::PARAM_TYPE => [
					'ascending',
					'descending',
				],
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2,
			],
			'count' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
			],
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}
}
