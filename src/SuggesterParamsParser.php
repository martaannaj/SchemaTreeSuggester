<?php

namespace SchemaTreeSuggester;

use InvalidArgumentException;


class SuggesterParamsParser {

	/**
	 * @var int
	 */
	private $defaultSuggestionLimit;

	/**
	 * @var float
	 */
	private $defaultMinProbability;

	public function __construct( $defaultSuggestionLimit, $defaultMinProbability ) {
		$this->defaultSuggestionLimit = $defaultSuggestionLimit;
		$this->defaultMinProbability = $defaultMinProbability;
	}
	/**
	 * parses and validates the parameters of GetSuggestion
	 * @param array $params
	 * @return SuggesterParams
	 *@throws InvalidArgumentException
	 */
	public function parseAndValidate( array $params ) {
		$result = new SuggesterParams();

		$result->entity = $params['entity'];
		$result->properties = $params['properties'];
        $result->types = $params['types'];

		if ( !( $result->entity xor $result->properties ) ) {
			throw new InvalidArgumentException(
				"Provide either entity-id parameter 'entity' or a list of properties 'properties'"
			);
		}
		if ( $params['continue'] !== null && !is_numeric( $params['continue'] ) ) {
			throw new InvalidArgumentException( 'Continue must be int!' );
		}

		// The entityselector doesn't allow a search for '' so '*' gets mapped to ''
		if ( $params['search'] !== '*' ) {
			$result->search = trim( $params['search'] );
		} else {
			$result->search = '';
		}

		$result->limit = $params['limit'];
		$result->continue = (int)$params['continue'];
		$result->resultSize = $result->limit + $result->continue;

		if ( $result->resultSize > $this->defaultSuggestionLimit ) {
			$result->resultSize = $this->defaultSuggestionLimit;
		}

		$result->language = $params['language'];
		$result->context = $params['context'];

		if ( $result->search ) {
			// the results matching '$search' can be at the bottom of the list
			// however very low ranked properties are not interesting and can
			// still be found during the merge with search result later.
			$result->suggesterLimit = $this->defaultSuggestionLimit;
			$result->minProbability = 0.0;
		} else {
			$result->suggesterLimit = $result->resultSize;
			$result->minProbability = $this->defaultMinProbability;
		}

		if ( $params['include'] === 'all' ) {
			$result->include = 'all';
		} else {
			$result->include = '';
		}

		return $result;
	}
}
