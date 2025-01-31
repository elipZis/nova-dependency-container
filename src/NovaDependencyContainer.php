<?php

namespace Epartment\NovaDependencyContainer;

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
			'dependencies' => array_merge($this->meta['dependencies'], [
				$this->getFieldLayout($field, $value)
			])
		]);
	}

	/**
	 * Adds a dependency for not empty
	 *
	 * @param $field
	 * @return NovaDependencyContainer
	 */
	public function dependsOnEmpty($field) {
		return $this->withMeta([
			'dependencies' => array_merge($this->meta['dependencies'], [
				array_merge($this->getFieldLayout($field), ['empty' => true])
			])
		]);
	}

	/**
	 * Adds a dependency for not empty
	 *
	 * @param $field
	 * @return NovaDependencyContainer
	 */
	public function dependsOnNotEmpty($field) {
		return $this->withMeta([
			'dependencies' => array_merge($this->meta['dependencies'], [
				array_merge($this->getFieldLayout($field), ['notEmpty' => true])
			])
		]);
	}

	/**
	 * Adds a dependency for null or zero (0)
	 *
	 * @param $field
	 * @param $value
	 * @return $this
	 */
	public function dependsOnNullOrZero($field) {
		return $this->withMeta([
			'dependencies' => array_merge($this->meta['dependencies'], [
				array_merge($this->getFieldLayout($field), ['nullOrZero' => true])
			])
		]);
	}

	/**
	 * Get layout for a specified field. Dot notation will result in {field}.{property}. If no dot was found it will
	 * result in {field}.{field}, as it was in previous versions by default.
	 *
	 * @param $field
	 * @param $value
	 * @return array
	 */
	protected function getFieldLayout($field, $value = null) {
		if(count(($field = explode('.', $field))) === 1) {
			// backwards compatibility, property becomes field
			$field[1] = $field[0];
		}
		return [
			// literal form input name
			'field'    => $field[0],
			// property to compare
			'property' => $field[1],
			// value to compare
			'value'    => $value,
		];
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
		if($this->areDependenciesSatisfied($resource)) {
			foreach($this->meta['fields'] as $field) {
				switch($field->component) {
					// callback is set for `resolve` in relation
					case 'belongs-to-field':
					case 'morph-to-field':
						$field->resolve($resource);
						break;
					// default callbacks are for resolveForDisplay, and if implemented correctly, should call `resolve`
					// at the end of the chain
					// @todo: at all packaged incompatible with `resolveForDisplay()`
					default:
						$field->resolveForDisplay($resource);
				}
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
		if (array_key_exists('empty', $dependency) && empty($field)) {
			$retVal++;
		}
		// inverted
		if (array_key_exists('nullOrZero', $dependency) && in_array($field, [null, 0, '0'], true)) {
			$retVal++;

		}
		return $retVal;
	}

	/**
	 * Checks whether to add validation rules
	 *
	 * @param mixed $resource
	 * @return bool
	 */
	public function areDependenciesSatisfied($resource): bool {
		if(!isset($this->meta['dependencies'])
			|| !is_array($this->meta['dependencies'])) {
			return false;
		}

		$satisfiedCounts = 0;
		foreach($this->meta['dependencies'] as $index => $dependency) {
			$satisfiedCounts += $this->isSatisfied($dependency, $resource->{$dependency['property']} ?? $resource->get($dependency['property']) ?? null);
		}

		return $satisfiedCounts == count($this->meta['dependencies']);
	}

    /**
     * Get a rule set based on field property name
     *
     * @param NovaRequest $request
     * @param string $propertyName
     * @return array
     */
    protected function getSituationalRulesSet(NovaRequest $request, string $propertyName = 'rules')
    {
        $fieldsRules = [$this->attribute => []];
        if (!$this->areDependenciesSatisfied($request)
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
