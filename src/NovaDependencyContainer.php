<?php

namespace elipZis\NovaDependencyContainer;

use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 */
class NovaDependencyContainer extends Field {
	/**
	 * The field's component.
	 *
	 * @var string
	 */
	public $component = 'nova-dependency-container';

	/**
	 * @var bool
	 */
	public $showOnIndex = false;

	/**
	 * NovaDependencyContainer constructor.
	 *
	 * @param      $fields
	 * @param null $attribute
	 * @param null $resolveCallback
	 */
	public function __construct($fields, $attribute = null, $resolveCallback = null) {
		parent::__construct('', $attribute, $resolveCallback);

		$this->withMeta(['fields' => $fields]);
		$this->withMeta(['dependencies' => []]);
	}

	/**
	 * Adds a dependency
	 *
	 * @param $field
	 * @param $value
	 * @return $this
	 */
	public function dependsOn($field, $value) {
		return $this->withMeta([
			'dependencies' => array_merge($this->meta['dependencies'], [['field' => $field, 'value' => $value]])
		]);
	}

	/**
	 *
	 *
	 * @param $field
	 * @return NovaDependencyContainer
	 */
	public function dependsOnNotEmpty($field) {
		return $this->withMeta([
			'dependencies' => array_merge($this->meta['dependencies'], [['field' => $field, 'notEmpty' => true]])
		]);
	}

	/**
	 * @param mixed $resource
	 * @param null  $attribute
	 */
	public function resolveForDisplay($resource, $attribute = null) {
		parent::resolveForDisplay($resource, $attribute);

		foreach($this->meta['dependencies'] as $index => $dependency) {
			$this->meta['dependencies'][$index]['satisfied'] = $this->isSatisfied($dependency, $resource->{$dependency['field']} ?? null) > 0;
		}
	}

	/**
	 * Retrieve values of dependency fields
	 *
	 * @param mixed  $resource
	 * @param string $attribute
	 * @return array|mixed
	 */
	protected function resolveAttribute($resource, $attribute) {
		if($this->isDependenciesSatisfied($resource)) {
			foreach($this->meta['fields'] as $field) {
				$field->resolve($resource);
			}
		}

		return [];
	}

	/**
	 * Fills the attributes of the model within the container if the dependencies for the container are satisfied.
	 *
	 * @param NovaRequest $request
	 * @param string      $requestAttribute
	 * @param object      $model
	 * @param string      $attribute
	 */
	protected function fillAttributeFromRequest(NovaRequest $request, $requestAttribute, $model, $attribute) {
		foreach($this->meta['fields'] as $field) {
			$field->fill($request, $model);
		}
	}

	/**
	 * @param      $dependency
	 * @param null $field
	 * @return int Zero if not satisfied, otherwise the count of "satisfaction"
	 */
	protected function isSatisfied($dependency, $field = null) {
		$retVal = 0;
		if(array_key_exists('notEmpty', $dependency) && $field) {
			$retVal++;
		}
		if(array_key_exists('value', $dependency) && $dependency['value'] == $field) {
			$retVal++;
		}
		return $retVal;
	}

	/**
	 * @return bool
	 */
	protected function isDependenciesSatisfied($resource) {
		if(!isset($this->meta['dependencies'])
			|| !is_array($this->meta['dependencies'])) {
			return false;
		}

		$satisfiedCounts = 0;
		foreach($this->meta['dependencies'] as $index => $dependency) {
			$satisfiedCounts += $this->isSatisfied($dependency, $resource->{$dependency['field']} ?? $resource->get($dependency['field']) ?? null);
		}

		return $satisfiedCounts == count($this->meta['dependencies']);
	}

	/**
	 * Get a rule set based on field property name
	 *
	 * @param NovaRequest $request
	 * @param string      $propertyName
	 * @return array
	 */
	protected function getSituationalRulesSet(NovaRequest $request, string $propertyName = 'rules') {
		$fieldsRules = [];
		if(!$this->isDependenciesSatisfied($request)
			|| !isset($this->meta['fields'])
			|| !is_array($this->meta['fields'])) {
			return $fieldsRules;
		}

		/** @var Field $field */
		foreach($this->meta['fields'] as $field) {
			$fieldsRules[$field->attribute] = is_callable($field->{$propertyName})
				? call_user_func($field->{$propertyName}, $request)
				: $field->{$propertyName};
		}

		return $fieldsRules;
	}

	/**
	 * Get the validation rules for this field.
	 *
	 * @param NovaRequest $request
	 * @return array
	 */
	public function getRules(NovaRequest $request) {
		return $this->getSituationalRulesSet($request);
	}

	/**
	 * Get the creation rules for this field.
	 *
	 * @param NovaRequest $request
	 * @return array|string
	 */
	public function getCreationRules(NovaRequest $request) {
		$fieldsRules = $this->getSituationalRulesSet($request, 'creationRules');

		return array_merge_recursive(
			$this->getRules($request),
			$fieldsRules
		);
	}

	/**
	 * Get the update rules for this field.
	 *
	 * @param NovaRequest $request
	 * @return array
	 */
	public function getUpdateRules(NovaRequest $request) {
		$fieldsRules = $this->getSituationalRulesSet($request, 'updateRules');

		return array_merge_recursive(
			$this->getRules($request),
			$fieldsRules
		);
	}
}
