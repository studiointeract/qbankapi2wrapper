<?php
function qbankwrapperautoload($class) {
	$path = dirname(__FILE__).'/';
	$subPaths = array('', 'model/', 'exceptions/');
	foreach ($subPaths as $subPath) {
		$file = $path.$subPath.$class.'.php';
		if (file_exists($file)) {
			include_once $file;
			return true;
		}
	}
}
spl_autoload_register('qbankwrapperautoload', true);
?>