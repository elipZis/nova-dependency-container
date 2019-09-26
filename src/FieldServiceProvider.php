<?php

namespace elipZis\NovaDependencyContainer;

use Laravel\Nova\Nova;
use Laravel\Nova\Events\NovaServiceProviderRegistered;
use Laravel\Nova\Events\ServingNova;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

/**
 */
class FieldServiceProvider extends ServiceProvider {

	/**
	 *
	 * @return void
	 */
	public function boot() {
		// Override ActionController after NovaServiceProvider loaded
		Event::listen(NovaServiceProviderRegistered::class, static function() {
			app('router')->middleware('nova')->post('/nova-api/{resource}/action',
				['uses' => '\elipZis\NovaDependencyContainer\Http\Controllers\ActionController@store']);
		});

		Nova::serving(static function(ServingNova $event) {
			Nova::script('nova-dependency-container', __DIR__ . '/../dist/js/field.js');
		});
	}
}
