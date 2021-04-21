<?php

namespace SchemaTreeSuggester;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Repo\Api\EntitySearchHelper;


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
		$types,
		$suggesterLimit, // add that the schema tree recommender takes this as a parameter (?)
		$minProbability
	): array
	{
		return $this->generateRecommendations(
			$properties,
			$types,
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
			$types,
			$suggesterLimit,
			$minProbability
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

	// TODO: maybe put this in a separate class (the same as the Property suggester)
	private function generateRecommendations(
		$properties,
		$types,
		$suggesterLimit, // add that the schema tree recommender takes this as a parameter (?)
		$minProbability
	): array
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_POST, 'GET');
		$url = "http://localhost:9090/lean-recommender";
		$data_array =  array(
			"Properties" => $properties,
			"Types" => $types
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
		$resultArray = [];
		foreach ( $result as $res) {
			if(strpos($res["property"], "/prop/direct/")) {
				$id = explode("/", $res["property"]);
				$pid = PropertyId::newFromNumber((int)substr($id[count($id) - 1], 1));
				$suggestion = new Suggestion($pid, $res["probability"]);
				$resultArray[] = $suggestion;
			}
		}
		return $resultArray;
	}

}
