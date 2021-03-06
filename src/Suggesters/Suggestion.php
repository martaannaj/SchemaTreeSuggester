<?php


namespace SchemaTreeSuggester\Suggesters;

use Wikibase\DataModel\Entity\PropertyId;


class Suggestion
{

	/**
	 * @var PropertyId
	 */
	private $propertyId;

	/**
	 * @var float
	 * average probability that an already existing property is used with the suggested property
	 */
	private $probability;

	/**
	 * @param PropertyId $propertyId
	 * @param float $probability
	 */
	public function __construct(PropertyId $propertyId, $probability)
	{
		$this->propertyId = $propertyId;
		$this->probability = $probability;
	}

	/**
	 * @return PropertyId
	 */
	public function getPropertyId()
	{
		return $this->propertyId;
	}

	/**
	 * average probability that an already existing property is used with the suggested property
	 * @return float
	 */
	public function getProbability()
	{
		return $this->probability;
	}

}
