<?php

namespace DockWeb\REngDB\App\Secure;

use Composer\Autoload\ClassLoader;
use stdClass;


class Router {
	public $permition = false;
	public $nameSpace, $route, $config, $class;
	public $user;

	public function __construct() {
		@session_start(); // TODO Config 
		$this->route = str_replace('/', '\\', @$_SERVER['REDIRECT_URL']);
		if (!$this->route) $this->route = 'Main';
		$this->user=$this->checkLogin();
	}
	public function __destruct() {
		if (!$this->nameSpace) $this->goNotFound();
		elseif (!$this->permition) {
			if ('isLoged') $this->goAccesDenied();
			else $this->goGetLogin();
		}
	}
	public function __invoke($nameSpace, $ports = null, $protocols = null, $config = null) {
		print ':' . __CLASS__ . '->' . __METHOD__ . '("' . $this->route . '",' . json_encode(func_get_args()) . ");\n";
		if (
			$this->checkPort($ports) &&
			$this->checkProtocol($protocols)
		) {
			$this->nameSpace = $nameSpace;
			$this->config = $config;
			$this->class = $this->getClass($this->nameSpace, $this->route);
			return $this->checkPermition();
		}
		return false;
	}

	private function checkLogin() {
		// se logout -> clear credentials & keepalive && logoff
		// else
			// se new login -> check credentials & logon if ok
			// se loged -> getObj
		
	}
	private function checkPort($ports) {
		$port = @$_SERVER['SERVER_PORT'];

		// web
		if ($ports) return $port ? in_array($port, (array)$ports) : true;

		// console
		return $port ? false : (bool)@$_SERVER['SHELL'];
	}
	private function checkProtocol($protocols) {
		$prot = @$_SERVER['REQUEST_METHOD'];

		if (@$_SERVER['SHELL']) { // console
			return !$prot;
		} elseif ($protocols) { // web
			$protocols = array_map('strtoupper', (array)$protocols);
			return $prot ? in_array($prot, (array)$protocols) : true;
		}
		return true;
	}
	private function checkPermition() {
		print ':' . __CLASS__ . '->' . __METHOD__ . '("' . $this->route . '",' . json_encode(func_get_args()) . ");\n";
		$sec = $this->getPermition();
		if ($sec) {
			$this->permition = true;
			return $this->goView($this->class);
		}
		return false;
	}
	private function getClass($nameSpace, $route) {
		static $c = '\\';
		return rtrim($nameSpace, $c) . $c . ltrim($route, $c);
	}
	private function getPermition() {
		return new stdClass; // TODO implement
	}
	private function goView($class) {
		print ':' . __CLASS__ . '->' . __METHOD__ . '("' . $this->route . '",' . json_encode(func_get_args()) . ");\n";
		if ($this->classExistsInComposer($class)) {
			$v = new $class;
			if (method_exists($v, '__toString')) print $v;
			elseif (method_exists($v, '__invoke')) $v();
			exit;
		} else return false;
	}
	private function goException($route) {
		if (($e = $this->goView($this->getClass($this->nameSpace, $route))) ||
			($e = $this->goView($this->getClass(__NAMESPACE__, $route)))
		) print $e;
		exit;
	}
	private function goGetLogin() {
		if (@$_SERVER['SHELL']) $this->goException('AccesDenied');
		$this->goException('GetLogin');
	}
	private function goNotFound() {
		if (!@$_SERVER['SHELL']) http_response_code(404);
		$this->goException('NotFound');
	}
	private function goAccesDenied() {
		if (!@$_SERVER['SHELL']) http_response_code(404);
		$this->goException('AccesDenied');
	}
	protected function classExistsInComposer($class) {
		if (class_exists($class)) return true;

		// Obtém o arquivo autoload do Composer
		$composerAutoload = reset(ClassLoader::getRegisteredLoaders());
		$psr4Prefixes = $composerAutoload->getPrefixesPsr4();

		foreach ($psr4Prefixes as $psr4Prefix => $paths) {
			$tam = strlen($psr4Prefix);
			if (substr($class, 0, $tam) == $psr4Prefix) {
				$classFilePath = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, $tam)) . '.php';
				foreach ($paths as $path) {
					$fullPath = $path . DIRECTORY_SEPARATOR . $classFilePath;
					if (file_exists($fullPath)) return $fullPath;
				}
			}
		}
		return false;
	}
	function sh() {
		print_r([
			'SERVER' => [
				'HTTP_HOST' => @$_SERVER['HTTP_HOST'],
				'REQUEST_URI' => @$_SERVER['REQUEST_URI'],
				'REDIRECT_URL' => @$_SERVER['REDIRECT_URL'],
				'QUERY_STRING' => @$_SERVER['QUERY_STRING'],
				'SERVER_PORT' => @$_SERVER['SERVER_PORT'],
				'REDIRECT_STATUS' => @$_SERVER['REDIRECT_STATUS'],
				'REQUEST_METHOD' => @$_SERVER['REQUEST_METHOD'],
				'HTTP_ACCEPT' => @$_SERVER['HTTP_ACCEPT'],
				'HTTP_COOKIE' => @$_SERVER['HTTP_COOKIE'],
				'HTTP_ACCEPT_ENCODING' => @$_SERVER['HTTP_ACCEPT_ENCODING'],
				'HTTP_ACCEPT_LANGUAGE' => @$_SERVER['HTTP_ACCEPT_LANGUAGE'],
				'HTTP_UPGRADE_INSECURE_REQUESTS' => @$_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'],
				'REQUEST_SCHEME' => @$_SERVER['REQUEST_SCHEME'],
				'DOCUMENT_ROOT' => @$_SERVER['DOCUMENT_ROOT'],
				'SCRIPT_NAME' => @$_SERVER['SCRIPT_NAME'],
				'SCRIPT_FILENAME' => @$_SERVER['SCRIPT_FILENAME'],
				'Pass' => [
					'PHP_AUTH_DIGEST' => @$_SERVER['PHP_AUTH_DIGEST'],
					'PHP_AUTH_USER' => @$_SERVER['PHP_AUTH_USER'],
					'PHP_AUTH_PW' => @$_SERVER['PHP_AUTH_PW'],
				],
			],
			// 'Data' => [
			// 'input' => file_get_contents('php://input'),
			// 'GET' => @$_GET,
			// 'POST' => @$_POST,
			// 'COOKIE' => @$_COOKIE,
			// 'REQUEST' => @$_REQUEST,
			// 'FILES' => @$_FILES,
			// 'argv' => @$argv,
			// ],
		]);
	}
}

/*
switch (@$_SERVER['SERVER_PORT']) {
	case 80:
	case 443:
		require_once __DIR__ . '/../routes/web.php';
		break;
	case 8080:
		require_once __DIR__ . '/../routes/api.php';
	default:
		if (@$_SERVER['SHELL']) require_once __DIR__ . '/../routes/console.php';
		else {
			http_response_code(404);
		}
}
*/
