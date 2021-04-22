<?php

namespace SchemaTreeSuggester\Suggesters;

use InvalidArgumentException;
use LogicException;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * a Suggester implementation that creates suggestion via MySQL
 * Needs the wbs_propertypairs table filled with pair probabilities.
 *
 * @author BP2013N2
 * @license GPL-2.0-or-later
 */
class SimpleSuggester implements SuggesterEngine {

	/**
	 * @var int[]
	 */
	private $deprecatedPropertyIds = [];

	/**
	 * @var array Numeric property ids as keys, values are meaningless.
	 */
	private $classifyingPropertyIds = [];

	/**
	 * @var Suggestion[]
	 */
	private $initialSuggestions = [];

	/**
	 * @var string
	 */
	private $recommenderUrl;

	/**
	 * @var ILoadBalancer
	 */
	private $lb;

	public function __construct(string $url, ILoadBalancer $lb) {
		$this->recommenderUrl = $url;
		$this->lb = $lb;
	}

	/**
	 * @param int[] $deprecatedPropertyIds
	 */
	public function setDeprecatedPropertyIds( array $deprecatedPropertyIds ) {
		$this->deprecatedPropertyIds = $deprecatedPropertyIds;
	}

	/**
	 * @param int[] $classifyingPropertyIds
	 */
	public function setClassifyingPropertyIds( array $classifyingPropertyIds ) {
		$this->classifyingPropertyIds = array_flip( $classifyingPropertyIds );
	}

	/**
	 * @param int[] $initialSuggestionIds
	 */
	public function setInitialSuggestions( array $initialSuggestionIds ) {
		$suggestions = [];
		foreach ( $initialSuggestionIds as $id ) {
			$suggestions[] = new Suggestion( PropertyId::newFromNumber( $id ), 1.0 );
		}

		$this->initialSuggestions = $suggestions;
	}

	/**
	 * @param string[] $propertyIds
	 * @param string[] $typesIds
	 * @param int $limit
	 * @param float $minProbability
	 * @param string $context
	 * @param string $include
	 * @throws InvalidArgumentException
	 * @return Suggestion[]
	 */
	private function getSuggestions(
		$propertyIds,
		$typesIds,
		$limit,
		$minProbability,
		$context // will be used if qualifier suggestions are also implemented
	) {
		if ( !is_int( $limit ) ) {
			throw new InvalidArgumentException( '$limit must be int!' );
		}
		if ( !is_float( $minProbability ) ) {
			throw new InvalidArgumentException( '$minProbability must be float!' );
		}
		if ( !$propertyIds ) {
			return $this->initialSuggestions;
		}

		$properties = [];
		foreach($propertyIds as $id) {
			$properties[] = "http://www.wikidata.org/prop/direct/" . $this->getOriginalID($id); //TODO: probably also a config parameter
		}


		$types = [];
		if ($typesIds !== null) {
			foreach ($typesIds as $id) {
				$types[] = "http://www.wikidata.org/entity/" . $this->getOriginalID($id); //TODO: probably also a config parameter
			}
		}

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_POST, 'GET');
		$url = $this->recommenderUrl;
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

		return $this->buildResult( $result );
	}

	private function getOriginalID($id){
		$dbr = $this->lb->getConnection( DB_REPLICA );
		$res = $dbr->select(
			'wbs_entity_mapping',
			[
				'id' => 'wbs_original_id'
			],
			['wbs_local_id' => $id],
			__METHOD__
		);

		$this->lb->reuseConnection( $dbr );
		$results = [];
		foreach($res as $re) {
			$results[] = $re->id;
		}
		return $results[0];
	}

	private function getLocalId($id){
		$dbr = $this->lb->getConnection( DB_REPLICA );
		$res = $dbr->select(
			'wbs_entity_mapping',
			[
				'id' => 'wbs_local_id'
			],
			['wbs_original_id' => $id],
			__METHOD__
		);

		$this->lb->reuseConnection( $dbr );
		$results = [];
		foreach($res as $re) {
			$results[] = $re->id;
		}
		return $results[0];
	}

	/**
	 * @see SuggesterEngine::suggestByPropertyIds
	 *
	 * @param string[] $propertyIds
	 * @param int $limit
	 * @param float $minProbability
	 * @param string $context
	 * @return Suggestion[]
	 */
	public function suggestByPropertyIds(
		$propertyIds,
		$typesIds,
		$limit,
		$minProbability,
		$context
	) {
		return $this->getSuggestions(
			$propertyIds,
			$typesIds,
			$limit,
			$minProbability,
			$context
		);
	}

	/**
	 * @param string $itemIdString
	 * @param int $limit
	 * @param float $minProbability
	 * @param string $context
	 * @return Suggestion[]
	 *@throws LogicException
	 * @see SuggesterEngine::suggestByEntity
	 *
	 */
	public function suggestByItem(
		$itemIdString,
		$entityLookup,
		$limit,
		$minProbability,
		$context
	) {
		$itemId = new ItemId( $this->getLocalId($itemIdString) );
		/** @var Item $item */
		$item = $entityLookup->getEntity( $itemId );

		if ( $item === null ) {
			throw new InvalidArgumentException( 'Item ' . $itemIdString . ' could not be found' );
		}
		$propertyIds = array();
		$typesIds = array();
		foreach ( $item->getStatements()->toArray() as $statement ) {
			// TODO: verify what values are actually returned here
			$mainSnak = $statement->getMainSnak();
			$numericPropertyId = $mainSnak->getPropertyId()->getNumericId();
			array_push($propertyIds, 'P' . $numericPropertyId);
			if (isset( $this->classifyingPropertyIds[$numericPropertyId] ) ) {
				$dataValue = $mainSnak->getDataValue();
				if ( !( $dataValue instanceof EntityIdValue ) ) {
					throw new LogicException(
						"Property $numericPropertyId in wgPropertySuggesterClassifyingPropertyIds"
						. ' does not have value type wikibase-entityid'
					);
				}

				$entityId = $dataValue->getEntityId();

				if ( !( $entityId instanceof ItemId ) ) {
					throw new LogicException(
						"PropertyValueSnak for $numericPropertyId, configured in " .
						' wgPropertySuggesterClassifyingPropertyIds, has an unexpected value ' .
						'and data type (not wikibase-item).'
					);
				}
				$numericEntityId = $entityId->getNumericId();
				array_push($typesIds, 'Q' . $numericEntityId);
			}
		}

		return $this->getSuggestions(
			$propertyIds,
			$typesIds,
			$limit,
			$minProbability,
			$context
		);
	}


	/**
	 * Converts the rows of the SQL result to Suggestion objects
	 *
	 * @param array $result
	 * @return Suggestion[]
	 */
	private function buildResult( array $result ) {
		$resultArray = [];
		foreach ( $result as $res) {
			if(strpos($res["property"], "/prop/direct/")) {
				$id = explode("/", $res["property"]);
				$id = $this->getLocalId($id[count($id) - 1]);
				$pid = PropertyId::newFromNumber((int)substr($id, 1));
				$suggestion = new Suggestion($pid, $res["probability"]);
				$resultArray[] = $suggestion;
			}
		}
		return $resultArray;
	}

}
