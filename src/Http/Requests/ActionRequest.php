<?php

namespace elipZis\NovaDependencyContainer\Http\Requests;

use elipZis\NovaDependencyContainer\HasDependencies;
use Laravel\Nova\Http\Requests\ActionRequest as NovaActionRequest;

class ActionRequest extends NovaActionRequest {

	use HasDependencies;
}
