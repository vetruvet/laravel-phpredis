<?php

namespace Vetruvet\PhpRedis;

use Illuminate\Support\ServiceProvider;

class PhpRedisServiceProvider extends ServiceProvider {
	
	protected $defer = true;

	public function register() {
		$this->app['redis'] = $this->app->share(function($app) {
			return new Database($app['config']['database.redis']);
		});
	}

	public function provides() {
		return array('redis');
	}

}
