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

		$order = match ( $params[ 'sort' ] ) {
			'created' => WishStore::createdField(),
			'updated' => WishStore::updatedField(),
			'title' => WishStore::titleField(),
			'votecount' => WishStore::voteCountField(),
			default => throw new InvalidArgumentException( 'Invalid sort parameter.' ),
		};

		$offsetArg = null;
		if ( isset( $params[ 'continue' ] ) ) {
			$offsetArg = $this->parseContinueParamOrDie( $params[ 'continue' ], [ 'string', 'timestamp', 'int' ] );
		}

		$wishes = $this->store->getAll(
			$params[ 'lang' ] ?? $this->getLanguage()->getCode(),
			$order,
			$params[ 'dir' ] === 'ascending' ? WishStore::SORT_ASC : WishStore::SORT_DESC,
			$params[ 'limit' ] + 1,
			$offsetArg
		);

		$result = $this->getResult();

		foreach ( $wishes as $index => /** @var Wish $wish */ $wish ) {
			// Do this here to avoid unnecessarily fetching wikitext for wishes that won't be returned.
			if ( $index === $params[ 'limit' ] ) {
				$timestamp = match ( $params[ 'sort' ] ) {
					'updated' => wfTimestamp( TS_MW, $wish->getUpdated() ),
					default => wfTimestamp( TS_MW, $wish->getCreated() ),
				};
				// We have more results, so set the continue parameter.
				$this->setContinueEnumParameter(
					'continue',
					"{$wish->getTitle()}|$timestamp|{$wish->getVoteCount()}",
				);
				break;
			}

			$wishData = $wish->toArray( $this->config, true );

			// Fill in fields that only live in wikitext, if requested.
			$wikitextFields = array_intersect( $params[ 'prop' ], $this->store->getWikitextFields() );
			if ( count( $wikitextFields ) ) {
				$wikitextData = $this->store->getDataFromWikitext( $wish->getPage()->getId() );

				// TODO: strip out <translate> tags and translation markers.
				foreach ( $wikitextFields as $field ) {
					$wishData[ $field ] = $wikitextData[ $field ] ?? '';
				}
			}

			// Only return requested properties.
			$wishData = array_intersect_key( $wishData, array_flip( $params[ 'prop' ] ) );

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
			if ( $wish->getFocusArea() ) {
				ApiQueryBase::addTitleInfo(
					$wishData,
					Title::newFromPageIdentity( $wish->getFocusArea() ),
					'crfa'
				);
				// TODO: Include this in the WishStore::getAll() query.
				$focusArea = $this->focusAreaStore->get(
					$wish->getFocusArea(),
						$params[ 'lang' ] ?? $this->getLanguage()->getCode()
				);
				$wishData['focusareatitle'] = $focusArea->getTitle();
			}

			$result->addValue( [ 'query', $this->getModuleName() ], null, $wishData );
		}

		// If the count parameter is set, include the total number of wishes.
		if ( $params[ 'count' ] ) {
			$result->addValue(
				[ 'query', "{$this->getModuleName()}-metadata" ],
				'count',
				$this->store->getCount()
			);
		}

		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'wishes' );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		$apiHelpMsgPrefix = 'apihelp-query+communityrequests-wishes-paramvalue';
		return [
			'lang' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'prop' => [
				ParamValidator::PARAM_DEFAULT => 'status|type|title|votecount|created|updated',
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'status',
					'type',
					'title',
					'focusarea',
					'description',
					'audience',
					'projects',
					'otherproject',
					'phabtasks',
					'proposer',
					'votecount',
					'created',
					'updated',
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [
					'status' => "$apiHelpMsgPrefix-prop-status",
					'type' => "$apiHelpMsgPrefix-prop-type",
					'title' => "$apiHelpMsgPrefix-prop-title",
					'focusarea' => "$apiHelpMsgPrefix-prop-focusarea",
					'description' => "$apiHelpMsgPrefix-prop-description",
					'audience' => "$apiHelpMsgPrefix-prop-audience",
					'projects' => "$apiHelpMsgPrefix-prop-projects",
					'otherproject' => "$apiHelpMsgPrefix-prop-otherproject",
					'phabtasks' => "$apiHelpMsgPrefix-prop-phabtasks",
					'proposer' => "$apiHelpMsgPrefix-prop-proposer",
					'votecount' => "$apiHelpMsgPrefix-prop-votecount",
					'created' => "$apiHelpMsgPrefix-prop-created",
					'updated' => "$apiHelpMsgPrefix-prop-updated",
				],
			],
			'sort' => [
				ParamValidator::PARAM_DEFAULT => 'created',
				ParamValidator::PARAM_TYPE => [
					'created',
					'updated',
					'title',
					'votecount',
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [
					'created' => "$apiHelpMsgPrefix-sort-created",
					'updated' => "$apiHelpMsgPrefix-sort-updated",
					'title' => "$apiHelpMsgPrefix-sort-title",
					'votecount' => "$apiHelpMsgPrefix-sort-votecount",
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
