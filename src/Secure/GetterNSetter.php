<?php

namespace DockWeb\REngDB\App\Secure;

trait GetterNSetter {
	public function __get($name) {
		if (method_exists($this, $fn = 'get' . $name)) return $this->$fn();
		if (key_exists($name, $this->readonly)) return $this->readonly[$name];
		return @$this->protect[$name];
	}
	public function __set($name, $value) {
		if (method_exists($this, $fn = 'set' . $name)) $this->$fn($value);
		elseif (!key_exists($name, $this->readonly)) $this->protect[$name]=$value;
		return $this;
	}

	public function getReadonly() {
		return $this->readonly;
	}
	public function getProtect() {
		return $this->protect;
	}
}

