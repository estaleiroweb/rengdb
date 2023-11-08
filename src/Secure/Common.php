<?php

namespace DockWeb\REngDB\App\Secure;

use DockWeb\REngDB\App\Secure\Router;

class Common {
	protected $router;

	public function __construct(Router $router) {
		$this->router = $router;
	}
	public function __toString() {
		return __CLASS__;
	}
}
