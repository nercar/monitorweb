<?php
	session_start();
	$_SESSION['id'] = session_id();
	$_SESSION['url'] = $_SERVER['SERVER_NAME'];
	$params = parse_ini_file('dist/config.ini');
	if ($params === false) {
		$titulo = '';
	}
	$titulo = $params['title'];
	include 'app/IniciarSesion.php';
?>