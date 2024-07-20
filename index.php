<?php
function redirect() {
	$h = explode('.', $_SERVER['HTTP_HOST'])[0];
	if (in_array($h, ['local', 'localhost'])) return;
	$r = $_SERVER['REDIRECT_URL'];

	$basePath = ["/var/www/$h", "/home/$h/public_html"];
	if ($r) {
		$b = explode('.', basename($r));
		$chkPath = count($b) > 1 ? [$r] : [$r . '/index.php', $r . '/index.htm', $r . '/index.html'];
	} else $chkPath = ['index.php', 'router.php', 'index.htm', 'index.html'];

	foreach($chkPath as $path) foreach($basePath as $base){
		$file=$base.$path;
		if(is_file($file)){
			$e=explode('.',$file);
			$e=array_pop($e);
			if (in_array($e, ['php', 'inc', 'htm', 'html', 'txt'])) include $file;
			else {
				$contents=  file_get_contents($file);
				$mimeType=mime_content_type($file);

				$expires = 1209600; // 14 * 60*60*24; = 14*86400 = 1209600
				header("Content-Type: $mimeType");
				header("Content-Length: " . strlen($contents));
				header("Cache-Control: public", true);
				header("Pragma: public", true);
				header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT', true);
				
				print $contents;
			}
			exit;
		}
	}
	return;
}
redirect();

print '<h1>Default</h1>
<pre><samp>';
print_r([
	'HTTP_HOST' => $_SERVER['HTTP_HOST'],
	'REQUEST_URI' => $_SERVER['REQUEST_URI'],
	'REDIRECT_URL' => $_SERVER['REDIRECT_URL'],
	'QUERY_STRING' => $_SERVER['QUERY_STRING'],
	'SERVER_PORT' => $_SERVER['SERVER_PORT'],
	'REDIRECT_STATUS' => $_SERVER['REDIRECT_STATUS'],
	'GLOBALS' => $GLOBALS,
]);
