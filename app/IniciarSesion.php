<?php
	if(isset($_SESSION['error']))
		$error = $_SESSION['error'];
	else
		$error = 0;

	if(isset($_SESSION)) {
		session_id();
		session_destroy();
		session_commit();
	}
	session_start();
	$_SESSION['error'] = $error;
	$params = parse_ini_file('dist/config.ini');
	if ($params === false) {
		$titulo = '';
	}
	$titulo = $params['title'];
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<title><?php echo $titulo; ?></title>
		<!-- Tell the browser to be responsive to screen width -->
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<!-- Font Awesome -->
		<link rel="stylesheet" href="plugins/fontawesome/css/all.css">
		<!-- Theme style -->
		<link rel="stylesheet" href="dist/css/adminlte.css">
		<!-- Icon Favicon -->
		<link rel="shortcut icon" href="dist/img/favicon.png" />
	</head>
	<body class="hold-transition login-page" onload="document.getElementById('tusuario').focus()" oncontextmenu="return false">
		<div class="wrapper">
			<!-- Navbar -->
			<nav class="navbar navbar-expand border-bottom navbar-dark bg-dark elevation-2">
				<img src="dist/img/solologo.png" class="m-0 p-0 bg-transparent imgmain" height="45px">
				<h2 id="titulo" class="align-items-center ml-2"><?php echo substr($titulo, strpos($titulo, '#')); ?><sup>v1.2</sup></h2>
			</nav>
			<!-- /.navbar -->

			<div class="login-box">
				<!-- Contains page content -->
				<div class="card elevation-4">
					<div class="card-header card-title text-center font-weight-bold bg-primary elevation-2">Iniciar Sesión</div>
					<div class="card-body login-card-body">
						<form action="app/DBProcs.php" method="post" onsubmit="document.getElementById('tclave').value = ascii_to_hexa(document.getElementById('tclave').value)">
							<input type="hidden" name="idpara" id="idpara" value="<?php echo $_SERVER['REQUEST_URI'] ?>">
							<input type="hidden" name="opcion" id="opcion" value="iniciar_sesion">
							<div class="input-group has-feedback elevation-2">
								<span class="input-group-addon p-1 pl-2 pr-2 bg-info"><i class="fas fa-user"></i></span>
								<input name="tusuario" id="tusuario" type="text" class="form-control" placeholder="Usuario" required>
							</div>
							<br>
							<div class="input-group has-feedback elevation-2">
								<span class="input-group-addon p-1 pl-2 pr-2 bg-info"><i class="fas fa-lock"></i></span>
								<input name="tclave" id="tclave" type="password" class="form-control" placeholder="Clave" required>
							</div>
							<br>
							<div class="row">
								<div class="col-12">
									<button type="submit" class="btn btn-primary btn-block elevation-2">Ingresar</button>
								</div>
								<!-- /.col -->
							</div>
						</form>
					</div>
					<!-- /.login-card-body -->
				</div>
				<!-- /.card -->
			</div>
			<?php
				if(isset($_SESSION['error'])) {
					if($_SESSION['error'] == 1) { ?>
						<div class="error-page align-items-center align-content-center fixed-bottom border border-danger elevation-4 my-4">
							<h1 class="headline text-danger ml-3">401</h1>
							<div class="error-content">
								<h2 class="text-center"><i class="fas fa-exclamation-triangle text-danger"></i> Uups! Algo salió mal.</h2>
								<p class="text-center py-2 mr-3 bg-dark-gradient font-weight-bold align-middle border border-light elevation-3">
									Ingresó un Usuario o Clave incorrectos.
									<br>
									Por favor ingrese de nuevo la información.
								</p>
							</div>
						</div>
					<?php } if($_SESSION['error'] == 2) { ?>
						<div class="error-page align-items-center align-content-center fixed-bottom border border-warning elevation-4 my-4">
							<h1 class="headline text-warning ml-3">401</h1>
							<div class="error-content">
								<h2 class="text-center"><i class="fas fa-exclamation-triangle text-warning"></i> !!! A T E N C I Ó N !!!.</h2>
								<p class="text-center py-2 mr-3 bg-info-gradient font-weight-bold align-middle border border-light elevation-3">
									Usuario Bloqueado/Inactivo.
									<br>
									Por favor comuniquese con el Administrador del Sistema
								</p>
							</div>
						</div>
					<?php }
					unset($_SESSION['error']);
				}
			?>
		</div>
		<script src="app/js/app.js"></script>
	</body>
</html>