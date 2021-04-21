<?php

namespace SchemaTreeSuggester;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Repo\Api\EntitySearchHelper;

/** TODO:
 * This class will make the curl call to the /lean-recommender
 * To get the properties + their probabilities
 * There should be the following:
 * main function to make the curl call
 * a function to filter the suggestions based on search value
 * probably a later TODO is to scale for languages in this function
 **/
class RecommendationGenerator {

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var EntitySearchHelper
	 */
	private $entityTermSearchHelper;

	public function __construct(
		EntityLookup $entityLookup,
		EntitySearchHelper $entityTermSearchHelper
	)
	{
		$this->entityLookup = $entityLookup;
		$this->entityTermSearchHelper = $entityTermSearchHelper;
	}

	public function generateSuggestionsByPropertyList(
		$properties,
//		$types,
		$suggesterLimit, // add that the schema tree recommender takes this as a parameter (?)
		$minProbability
	): array
	{
		return $this->generateRecommendations(
			$properties,
//			$types,
			$suggesterLimit,
			$minProbability
		);
	}

	public function generateSuggestionsByItemId(
		$entity,
		$suggesterLimit,
		$minProbability
	) : array
	{
		$itemId = new ItemId( $entity );
		/** @var Item $item */
		$item = $this->entityLookup->getEntity( $itemId );

		if ( $item === null ) {
			throw new InvalidArgumentException( 'Item ' . $entity . ' could not be found' );
		}

		$properties = array();
		$types = array();
		foreach ( $item->getStatements()->toArray() as $statement ) {
			// TODO: double check what values this actually returns
			$mainSnak = $statement->getMainSnak();
			$numericPropertyId = $mainSnak->getPropertyId()->getNumericId();
			array_push($properties, $numericPropertyId);
		}
		//TODO: get the types as well

		return $this->generateRecommendations(
			$properties,
//			$types,
			$suggesterLimit,
			$minProbability
		);
	}

	// TODO: maybe put this in a separate class (the same as the Property suggester)
	private function generateRecommendations(
		$properties,
//		$types,
		$suggesterLimit, // add that the schema tree recommender takes this as a parameter (?)
		$minProbability
	): array
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_POST, 'GET');
		$url = "http://localhost:9090/lean-recommender";
		$data_array =  array(
			"Properties" => $properties,
			"Types" => array()
		);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data_array));
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
		));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

		$result = curl_exec($curl);
		if(!$result){die("Connection Failure");}
		curl_close($curl);

		$result = json_decode( $result, true )['recommendations'];
		$recommendations = array();
		foreach($result as $res) {
			if ($res['probability'] >= $minProbability) {
				array_push($recommendations, $res);
			}
		}

		return $recommendations;
	}

}
