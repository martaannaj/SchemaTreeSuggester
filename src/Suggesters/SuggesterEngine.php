<?php


namespace SchemaTreeSuggester\Suggesters;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\PropertyId;

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
	 * @param Item $item
	 * @param int $limit
	 * @param float $minProbability
	 * @param string $context
	 * @param string $include One of the self::SUGGEST_* constants
	 * @return Suggestion[]
	 */
	public function suggestByItem(
		Item $item, $limit, $minProbability, $context
	);

}
