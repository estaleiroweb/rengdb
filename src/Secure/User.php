<?php

namespace DockWeb\REngDB\App\Secure;

class User {
	use GetterNSetter;

	const SESSION_KEY = 'secure';
	const LOGIN_TYPES = [
		'ad' => 'Active Directory _POST=["user"=>...,"passwd"=:...]',
		'db' => 'Data Base _POST=["user"=>...,"passwd"=:...]',
		'basic' => 'WWW-Authenticate: Basic',
		'digest' => 'WWW-Authenticate: Digest',
	];
	const ATTR_RO = [
		'loginType' => 'ad',
		'realm' => 'Login',
		'ttl' => null,
		'sessions' => null,
		'passConstraints' => [ // https://access.redhat.com/documentation/pt-br/red_hat_enterprise_linux/6/html/managing_smart_cards/pam_configuration_files
			'required' => true,
			'obscure' => true,
			'minlen' => 8,
			'maxlen' => 32,
			'retry' => 3,
			'difok' => 3,
			'nullok' => 3,

			'dcredit' => -1, // define a penalidade para palavras semelhantes ao nome de usuário. Neste caso, -1 indica que palavras semelhantes são desativadas. 
			'ucredit' => -1, // dígito
			'lcredit' => -1, // letras minúsculas
			'ocredit' => -1, // letra maiúscula
		],
	];
	const ATTR_RW = [
		'idUser' => null,
		'user' => null,
		'name' => null,
		'token' => null,
		'mainGroup' => null,
		'group' => [],
		'email' => [],
		'phone' => [],
		'docs' => [],
	];
	protected $loaded = false;
	protected $readonly = [];
	protected $protect = [];

	public function __construct(array $config = null) {
		if (isset($_SESSION[self::SESSION_KEY])) $this->loadSession();
		else $this->init();
		if ($config) foreach ($config as $k => $v) $this->$k = $v;

		if (@$_REQUEST['logout']) $this->logout();
		else {
			// se new login -> check credentials & logon if ok
			// se loged -> getObj
		}
	}
	public function init() {
		$_SESSION[self::SESSION_KEY] = array_merge(
			self::ATTR_RO,
			self::ATTR_RW
		);
		// TODO cookies 
		// TODO keepalive=0
		$this->loadSession();
		return $this;
	}
	protected function loadSession() {
		$this->protect = array_intersect_key(
			$_SESSION[self::SESSION_KEY],
			self::ATTR_RW
		);
		$this->readonly = array_intersect_key(
			$_SESSION[self::SESSION_KEY],
			self::ATTR_RO
		);
		return $this;
	}
	protected function saveSession() {
		$_SESSION[self::SESSION_KEY] = array_merge(
			$this->readonly,
			$this->protect
		);
		return $this;
	}

	public function logout() {
		// se logout -> clear credentials & keepalive && logoff
		$this->init()->saveSession();
		return $this;
	}
	public function checkDataBase($user, $passwd) {
		//TODO data base checker
		$this->idUser = null;
		$this->load();
	}
	public function isLoged() {
		return (bool)$this->idUser;
	}
	public function load() {
		if ($this->idUser) {
			if ($this->loaded) return $this->readonly;
			$this->loaded = true;
			$user = self::ATTR_RO; // get and check user ok in database (false if not)
			if ($user) {
				$this->readonly = $user;
				$this->saveSession();
				return $this->readonly;
			} else $this->logout();
		}
		return false;
	}
	public function login() {
		if ($this->idUser) return $this->readonly;
		return $this->{__METHOD__ . '_' . $this->loginType}();
	}
	protected function login_ad() {
		return $this->checkDataBase(@$_POST['user'], @$_POST['passwd']);
	}
	protected function login_db() {
		return $this->checkDataBase(@$_POST['user'], @$_POST['passwd']);
	}
	protected function login_basic() {
		if (!isset($_SERVER['PHP_AUTH_USER'])) {
			header('WWW-Authenticate: Basic realm="' . $this->realm . '"');
			header('HTTP/1.0 401 Unauthorized');
			// echo 'Texto enviado caso o usuário clique no botão Cancelar';
			// exit;
			return $this->logout();
		}
		return $this->checkDataBase($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
	}
	protected function login_digest() {
		$realm = 'Área restrita';

		//usuário => senha
		$users = array('admin' => 'mypass', 'guest' => 'guest');


		if (empty(@$_SERVER['PHP_AUTH_DIGEST'])) {
			header('HTTP/1.1 401 Unauthorized');
			header('WWW-Authenticate: Digest realm="' . $this->realm .
				'",qop="auth",nonce="' . uniqid() . '",opaque="' . md5($this->realm) . '"');

			// print 'Texto enviado caso o usuário clique no botão Cancelar';
			// exit;
			return $this->logout();
		}


		// analisar a variável PHP_AUTH_DIGEST
		if (
			!($data = $this->http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])) ||
			!isset($users[$data['username']])
		)
			die('Credenciais inválidas!');


		// gerar a resposta válida
		$A1 = md5($data['username'] . ':' . $realm . ':' . $users[$data['username']]);
		$A2 = md5($_SERVER['REQUEST_METHOD'] . ':' . $data['uri']);
		$valid_response = md5($A1 . ':' . $data['nonce'] . ':' . $data['nc'] . ':' . $data['cnonce'] . ':' . $data['qop'] . ':' . $A2);

		if ($data['response'] != $valid_response)
			die('Credenciais inválidas!');

		// ok, nome de usuário e senha válidos
		echo 'Você está logado como: ' . $data['username'];


		// função para decompor o http auth header
	}
	protected function http_digest_parse($txt) {
		// proteção contra dados incompletos
		$needed_parts = array('nonce' => 1, 'nc' => 1, 'cnonce' => 1, 'qop' => 1, 'username' => 1, 'uri' => 1, 'response' => 1);
		$data = array();
		$keys = implode('|', array_keys($needed_parts));

		preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);

		foreach ($matches as $m) {
			$data[$m[1]] = $m[3] ? $m[3] : $m[4];
			unset($needed_parts[$m[1]]);
		}

		return $needed_parts ? false : $data;
	}
}
