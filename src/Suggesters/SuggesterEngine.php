<?php


namespace SchemaTreeSuggester\Suggesters;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;

/**
 * interface for (Property-)Suggester
 *
 * @author BP2013N2
 * @license GPL-2.0-or-later
 */
interface SuggesterEngine
{

	/**
	 * Returns suggested attributes
	 *
	 * @param PropertyId[] $propertyIds
	 * @param int $limit
	 * @param float $minProbability
	 * @param string $context
	 * @param string $include One of the self::SUGGEST_* constants
	 * @return Suggestion[]
	 */
	public function suggestByPropertyIds(
		array $propertyIds,
		array $typesIds,
		$limit,
		$minProbability,
		$context
	);

	/**
	 * Returns suggested attributes
	 *
	 * @param string $itemIdString
	 * @param EntityLookup $entityLookup
	 * @param int $limit
	 * @param float $minProbability
	 * @param string $context
	 * @return Suggestion[]
	 */
	public function suggestByItem(
		$itemIdString, $entityLookup, $limit, $minProbability, $context
	);

}
