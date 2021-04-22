<?php

namespace SchemaTreeSuggester;

use InvalidArgumentException;
use SchemaTreeSuggester\Suggesters\SuggesterEngine;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Repo\Api\EntitySearchHelper;


class SuggestionGenerator {

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var EntitySearchHelper
	 */
	private $entityTermSearchHelper;

	/**
	 * @var SuggesterEngine
	 */
	private $suggester;

	public function __construct(
		EntityLookup $entityLookup,
		EntitySearchHelper $entityTermSearchHelper,
		SuggesterEngine $suggester
	)
	{
		$this->entityLookup = $entityLookup;
		$this->entityTermSearchHelper = $entityTermSearchHelper;
		$this->suggester = $suggester;
	}

	public function generateSuggestionsByItem(
		$itemIdString,
		$limit,
		$minProbability,
		$context
	) : array
	{
//		$itemId = new ItemId( $itemIdString );
//		/** @var Item $item */
//		$item = $this->entityLookup->getEntity( $itemId );
//
//		if ( $item === null ) {
//			throw new InvalidArgumentException( 'Item ' . $itemIdString . ' could not be found' );
//		}

		return $this->suggester->suggestByItem(
			$itemIdString,
			$this->entityLookup,
			$limit,
			$minProbability,
			$context
		);
	}

	public function generateSuggestionsByPropertyList(
		$propertyIdList,
		$typesIdList,
		$limit,
		$minProbability,
		$context
	): array
	{
		return $this->suggester->suggestByPropertyIds(
			$propertyIdList,
			$typesIdList,
			$limit,
			$minProbability,
			$context
		);
	}

	public function filterSuggestions( array $suggestions, $search, $language, $resultSize ) {
		if ( !$search ) {
			return array_slice( $suggestions, 0, $resultSize );
		}

		$searchResults = $this->entityTermSearchHelper->getRankedSearchResults(
			$search,
			$language,
			'property',
			$resultSize,
			true
		);

		$id_set = [];
		foreach ( $searchResults as $searchResult ) {
			// @phan-suppress-next-next-line PhanUndeclaredMethod getEntityId() returns PropertyId
			// as requested above and that implements getNumericId()
			$id_set[$searchResult->getEntityId()->getNumericId()] = true;
		}

		$matching_suggestions = [];
		$count = 0;
		foreach ( $suggestions as $suggestion ) {
			if ( array_key_exists( $suggestion->getPropertyId()->getNumericId(), $id_set ) ) {
				$matching_suggestions[] = $suggestion;
				if ( ++$count === $resultSize ) {
					break;
				}
			}
		}
		return $matching_suggestions;
	}
}
