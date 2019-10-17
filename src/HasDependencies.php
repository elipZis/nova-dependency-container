<?php

namespace Epartment\NovaDependencyContainer;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Http\Requests\NovaRequest;

trait HasDependencies
{
    protected $childFieldsArr = [];
    
    /**
     * @param NovaRequest $request
     * @return FieldCollection|\Illuminate\Support\Collection
     */
    public function availableFields(NovaRequest $request)
    {
        // Needs to be filtered once to resolve Panels
        $fields = $this->filter($this->fields($request));
        $availableFields = [];

        foreach ($fields as $field) {
            if ($field instanceof NovaDependencyContainer) {
                $availableFields[] = $field;
                // @todo: this should only be checked on `$request->method() === 'PUT'`, e.g store/update.
                $model = $this->model();
                if($field->areDependenciesSatisfied($request) || $model->id === null) {
                    // check if dependency is sta
                    if ($this->doesRouteRequireChildFields()) {
                        $this->extractChildFields($field->meta['fields']);
                    }
                }
            } else {
                $availableFields[] = $field;
            }
        }

        if ($this->childFieldsArr) {
            $availableFields = array_merge($availableFields, $this->childFieldsArr);
        }

        return new FieldCollection(array_values($this->filter($availableFields)));
    }

    /**
     * @return bool
     */
    protected function doesRouteRequireChildFields() : bool
    {
        return Str::endsWith(Route::currentRouteAction(), [
            'FieldDestroyController@handle',
            'ResourceUpdateController@handle',
            'ResourceStoreController@handle',
            'AssociatableController@index',
            'MorphableController@index',
        ]);
    }

	/**
	 * @param  [array] $childFields [meta fields]
	 * @return void
	 */
	protected function extractChildFields($childFields) {
		foreach($childFields as $childField) {
			if($childField instanceof NovaDependencyContainer) {
				$this->extractChildFields($childField->meta['fields']);
			} else {
				if(array_search($childField->attribute, array_column($this->childFieldsArr, 'attribute')) === false) {
					$childField = $this->applyRulesForChildFields($childField);
					$this->childFieldsArr[] = $childField;
				}
			}
		}
	}

	/**
	 * @param array $childField
	 * @return mixed|array
	 */
	protected function applyRulesForChildFields($childField) {
		if(isset($childField->rules)) {
			$childField->rules[] = "sometimes:required:" . $childField->attribute;
		}
		if(isset($childField->creationRules)) {
			$childField->creationRules[] = "sometimes:required:" . $childField->attribute;
		}
		if(isset($childField->updateRules)) {
			$childField->updateRules[] = "sometimes:required:" . $childField->attribute;
		}
		return $childField;
	}

    /**
     * Validate action fields
     * Overridden using ActionController & ActionRequest by modifying routes
     * @return void
     */
    public function validateFields() {
        $availableFields = [];
        if ( !empty( ($action_fields = $this->action()->fields()) ) ) {
            foreach ($action_fields as $field) {
                if ($field instanceof NovaDependencyContainer) {
                    // do not add any fields for validation if container is not satisfied
                    // @todo: this should only be checked on `$request->method() === 'PUT'`, e.g store/update.
                    if($field->areDependenciesSatisfied($this)) {
                        $availableFields[] = $field;
                        $this->extractChildFields($field->meta['fields']);
                    }
                } else {
                    $availableFields[] = $field;
                }
            }
        }

		if($this->childFieldsArr) {
			$availableFields = array_merge($availableFields, $this->childFieldsArr);
		}

        $this->validate(collect($availableFields)->mapWithKeys(function ($field) {
            return $field->getCreationRules($this);
        })->all());
    }
}
