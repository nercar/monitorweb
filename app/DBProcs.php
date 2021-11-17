<?php
	/**
	* Permite obtener los datos de la base de datos y retornarlos
	* en modo json o array
	*/
	try {
		date_default_timezone_set('America/Caracas');
		// Se capturan las opciones por Post
		$opcion = (isset($_POST["opcion"])) ? $_POST["opcion"] : "";
		$fecha  = (isset($_POST["fecha"]) ) ? $_POST["fecha"]  : date("Y-m-d");
		$hora   = (isset($_POST["hora"])  ) ? $_POST["hora"]   : date("H:i:s");
		// id para los filtros en las consultas
		$idpara = (isset($_POST["idpara"])) ? $_POST["idpara"] : '';
		// Se establece la conexion con la BBDD
		$params = parse_ini_file('../dist/config.ini');
		if ($params === false) {
			// exeption leyen archivo config
			throw new \Exception("Error reading database configuration file");
		}
		// connect to the sql server database
		if($params['instance']!='') {
			$conStr = sprintf("sqlsrv:Server=%s\%s;",
				$params['host_sql'],
				$params['instance']);
		} else {
			$conStr = sprintf("sqlsrv:Server=%s,%d;",
				$params['host_sql'],
				$params['port_sql']);
		}
		$connec = new \PDO($conStr, $params['user_sql'], $params['password_sql']);
		$host_ppl    = $params['host_ppl'];
		switch ($opcion) {
			case 'hora_srv':
				echo json_encode('1¬' . $hora);
				break;
			case 'consultarTBLDivisas':
				$sql = "SELECT COALESCE(Object_Id('BDES.dbo.ESFormasPago_FactorC'), 0) AS existe";
				$sql = $connec->query($sql);
				$row = $sql->fetch(\PDO::FETCH_ASSOC);
				if($row['existe']>0){
					echo 1;
				} else {
					echo 0;
				}
				break;
			case 'iniciar_sesion':
				extract($_POST);
				if(empty($tusuario) || empty($tclave)){
					header("Location: " . $idpara);
					break;
				}
				$sql = "SELECT login, descripcion, codusuario, password AS clave, activo, nivel
						FROM BDES.dbo.ESUsuarios WHERE LOWER(login)=LOWER('$tusuario') 
						AND password = '$tclave'";
				$sql = $connec->query($sql);
				$row = $sql->fetch(\PDO::FETCH_ASSOC);
				if($row) {
					if($row['activo']!=1) {
						session_destroy();
						session_commit();
						session_start();
						session_id($_SESSION['id']);
						session_destroy();
						session_commit();
						session_start();
						$_SESSION['error'] = 2;
						header("Location: " . $idpara);
					} else {
						session_start();
						$_SESSION['id']         = session_id();
						$_SESSION['url']        = $idpara;
						$_SESSION['usuario']    = strtolower($row['login']);
						$_SESSION['nomusuario'] = ucwords(strtolower($row['descripcion']));
						$_SESSION['error']      = 0;
						$_SESSION['nivel']      = $row['nivel'];
						header("Location: " . $idpara . "inicio.php");
					}
				} else {
					session_start();
					session_id($_SESSION['id']);
					session_destroy();
					session_commit();
					session_start();
					$_SESSION['error'] = 1;
					header("Location: " . $idpara);
				}
				break;
			case 'cerrar_sesion':
				session_start();
				session_id($_SESSION['id']);
				session_destroy();
				session_commit();
				header("Location: " . $_SESSION['url']);
				exit();
				break;
			case 'calDivisas':
				$sql = "SELECT simbolo, FACTOR, MULTIPLICA FROM BDES.dbo.ESFormasPago_FactorC WHERE CODIGO in(60, 61)";
				$sql = $connec->query($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'simbolo'    => $row['simbolo'],
						'factor'     => $row['FACTOR']*1,
						'multiplica' => $row['MULTIPLICA']*1,
					];
				}
				echo json_encode($datos);
				break;
			case 'cedulasid':
				$sql = "SELECT id, descripcion, predeterminado 
						FROM BDES.dbo.ESCedulasId 
						ORDER BY predeterminado DESC";
				$sql = $connec->query($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'id'             => $row['id'],
						'descripcion'    => $row['descripcion'],
						'predeterminado' => $row['predeterminado'],
					];
				}
				echo json_encode($datos);
				break;
			case 'consultarClte':
				$sql = "SELECT RIF, RAZON, DIRECCION, EMAIL, TELEFONO
						FROM BDES_POS.dbo.ESCLIENTESPOS
						WHERE RIF = '$idpara'
						AND activo = 1";
				$sql = $connec->query($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'rif'       => $row['RIF'],
						'razon'     => $row['RAZON'],
						'telefono'  => $row['TELEFONO'],
						'email'     => $row['EMAIL'],
						'direccion' => $row['DIRECCION'],
					];
				}
				echo json_encode($datos);
				break;
			case 'calTotalesDespacho':
				$sql = "SELECT SUM(CANTIDAD) AS CANTIDAD, SUM(CANTIDAD*(PRECIO*(1+(PORC/100)))) AS MONTO, COUNT(IDTR) AS ITEMS
				FROM BDES_POS.dbo.DBVENTAS_TMP_DET WHERE IDTR = $idpara AND IMAI = 1";
				$sql = $connec->query($sql);
				$row = $sql->fetch(\PDO::FETCH_ASSOC);
				$decimales = $row['CANTIDAD'] - intval($row['CANTIDAD']);
				$enteros = $row['CANTIDAD'] - $decimales;
				$cantidad = number_format($enteros, 0) . '.<sub>'.substr(number_format($decimales, 3), 2).'</sub>';
				$datos = [
					'cantidad' => $cantidad,
					'monto'    => number_format($row['MONTO']*1, 2),
					'items'    => $row['ITEMS']*1,
				];
				echo json_encode($datos);
				break;
			case 'consultaDispo':
				extract($_POST);
				if($buscaren==2) {
					$sql = "SELECT CODIGO 
							FROM BDES.dbo.ESGrupos
							WHERE DESCRIPCION LIKE '%$idpara%'";
					$sql = $connec->query($sql);
					// Se prepara el array para almacenar los datos obtenidos
					$grupos = '';
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$grupos.= $row['CODIGO'] .',';
					}
					$grupos = ($grupos!='') ? substr($grupos, 0, -1) : '99999';
					$sql = "SELECT CODIGO 
							FROM BDES.dbo.ESSubgrupos
							WHERE DESCRIPCION LIKE '%$idpara%'";
					$sql = $connec->query($sql);
					// Se prepara el array para almacenar los datos obtenidos
					$subgrupos = '';
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$subgrupos.= $row['CODIGO'] .',';
					}
					$subgrupos = ($subgrupos!='') ? substr($subgrupos, 0, -1) : '99999';
				}
				if($buscaren==1) {
					if(is_numeric($idpara)) {
						$where = " WHERE (a.codigo = $idpara OR ";	
					} else {
						$where = " WHERE (";
					}
					$where.= " a.descripcion LIKE '%$idpara%' OR bar.barra = '$idpara')";
				} else {
					$where = " WHERE a.grupo IN ($grupos) OR a.subgrupo IN ($subgrupos)";
				}
				$sql="SELECT d.material, COALESCE(bar.barra, CAST(d.material AS VARCHAR)) AS barra,
						a.descripcion, (CASE WHEN a.tipoarticulo != 0 THEN 1 ELSE 0 END) AS pesado,
						COALESCE(SUM(d.ExistLocal), 0) AS existlocal, a.impuesto,
						a.precio1 AS base, a.costo AS costo,
						(CASE WHEN CAST(a.fechainicio AS TIME) != '00:00:00' OR CAST(a.fechafinal AS TIME) != '00:00:00' THEN
							(CASE WHEN GETDATE() BETWEEN a.fechainicio AND a.fechafinal
								THEN a.preciooferta ELSE 0 END)
						ELSE
							(CASE WHEN CAST(GETDATE() AS DATE) 
								BETWEEN CAST(a.fechainicio AS DATE) AND CAST(a.fechafinal AS DATE)
								THEN a.preciooferta ELSE 0 END)
						END) AS oferta,
						(SELECT FACTOR FROM BDES.dbo.ESFormasPago_FactorC WHERE CODIGO = 60) AS factor,
						60 AS moneda
					FROM (SELECT articulo AS material, SUM(cantidad-comprometida-usada) AS existlocal 
							FROM BDES.dbo.BIKardexExistencias
							GROUP BY articulo) AS d
							INNER JOIN BDES.dbo.ESARTICULOS a ON a.codigo = d.material AND a.activo = 1
							LEFT JOIN (SELECT escodigo, MAX(DISTINCT barra) AS barra
								FROM BDES.dbo.ESCodigos
								WHERE CAST(escodigo AS VARCHAR) != barra 
								GROUP BY escodigo) AS bar ON bar.escodigo = a.codigo
					$where
					GROUP BY d.material, a.descripcion, bar.barra, a.tipoarticulo,
						a.precio1, a.impuesto, a.preciooferta, a.fechainicio, a.fechafinal, a.costo, a.moneda
					HAVING (SUM(existlocal) > 0)
					ORDER BY a.descripcion ASC";
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$precio = round($row['base'] * (1+($row['impuesto']/100)), 2) * $row['factor'];
					if($row['oferta']>0) {
						$precio = round($row['oferta'] * (1+($row['impuesto']/100)), 2) * $row['factor'];
					}
					$existlocal = ($row['existlocal']*1);
					if($precio>0 && $existlocal>0) {
						$txt = '<button type="button" title="Agregar Artículo" onclick="' .
									" addarticulo('" . $row['material'] . "','" . $row['barra'] . 
										"','" . $row['descripcion'] . "'," . round($precio, 2)*1 .
										"," . $existlocal . "," . $row['pesado']*1 . 
										"," . round($row['base']*1,2) . "," . round($row['impuesto']*1,2) .
										"," . round($row['costo']*1,2) . "," . $row['moneda'] . "," . $row['factor'] . ")" .
									'" class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
									' style="white-space: normal; line-height: 1;">' . ucwords($row['descripcion']) .
								'</button>';
						$datos[] = [
							'material'    => $row['material'],
							'barra'       => '<span title="' . $row['material'] . '">' . $row['barra'] . '</span>',
							'descripcion' => $txt,
							'precio'      => number_format($precio, 2),
							'existlocal'  => number_format($existlocal, 2),
							'pesado'      => $row['pesado']*1,
							'nombre'      => $row['descripcion'],
							'cbarra'      => $row['barra'],
							'nprecio'     => round($precio, 2)*1,
							'nexistlocal' => $existlocal,
							'precioreal'  => round($row['base']*1,2),
							'impuesto'    => round($row['impuesto']*1,2),
							'costo'       => round($row['costo']*1,2),
							'moneda'      => $row['moneda']*1,
							'factor'      => $row['factor']*1,
						];
					}
				}
				echo json_encode($datos);
				break;
			case 'guardarPrefactura':
				extract($_POST);
				$detalle = json_decode($detalle);
				$sql = "SELECT (ValorCorrelativo + 1) AS ValorCorrelativo
						FROM BDES_POS.dbo.ESCORRELATIVOS
						WHERE Correlativo = 'CompraEsperaRem'";
				$sql = $connec->query($sql);
				$idtr = $sql->fetch(\PDO::FETCH_ASSOC);
				$idtr = $idtr['ValorCorrelativo'];
				$sql = "UPDATE BDES_POS.dbo.ESCORRELATIVOS
						SET ValorCorrelativo = $idtr
						WHERE Correlativo = 'CompraEsperaRem'";
				$sql = $connec->query($sql);
				if($sql) {
					$sql = "INSERT INTO BDES_POS.dbo.DBVENTAS_TMP
								(IDTR, IDCLIENTE, ACTIVA, FECHAHORA, CAJA, RAZON, DIRECCION, LIMITE, CREADO_POR, email, telefono)
								VALUES($idtr, '$cedulasid'+'$idclte', 1, CURRENT_TIMESTAMP, 999,
									  '$nomclte', '$dirclte', 0, '$usuario', '$emailclte', '$telclte')";
					$sql = $connec->query($sql);
					if($sql) {
						$sql = "INSERT INTO BDES_POS.dbo.DBVENTAS_TMP_DET
								(IDTR, LINEA, ARTICULO, BARRA, PRECIO, COSTO, CANTIDAD, PEDIDO, SUBTOTAL, IMPUESTO,
								PORC, PRECIOREAL, PROMO, PROMODSCTO, PRECIOOFERTA, MONEDA, FACTOR)
								VALUES";
						$i = 1;
						foreach ($detalle as $value) {
							$precio = $value->precio/(1+($value->impuesto/100));
							$sql .= "($idtr, $i, '$value->codigo', '$value->barra', $precio, 
									  $value->costo, $value->cantidad, $value->cantidad, 0, 0, $value->impuesto,
									  $value->precioreal, 0, 0, $precio, $value->moneda, $value->factor),";
							$i++;
						}
						$sql = $connec->query(substr($sql, 0, -1));
						if(!$sql) {
							print_r( $connec->errorInfo() );
						}
					} else {
						print_r( $connec->errorInfo() );
					}
					$sql = "SELECT COUNT(*) AS cuenta FROM BDES_POS.dbo.ESCLIENTESPOS
							WHERE RIF = '$cedulasid'+'$idclte'
							AND ACTIVO = 1";
					$sql = $connec->query($sql);
					$sql = $sql->fetch(\PDO::FETCH_ASSOC);
					if($sql['cuenta']==0) {
						$sql = "SELECT (ValorCorrelativo + 1) AS ValorCorrelativo
								FROM BDES_POS.dbo.ESCORRELATIVOS
								WHERE Correlativo = 'ClientePos'";
						$sql = $connec->query($sql);
						$codclte = $sql->fetch(\PDO::FETCH_ASSOC);
						$codclte = $codclte['ValorCorrelativo'];
						$sql = "UPDATE BDES.dbo.ESCorrelativos
								SET ValorCorrelativo = $codclte
								WHERE Correlativo = 'Cliente'";
						$sql = $connec->query($sql);
						if($sql) {
							$sql = "INSERT INTO BDES_POS.dbo.ESCLIENTESPOS
									(RIF, RAZON, DIRECCION, ACTIVO, IDTR, ACTUALIZO, EMAIL, TELEFONO)
									VALUES('$cedulasid'+'$idclte', '$nomclte', '$dirclte', 1, $codclte,
									1, '$emailclte', '$telclte')";
							$sql = $connec->query($sql);
						} else {
							print_r( $connec->errorInfo() );
						}
					} else {
						$sql = "UPDATE BDES_POS.dbo.ESCLIENTESPOS
								SET RAZON = '$nomclte', DIRECCION = '$dirclte', ACTIVO = 1,
								EMAIL = '$emailclte', TELEFONO = '$telclte'
								WHERE RIF = '$cedulasid'+'$idclte'";
						$sql = $connec->query($sql);
					}
				} else {
					print_r( $connec->errorInfo() );
				}
				if($sql) {
					echo $idtr;
				} else {
					echo 0;
				}
				break;
			case 'agregaArtDesp':
				$idpara = explode('¬', $idpara);
				$precio = $idpara[4]/(1+($idpara[6]/100));
				$sql = "INSERT INTO BDES_POS.dbo.DBVENTAS_TMP_DET
						(IDTR, LINEA, ARTICULO, BARRA, PRECIO, COSTO, CANTIDAD, PEDIDO, SUBTOTAL, IMPUESTO,
						PORC, PRECIOREAL, PROMO, PROMODSCTO, IMAI, PRECIOOFERTA, MONEDA, FACTOR)
						VALUES
						($idpara[0], $idpara[1]+1, $idpara[2], '$idpara[3]', $precio, 
						$idpara[5], 1, 1, 0, 0, $idpara[6], $idpara[7], 0, 0, 1, $precio,
						$idpara[8], $idpara[9])";
				$sql = $connec->query($sql);
				if($sql) {
					echo 1;
				} else {
					echo 0;
					print_r( $connec->errorInfo() );
				}
				break;
			case 'delArtDesp':
				$idpara = explode('¬', $idpara);
				$sql = "DELETE FROM BDES_POS.dbo.DBVENTAS_TMP_DET WHERE IDTR = $idpara[0] AND ARTICULO = $idpara[1]";
				$sql = $connec->query($sql);
				if($sql) {
					echo 1;
				} else {
					echo 0;
					print_r( $connec->errorInfo() );
				}
				break;
			case 'crearTemporalCab':
				extract($_POST);
				$sql = "INSERT INTO BDES_POS.dbo.DB_TMP_VENTAS
							(FECHAHORA, RIF, NOMBRE, TELEFONO, CORREO, DIRECCION, ACTIVA)
							VALUES
							(CURRENT_TIMESTAMP, '$idclte', '$nomcte', '$telcte', '$emacte', '$dircte', 1)";
				$sql = $connec->query($sql);
				if($sql) {
					$sql  ="SELECT IDENT_CURRENT('BDES_POS.dbo.DB_TMP_VENTAS') as idtr";
					$sql  = $connec->query($sql);
					$sql  = $sql->fetch(\PDO::FETCH_ASSOC);
					$idtr = $sql['idtr'];
				} 
				echo $idtr;
				break;
			case 'crearTemporalDet':
				extract($_POST);
				$sql = "INSERT INTO BDES_POS.dbo.DB_TMP_VENTAS_DET
						(ID, ARTICULO, BARRA, PRECIO, COSTO, CANTIDAD, PORC, PRECIOREAL, ACTIVA)
						VALUES
						($idtemp, $codart, '$barart', $pvpart, $cosart, $canart, $impart, $preart, 1)";
				$sql = $connec->query($sql);
				if($sql)
					echo 1;
				else {
					echo 0;
					print_r( $connec->errorInfo() );
				}
				break;
			case 'modCantidadTmp':
				$params = explode('¬', $idpara);
				$sql = "UPDATE BDES_POS.dbo.DB_TMP_VENTAS_DET SET
						CANTIDAD = $params[2]
						WHERE ID = $params[0] AND ARTICULO = $params[1]";
				$sql = $connec->query($sql);
				if($sql)
					echo 1;
				else {
					echo 0;
					print_r( $connec->errorInfo() );
				}
				break;
			case 'delArtTmp':
				$idpara = explode('¬', $idpara);
				$sql = "DELETE FROM BDES_POS.dbo.DB_TMP_VENTAS_DET WHERE ID = $idpara[0] AND ARTICULO = $idpara[1]";
				$sql = $connec->query($sql);
				if($sql) {
					echo 1;
				} else {
					echo 0;
					print_r( $connec->errorInfo() );
				}
				break;
			case 'buscarTemporal':
				$sql = "SELECT ID AS nrodoc, CONVERT(VARCHAR, FECHAHORA, 103) AS FECHA,
							CONVERT(VARCHAR(5), FECHAHORA, 108) AS HORA,
							RIF, NOMBRE, TELEFONO, CORREO, DIRECCION
						FROM BDES_POS.dbo.DB_TMP_VENTAS
						WHERE RIF = '$idpara' AND ACTIVA = 1";
				$sql = $connec->query($sql);
				$cabecera = $sql->fetch(\PDO::FETCH_ASSOC);
				if( $cabecera ) {
					$recupera = 1;
					$sql = "SELECT det.ID, det.ARTICULO, det.BARRA,
								(CASE WHEN CAST(GETDATE() AS DATE) BETWEEN CAST(art.fechainicio AS DATE)
									AND CAST(art.fechafinal AS DATE) THEN
											ROUND((art.preciooferta * (1+(art.impuesto/100))) *
											(CASE WHEN art.moneda = 0 THEN 1
											ELSE (SELECT FACTOR FROM BDES.dbo.ESFormasPago_FactorC WHERE CODIGO = 60)
											END), 2)
								ELSE
									ROUND((art.precio1 * (1+(art.impuesto/100))) *
										(CASE WHEN art.moneda = 0 THEN 1
										ELSE (SELECT FACTOR FROM BDES.dbo.ESFormasPago_FactorC WHERE CODIGO = 60)
										END), 2)
								END) AS PRECIO,
								ROUND(art.costo * 
									(CASE WHEN art.moneda = 0 THEN 1
									ELSE (SELECT FACTOR FROM BDES.dbo.ESFormasPago_FactorC WHERE CODIGO = 60)
									END), 2) AS COSTO,
								det.CANTIDAD, det.PORC,
								ROUND(art.precio1 * 
									(CASE WHEN art.moneda = 0 THEN 1
									ELSE (SELECT FACTOR FROM BDES.dbo.ESFormasPago_FactorC WHERE CODIGO = 60)
									END), 2) AS PRECIOREAL,
								det.ACTIVA, art.descripcion AS DESCRIPCION,
								( CASE WHEN art.tipoarticulo != 0 THEN 1 ELSE 0 END ) AS PESADO,
								COALESCE(SUM(d.existlocal), 0) AS EXISTLOCAL,
								(SELECT FACTOR FROM BDES.dbo.ESFormasPago_FactorC WHERE CODIGO = 60) AS FACTOR,
								60 AS MONEDA
							FROM BDES_POS.dbo.DB_TMP_VENTAS_DET det
							INNER JOIN BDES.dbo.ESARTICULOS art ON art.codigo = det.ARTICULO
							INNER JOIN (SELECT articulo, SUM(cantidad-comprometida-usada) AS existlocal 
										FROM BDES.dbo.BIKardexExistencias
										GROUP BY articulo
										HAVING (SUM(cantidad-comprometida-usada)>0)) AS d ON d.articulo = det.ARTICULO
							WHERE ACTIVA = 1 AND ID = " . $cabecera['nrodoc'] ."
							GROUP BY det.ID, det.ARTICULO, det.BARRA, det.CANTIDAD, det.PORC, det.ACTIVA, art.descripcion,
								art.tipoarticulo, art.fechainicio, art.fechafinal, art.precio1, art.preciooferta,
								art.impuesto, art.moneda, art.costo";
					$sql = $connec->query($sql);
					if(!$sql) print_r( $connec->errorInfo() );
					$detalle = $sql->fetchAll(\PDO::FETCH_ASSOC);
				} else {
					$recupera = 0;
					$cabecera = [];
					$detalle = [];
				}
				echo json_encode(array('recupera' => $recupera, 'cabecera' => $cabecera, 'detalle' => $detalle));
				break;
			case 'eliminarTemporal':
				$sql = "UPDATE BDES_POS.dbo.DB_TMP_VENTAS     SET ACTIVA = 0 WHERE ID = $idpara; 
						UPDATE BDES_POS.dbo.DB_TMP_VENTAS_DET SET ACTIVA = 0 WHERE ID = $idpara";
				$sql = $connec->query($sql);
				if($sql) {
					echo '1-'.$idpara;
				} else {
					echo 0;
					print_r( $connec->errorInfo() );
				}
				break;
			case 'despachosWebpendientes':
				if($idpara!='') {
					// Se prepara la consulta para los articulos para paginas web
					$sql = "SELECT cab.IDTR, 
							(CONVERT(VARCHAR(10), cab.FECHAHORA, 105)+' '+CONVERT(VARCHAR(5), cab.FECHAHORA, 108)) AS FECHAHORA,
							cab.IDCLIENTE, cab.RAZON, cab.GRUPOC, cli.TELEFONO, cab.mensaje, cab.DIRECCION,
							cab.montodomicilio, cab.descuentos
							FROM BDES_POS.dbo.DBVENTAS_TMP cab
							LEFT OUTER JOIN BDES_POS.dbo.ESCLIENTESPOS cli ON cli.RIF = cab.IDCLIENTE
							WHERE  cab.GRUPOC <= 1 AND cab.IDTR = $idpara";
					// Se ejecuta la consulta en la BBDD
					$sql = $connec->query($sql);
					if(!$sql) print_r($connec->errorInfo());
					// Se prepara el array para almacenar los datos obtenidos
					$datos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[] = [
							'nrodoc'    => $row['IDTR'],
							'fecha'     => date('d-m-Y H:i', strtotime($row['FECHAHORA'])),
							'idcliente' => $row['IDCLIENTE'],
							'nombre'    => $row['RAZON'],
							'activa'    => $row['GRUPOC'],
							'telefono'  => $row['TELEFONO'],
							'mensaje'   => $row['mensaje'],
							'direccion' => $row['DIRECCION'],
							'domicilios'=> $row['montodomicilio'],
							'descuentos'=> $row['descuentos']
						];
					}
				} else {
					$datos = [];
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'detalleDespachoweb':
				if($idpara!='') {
					$sql = "SELECT IDTR, ARTICULO, BARRA, IMAI,
								art.descripcion AS NOMBRE,
								(CASE WHEN art.tipoarticulo != 0 THEN 1 ELSE 0 END) AS pesado,
								ROUND(PRECIO+(PRECIO*PORC/100), 2) AS PRECIO,
								SUM(CANTIDAD) AS CANTIDAD
							FROM BDES_POS.dbo.DBVENTAS_TMP_DET AS det
							INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = det.ARTICULO
							WHERE IDTR = $idpara
							GROUP BY IDTR, ARTICULO, BARRA, IMAI, art.descripcion, art.tipoarticulo,
								PRECIO, PORC, det.LINEA
							ORDER BY det.LINEA";
					$sql = $connec->query($sql);
					$datos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[]=[
							'nrodoc'   => $row['IDTR'],
							'codigo'   => $row['ARTICULO'],
							'barra'    => $row['BARRA'],
							'imai'     => $row['IMAI'],
							'nombre'   => $row['NOMBRE'],
							'precio'   => $row['PRECIO'],
							'cantidad' => ($row['pesado']==1 ? $row['CANTIDAD']*1:round($row['CANTIDAD']*1, 0)),
							'pesado'   => $row['pesado'],
						];
					}
				} else {
					$datos = [];
				}
				echo json_encode($datos);
				break;
			case 'marcarCabeceraweb':
				// se extraen los valores del parametro idpara
				$params = explode('¬', $idpara);
				$usuario = "'" . $params[2] . "'";
				$fpickin = 'CURRENT_TIMESTAMP';
				if($params[1]==0) {
					$usuario = 'null';
					$fpickin = 'null';
				}
				// Se modifica el valor para indicar si se envia o no al excel
				$sql = "UPDATE BDES_POS.dbo.DBVENTAS_TMP SET
							GRUPOC = $params[1],
							PICKING_POR = $usuario,
							FECHA_PICKING = $fpickin
						WHERE IDTR = $params[0]";
				$sql = $connec->query($sql);
				if($sql) {
					echo '1';
				} else {
					echo '0';
					print_r( $connec->errorInfo() );
				}
				break;
			case 'marcarDetalleweb':
				// se extraen los valores del parametro idpara
				$params = explode('¬', $idpara);
				// Se modifica el valor para indicar si se envia o no al excel
				$sql = "UPDATE BDES_POS.dbo.DBVENTAS_TMP_DET SET 
						IMAI = $params[2]
						WHERE IDTR = $params[0] AND ARTICULO = $params[1]";
				$sql = $connec->query($sql);
				if($sql) {
					echo '1';
				} else {
					echo '0';
					print_r( $connec->errorInfo() );
				}
				break;
			case 'modCantidadweb':
				// se extraen los valores del parametro idpara
				$params = explode('¬', $idpara);
				// Se modifica el valor para indicar si se envia o no al excel
				$sql = "UPDATE BDES_POS.dbo.DBVENTAS_TMP_DET SET 
						CANTIDAD = $params[2]
						WHERE IDTR = $params[0] AND ARTICULO = $params[1]";
				$sql = $connec->query($sql);
				if($sql) {
					echo '1';
				} else {
					echo '0';
					print_r( $connec->errorInfo() );
				}
				break;
			case 'procDctoweb':
				// se extraen los valores del parametro idpara
				$params = explode('¬', $idpara);
				// Se modifica el valor para indicar si se envia o no al excel
				$sql = "UPDATE BDES_POS.dbo.DBVENTAS_TMP SET
						GRUPOC = 2, FECHA_PROCESADO = CURRENT_TIMESTAMP,
						PROCESADO_POR = '$params[1]'
						WHERE IDTR = $params[0]";
				$sql = $connec->query($sql);
				if($sql) {
					$sql = "INSERT INTO BDES_POS.dbo.ESVENTAS_TMP
								(IDTR, IDCLIENTE, ACTIVA, LIMITE, FECHAHORA, SUSPENDIDO,
								 PERMITEREG, CAJA, RAZON, DIRECCION, SODEXOACTIVO, pais,
								 estado, ciudad, tipoc, Codigoc, NDE, GRUPOC, email, telefono, VENDEDOR)
							SELECT
							 	IDTR, IDCLIENTE, ACTIVA, LIMITE, FECHAHORA, SUSPENDIDO,
							 	PERMITEREG, CAJA, RAZON, DIRECCION, SODEXOACTIVO, pais,
							 	estado, ciudad, tipoc, Codigoc, 0, GRUPOC, email, telefono, 2
							FROM BDES_POS.dbo.DBVENTAS_TMP WHERE IDTR = $params[0];
							INSERT INTO BDES_POS.dbo.ESVENTAS_TMP_DET
								(IDTR, LINEA, ARTICULO, BARRA, PRECIO, COSTO, CANTIDAD,
								 SUBTOTAL, IMPUESTO, PORC, PRECIOREAL, PROMO, PROMODSCTO,
								 IMAI, NDEREL, MONEDA, FACTOR)
							SELECT IDTR, LINEA, ARTICULO, BARRA, PRECIO, COSTO, CANTIDAD,
								 SUBTOTAL, IMPUESTO, PORC, PRECIOREAL, PROMO, PROMODSCTO, 
								 IMAI, NDEREL, MONEDA, FACTOR
							FROM BDES_POS.dbo.DBVENTAS_TMP_DET WHERE IDTR = $params[0] AND IMAI = 1";
					$sql = $connec->query($sql);
				}
				if($sql) {
					echo '1';
				} else {
					echo '0';
					print_r( $connec->errorInfo() );
				}
				break;
			case 'monitorlistaDocsweb':
				$sql = "UPDATE BDES_POS.dbo.DBVENTAS_TMP
						SET GRUPOC = 3
						WHERE IDTR NOT IN(SELECT IDTR FROM BDES_POS.dbo.ESVENTAS_TMP)
						AND GRUPOC = 2";
				$sql = $connec->query($sql);
				$sql = "SELECT IDTR,
						(SELECT TOP (1) [TELEFONO] FROM [BDES_POS].[dbo].[ESCLIENTESPOS] WHERE RIF = cab.IDCLIENTE) AS TELEFONO,
						(CONVERT(VARCHAR(10), FECHAHORA, 105)+' '+CONVERT(VARCHAR(5), FECHAHORA, 108)) AS FECHAHORA,
						(CASE WHEN FECHA_PICKING IS NULL THEN '' ELSE 
						(CONVERT(VARCHAR(10), FECHA_PICKING, 105)+' '+CONVERT(VARCHAR(5), FECHA_PICKING, 108)) END) AS FECHA_PICKING,
						(CASE WHEN FECHA_PROCESADO IS NULL THEN '' ELSE 
						(CONVERT(VARCHAR(10), FECHA_PROCESADO, 105)+' '+CONVERT(VARCHAR(5), FECHA_PROCESADO, 108)) END) AS FECHA_PROCESADO,
						RAZON, GRUPOC, CREADO_POR, PICKING_POR, PROCESADO_POR, paymentStatus AS FPAGO, paymentModule AS TPAGO,
						(SELECT ROUND(SUM((COALESCE(PRECIOOFERTA, PRECIO)*(1+(PORC/100)))*CANTIDAD),2) FROM BDES_POS.dbo.DBVENTAS_TMP_DET WHERE IDTR = cab.IDTR) AS total,
						montodomicilio, descuentos
						FROM BDES_POS.dbo.DBVENTAS_TMP AS cab
						WHERE GRUPOC != 3
						ORDER BY GRUPOC, cab.FECHAHORA ASC";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$pedidopor = '';
					$pickingpor = '';
					$procesadopor = '';
					if($row['GRUPOC']==0) {
						$pedidopor = '<i class="fas fa-donate fa-2x mr-1 mt-1 mb-auto float-left"></i>'.
									 '<span class="mbadge m-0 p-0 text-left"><b>Pedido x: </b><br>'.$row['CREADO_POR'].'</span>';
					}
					if($row['GRUPOC']==1) {
						$pickingpor = '<i class="fas fa-cart-arrow-down fa-2x mr-1 mt-auto mb-auto float-left"></i>'.
									  '<span class="mbadge m-0 p-0 text-left"><b>Picking x: </b><br>'.$row['PICKING_POR'].'</span>';
					}
					if($row['GRUPOC']==2) {
						$procesadopor = '<i class="fas fa-cash-register fa-2x mr-1 mt-auto mb-auto float-left"></i>'.
										'<span class="mbadge m-0 p-0 text-left"><b>Procesado x: </b><br>'.$row['PROCESADO_POR'].'</span>';
					}
					$datos[] = [
						'nrodoc'         => $row['IDTR'],
						'nombre'  		 => $row['RAZON'].'<br><i class="fas fa-phone"></i> '.$row['TELEFONO'],
						'grupoc'         => $row['GRUPOC'],
						'fechapedido'    => $row['FECHAHORA'],
						'fechapicking'   => $row['FECHA_PICKING'],
						'fechaprocesado' => $row['FECHA_PROCESADO'],
						'pedidopor'      => $pedidopor,
						'pickingpor'     => $pickingpor,
						'procesadopor'   => $procesadopor,
						'fpago'          => ($row['FPAGO']==21?
											'<div class="w-100 text-center"><img height="35px" class="drop" src="dist/img/paguelofacil.png" title="'.$row['TPAGO'].'"></div>':
						                    ($row['FPAGO']==20?
											'<div class="w-100 text-center"><img height="35px" class="drop" src="dist/img/instapago.png" title="'.$row['TPAGO'].'"></div>':
											($row['FPAGO']==15?
											'<div class="w-100 text-center"><img height="35px" class="drop" src="dist/img/datafono.png" title="'.$row['TPAGO'].'"></div>':
											($row['FPAGO']==14?
											'<div class="w-100 text-center"><img height="35px" class="drop" src="dist/img/monedas.png" title="'.$row['TPAGO'].'"></div>':
											'<div class="w-100 text-center"></div>')))),
						'monto'			 => $row['total']+$row['montodomicilio']-$row['descuentos'],
					];
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'listaDocsweb':
				$idpara = explode(',', $idpara);
				$sql = "SELECT IDTR, 
						(CONVERT(VARCHAR(10), FECHAHORA, 105)+' '+CONVERT(VARCHAR(5), FECHAHORA, 108)) AS FECHAHORA, RAZON, GRUPOC, FECHA_PROCESADO, PROCESADO_POR,
						(SELECT COUNT(IDTR) FROM BDES_POS.dbo.DBVENTAS_TMP_DET
							WHERE IMAI = 1 AND IDTR = cab.IDTR) AS items,
						(SELECT SUM(PEDIDO) FROM BDES_POS.dbo.DBVENTAS_TMP_DET
							WHERE IMAI = 1 AND IDTR = cab.IDTR) AS pedidos,
						(SELECT SUM(CANTIDAD) FROM BDES_POS.dbo.DBVENTAS_TMP_DET
							WHERE IMAI = 1 AND IDTR = cab.IDTR) AS unidades,
						(SELECT SUM(ROUND((PRECIO*(1+(PORC/100)))*CANTIDAD, 2)) FROM BDES_POS.dbo.DBVENTAS_TMP_DET
							WHERE IMAI = 1 AND IDTR = cab.IDTR) AS total
						FROM BDES_POS.dbo.DBVENTAS_TMP cab
						WHERE GRUPOC = 3 AND CAST(FECHAHORA AS DATE) BETWEEN '$idpara[0]' AND '$idpara[1]'
						ORDER BY GRUPOC";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$xfactura = '<div class="d-flex w-100 align-items-center">';
					$xfactura.= '<div style="width: 20%"><i class="fas fa-cash-register fa-2x"></i></div>';
					$xfactura.= '<div style="width: 80%" class="mbadge">'.$row['PROCESADO_POR'].'</div>';
					$xfactura.= '</div';
					$xfactura = ($row['GRUPOC']==1) ? $xfactura : '';
					$datos[] = [
						'nrodoc'   => $row['IDTR'],
						'fecha'    => date('d-m-Y H:i', strtotime($row['FECHAHORA'])),
						'nombre'   => '<span class="btn-link p-0 m-0" style="cursor: pointer"'.
									 '   onclick="verDetalle('.$row['IDTR'].')" title="Ver Prefactura">'.
									 	 $row['RAZON'].
									 ' </span>',
						'items'    => $row['items'],
						'pedidos'  => $row['pedidos'],
						'unidades' => $row['unidades'],
						'total'    => $row['total'],
					];
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'datosPreFactura':
				// Se crea el query para obtener los datos
				$sql = "SELECT cab.IDTR, 
							(CONVERT(VARCHAR(10), cab.FECHAHORA, 105)+' '+CONVERT(VARCHAR(5), cab.FECHAHORA, 108)) AS FECHA,
							cli.RAZON, cli.DIRECCION, cli.TELEFONO, cli.EMAIL, cab.IDCLIENTE, det.ARTICULO AS material,
							art.descripcion AS ARTICULO, det.PEDIDO, det.CANTIDAD, ROUND(det.PORC, 0) AS PORC,
							ROUND((det.PRECIO*(1+(det.PORC/100))), 2) AS PRECIO,
							ROUND((det.PRECIO*(1+(det.PORC/100)))*det.CANTIDAD, 2) AS TOTAL,
							(det.PRECIO*det.CANTIDAD) AS SUBTOTAL, (det.COSTO*det.CANTIDAD) AS COSTO
						FROM BDES_POS.dbo.DBVENTAS_TMP AS cab
							INNER JOIN BDES_POS.dbo.ESCLIENTESPOS cli ON cli.RIF = cab.IDCLIENTE
							INNER JOIN BDES_POS.dbo.DBVENTAS_TMP_DET det ON det.IDTR = cab.IDTR
							INNER JOIN BDES.dbo.ESARTICULOS art ON art.codigo = det.ARTICULO
						WHERE det.IMAI = 1 AND cab.IDTR = $idpara
						ORDER BY det.LINEA";
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'nrodoc'      => $row['IDTR'],
						'fecha'       => date('d-m-Y', strtotime($row['FECHA'])),
						'hora'        => date('h:i a', strtotime($row['FECHA'])),
						'razon'       => $row['RAZON'],
						'direccion'   => ucwords(strtolower(substr($row['DIRECCION'], 0, 100))),
						'telefono'    => $row['TELEFONO'],
						'email'       => $row['EMAIL'],
						'cliente'     => $row['IDCLIENTE'],
						'material'    => $row['material'],
						'descripcion' => $row['ARTICULO'],
						'pedido'      => $row['PEDIDO']*1,
						'cantidad'    => $row['CANTIDAD']*1,
						'precio'      => $row['PRECIO']*1,
						'impuesto'    => $row['PORC']*1,
						'total'       => $row['TOTAL']*1,
						'subtotal'    => $row['SUBTOTAL']*1,
						'costo'       => $row['COSTO']*1,
					];
				}
				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;
			case 'listPadreComptos':
				extract($_POST);
				$buscar = ($buscaren==1) ? "AND (art.codigo = $idpara OR bar.barra = '$idpara')" :
										  "AND LOWER(art.descripcion) LIKE LOWER('%$idpara%')";
				$sql = "SELECT DISTINCT art.codigo, COALESCE(bar.barra, '') AS barra, art.descripcion,
							COALESCE((bkp.Cantidad-bkp.comprometida-bkp.usada), 0) AS exist_padre,
							(CASE WHEN ac.codigo_padre IS NOT NULL THEN 1 ELSE 0 END) AS artpadre,
							COALESCE(ac.PorcInv_Padre, 0) AS porcinv_padre, art.costo,
							(art.PRECIO1*(1+(art.impuesto/100))) AS  precio
						FROM BDES.dbo.ESARTICULOS art
						LEFT JOIN (SELECT escodigo, MAX(DISTINCT barra) AS barra
							FROM BDES.dbo.ESCodigos
							WHERE CAST(escodigo AS VARCHAR) != barra 
							GROUP BY escodigo) AS bar ON bar.escodigo = art.codigo
						LEFT JOIN BDES.dbo.BiKardexExistencias bkp ON bkp.articulo=art.codigo
						LEFT JOIN BDES.dbo.DBArticulosCompuestos ac ON ac.codigo_padre=art.codigo AND ac.eliminado=0
						WHERE art.precio1>0 AND art.activo=1
						AND art.codigo NOT IN(SELECT codigo_hijo FROM BDES.dbo.DBArticulosCompuestos) ".$buscar;
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$decimales = $row['exist_padre'] - intval($row['exist_padre']);
					$enteros = $row['exist_padre'] - $decimales;
					$exist_padre = number_format($enteros, 0).'.<sub>'.substr(number_format($decimales, 3), 2).'</sub>';
					$txt = '<button type="button" title="Agregar Artículo" onclick="' .
								" addarticulo('" . $row['codigo'] . "','" . $row['barra'] . 
									"','" . $row['descripcion'] . "'," . round($row['exist_padre']*1,3) .
									"," . $row['porcinv_padre']*1 . "," . $row['costo']*1 . "," .
									$row['precio']*1 . ", '" . $exist_padre . "')" .
								'" class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
								' style="white-space: normal; line-height: 1;">' . ucwords($row['descripcion']) .
							'</button>';
					$datos[] = [
						'codigo'        => $row['codigo'],
						'barra'         => $row['barra'],
						'descripcion'   => $txt,
						'exist_padre'   => $exist_padre,
						'artpadre'      => $row['artpadre'],
						'porcinv_padre' => $row['porcinv_padre']*1,
						'descripcion2'  => $row['descripcion'],
						'exist_padre2'  => $row['exist_padre']*1,
						'costo'         => $row['costo'],
						'vcosto'        => number_format($row['costo']*1, 2),
						'precio'        => number_format($row['precio']*1, 2)
					];
				}
				echo json_encode($datos);
				break;
			case 'listaHijos':
				$datos = [];
				if($idpara!='') {
					$sql = "SELECT codigo_hijo, art.descripcion, porcmerma, valorempaque,
								valormo, ac.cantidad, rentabilidad AS rent_hijo, eliminado, tipo,
								CAST(
									(CASE WHEN artp.precio1 = 0 THEN 0
									ELSE (round(((artp.precio1-artp.costo)/artp.precio1*100), 2))
									END) 
								AS NUMERIC(5,2)) AS rent_padre,
								(((artp.costo*ac.cantidad)*(1+(ac.PorcMerma/100)))+ac.ValorEmpaque+ac.ValorMO) AS costo_hijo,
								(CASE WHEN artp.precio1 = 0 THEN 0 ELSE
									(CASE WHEN ac.tipo = 0 THEN
										((((artp.costo*ac.cantidad)*(1+(ac.PorcMerma/100)))+ac.ValorEmpaque+ac.ValorMO)/
										(100-ROUND(((artp.precio1-artp.costo)/artp.precio1*100), 2))*100)
										*(1+(artp.impuesto/100))
									ELSE
										((((artp.costo*ac.cantidad)*(1+(ac.PorcMerma/100)))+ac.ValorEmpaque+ac.ValorMO)/
										(100-ac.Rentabilidad)*100)*(1+(art.impuesto/100))
									END)
								END) AS precio1_hijo
							FROM BDES.dbo.DBArticulosCompuestos ac
							INNER JOIN BDES.dbo.ESARTICULOS art ON art.codigo = ac.codigo_hijo
							INNER JOIN BDES.dbo.ESARTICULOS artp ON artp.codigo = ac.codigo_padre
							WHERE ac.codigo_padre = $idpara";
					$sql = $connec->query($sql);
					if(!$sql) print_r($connec->errorInfo());
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[] = [
							'codigo'       => $row['codigo_hijo'],
							'descripcion'  => $row['descripcion'],
							'merma'        => $row['porcmerma']*1,
							'relacion'     => $row['cantidad']*1,
							'empaque'      => $row['valorempaque']*1,
							'operativo'    => $row['valormo']*1,
							'rent_hijo'    => $row['rent_hijo']*1,
							'eliminado'    => $row['eliminado']*1,
							'tipo'         => $row['tipo']*1,
							'rent_padre'   => $row['rent_padre']*1,
							'preciohijo'   => number_format($row['precio1_hijo']*1, 2),
							'costohijo'    => number_format($row['costo_hijo']*1, 2)
						];
					}
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'eliminarHijo':
				$idpara = explode('¬', $idpara);
				$sql = "UPDATE BDES.dbo.DBArticulosCompuestos SET
						eliminado = $idpara[1]
						WHERE codigo_padre = $idpara[2]
						AND codigo_hijo = $idpara[0]";
				$sql = $connec->query($sql);
				if(!$sql) {
					echo 0;
					print_r($connec->errorInfo());
				} else {
					echo 1;
				}
				break;
			case 'deleteHijo':
				extract($_POST);
				$sql = "DELETE FROM BDES.dbo.DBArticulosCompuestos
						WHERE codigo_padre = $padre AND codigo_hijo = $hijo";
				$sql = $connec->query($sql);
				if(!$sql)  {
					echo 0;
					print_r($connec->errorInfo());
				} else {
					$sql = "SELECT COUNT(*) AS cuenta FROM BDES.dbo.DBArticulosCompuestos
							WHERE codigo_padre = $padre";
					$sql = $connec->query($sql);
					$row = $sql->fetch(\PDO::FETCH_ASSOC);
					if($row['cuenta']==0) {
						echo 2;
					} else {
						echo 1;
					}
				}
				break;
			case 'guardarPadre':
				extract($_POST);
				$sql = "IF EXISTS(SELECT * FROM BDES.dbo.DBArticulosCompuestos
								  WHERE codigo_padre = $padre)
						BEGIN 
							UPDATE BDES.dbo.DBArticulosCompuestos SET
								PorcInv_Padre = $pinvp
							WHERE codigo_padre = $padre
						END";
				$sql = $connec->query($sql);
				if(!$sql) {
					echo 0;
					print_r($connec->errorInfo());
				} else {
					echo 1;
				}
				break;
			case 'guardarHijo':
				extract($_POST);
				$sql = "UPDATE BDES.dbo.DBArticulosCompuestos SET
							PorcMerma = $merma,
							ValorEmpaque = $empaq,
							ValorMO = $opera,
							Cantidad = $relac,
							Tipo = $tipo,
							Rentabilidad = $renta
						WHERE codigo_padre = $padre
						AND codigo_hijo = $idpara ";
				$sql = $connec->query($sql);
				if(!$sql) {
					echo 0;
					print_r($connec->errorInfo());
				} else {
					echo 1;
				}
				break;
			case 'agregarHijo':
				extract($_POST);
				$sql = "INSERT INTO BDES.dbo.DBArticulosCompuestos
						VALUES($padre, $pinvp, 1, $hijo, $merma, $empaq, $opera, $tipo, $relac, $renta, 0)";
				$sql = $connec->query($sql);
				if(!$sql) {
					if($connec->errorInfo()[0]==23000) {
						echo 2;
					} else {
						echo 0;
						print_r($connec->errorInfo());
					}
				} else {
					echo 1;
				}
				break;
			case 'lstHijos':
				$sql = "SELECT codigo, descripcion
						FROM BDES.dbo.ESARTICULOS
						WHERE activo = 1 AND
						(lower(descripcion) LIKE lower('%$idpara%') OR CAST(codigo AS VARCHAR) = '$idpara')
						AND codigo NOT IN(SELECT codigo_padre FROM
									  BDES.dbo.DBArticulosCompuestos
									  UNION
									  SELECT codigo_hijo FROM
									  BDES.dbo.DBArticulosCompuestos)";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$txt = '<button type="button" title="Agregar Artículo" onclick="' .
								" addHijo('" . $row['codigo'] . "', '" . $row['descripcion'] . "')" .
								'" class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
								' style="white-space: normal; line-height: 1;">' . ucwords($row['descripcion']) .
							'</button>';
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => $txt,
					];
				}
				echo json_encode($datos);
				break;
			case 'listaCompuestos':
				$sql = "SELECT DISTINCT codigo_padre AS codpadre, artp.descripcion AS despadre,
							COALESCE((bkp.cantidad-bkp.comprometida-bkp.usada), 0) AS exipadre,
							ac.PorcInv_Padre AS porhijos, artp.costo AS cospadre,
							COALESCE((bkp.cantidad-bkp.comprometida-bkp.usada)*
								(ac.PorcInv_Padre/100), 0) AS dishijos,
							(CASE WHEN artp.precio1 = 0 THEN 0
							ELSE (artp.precio1*(1+(artp.impuesto/100))) END) AS pvppadre
						FROM BDES.dbo.DBArticulosCompuestos AS ac
						LEFT JOIN BDES.dbo.ESARTICULOS AS artp ON artp.codigo = ac.codigo_padre
						LEFT JOIN BDES.dbo.BIKardexExistencias AS bkp ON
							bkp.articulo = ac.codigo_padre
						WHERE eliminado = 0";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datospadre = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datospadre[] = [
						'padre'     => 1,
						'codpadre'  => $row['codpadre'],
						'despadre'  => $row['despadre'],
						'exipadre'  => number_format($row['exipadre']*1, 2),
						'porhijos'  => number_format($row['porhijos']*1, 2),
						'dishijos'  => number_format($row['dishijos']*1, 2),
						'cospadre'  => number_format($row['cospadre']*1, 2),
						'pvppadre'  => number_format($row['pvppadre']*1, 2),
						'codhijo'   => '',
						'deshijo'   => '',
						'canthijo'  => '',
						'vempaque'  => '',
						'vmanoo'    => '',
						'mermahijo' => '',
						'costohijo' => '',
						'margen'    => '',
						'pvphijo'   => '',
					];
				}
				$sql = "SELECT hc.codpadre, hc.codhijo, hc.deshijo, hc.canthijo, hc.vempaque,
							hc.vmanoo, hc.mermahijo,
							(((artp.costo*hc.canthijo)*(1+(hc.mermahijo/100)))+hc.vempaque+hc.vmanoo) AS costohijo,
							(CASE WHEN hc.tipo = 0 THEN 
								(CASE WHEN artp.precio1 = 0 THEN 0 ELSE
									(ROUND(((artp.precio1-artp.costo)/artp.precio1*100), 2))
								END)
							ELSE
								hc.Rentabilidad
							END) AS margen,
							(CASE WHEN artp.precio1 = 0 THEN 0 ELSE
								(CASE WHEN hc.tipo = 0 THEN
									((((artp.costo*hc.canthijo)*(1+(hc.mermahijo/100)))+hc.vempaque+hc.vmanoo)/
									(100-ROUND(((artp.precio1-artp.costo)/artp.precio1*100), 2))*100)
									*(1+(artp.impuesto/100))
								ELSE
									((((artp.costo*hc.canthijo)*(1+(hc.mermahijo/100)))+hc.vempaque+hc.vmanoo)/
									(100-hc.Rentabilidad)*100)*(1+(artp.impuesto/100))
								END)
							END) AS pvphijo
						FROM
							(SELECT codigo_padre AS codpadre, codigo_hijo AS codhijo, arth.descripcion AS deshijo,
								ac.PorcMerma AS mermahijo, ac.Cantidad AS canthijo, ac.ValorEmpaque AS vempaque,
								ac.ValorMO AS vmanoo, ac.tipo, ac.Rentabilidad
							FROM BDES.dbo.DBArticulosCompuestos AS ac
							LEFT JOIN BDES.dbo.ESARTICULOS AS arth ON arth.codigo = ac.codigo_hijo
							LEFT JOIN BDES.dbo.BIKardexExistencias AS bkh ON
								bkh.articulo = ac.codigo_hijo
							WHERE eliminado = 0) AS hc
						LEFT JOIN BDES.dbo.ESARTICULOS AS artp ON artp.codigo = hc.codpadre";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datoshijos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datoshijos[] = [
						'padre'     => 2,
						'codpadre'  => $row['codpadre'],
						'despadre'  => '',
						'exipadre'  => '',
						'porhijos'  => '',
						'dishijos'  => '',
						'cospadre'  => '',
						'pvppadre'  => '',
						'codhijo'   => $row['codhijo'],
						'deshijo'   => $row['deshijo'],
						'canthijo'  => number_format($row['canthijo']*1, 2),
						'vempaque'  => number_format($row['vempaque']*1, 2),
						'vmanoo'    => number_format($row['vmanoo']*1, 2),
						'mermahijo' => number_format($row['mermahijo']*1, 0),
						'costohijo' => number_format($row['costohijo']*1, 2),
						'margen'    => number_format($row['margen']*1, 2),
						'pvphijo'   => number_format($row['pvphijo']*1, 2),
					];
				}
				$datos = array_merge($datospadre, $datoshijos);
				foreach ($datos as $clave => $fila) {
					$orden1[$clave] = $fila['codpadre'];
					$orden2[$clave] = $fila['padre'];
				}
				array_multisort($orden1, SORT_ASC, $orden2, SORT_ASC, $datos);
				echo json_encode($datos);
				break;
			case 'exportCompuestos':
				// Se prepara el query para obtener los datos
				$sql = "SELECT hc.codpadre, hc.despadre, hc.exipadre, (hc.porhijos/100) AS porhijos, hc.disphijos,
							hc.costopadre, (hc.margenpadre/100) AS margenpadre, hc.pvppadre,
							(hc.impuesto/100) AS impuesto,
							hc.codhijo, hc.deshijo, hc.canthijo, hc.vempaque, hc.vmanoo,
							(hc.mermahijo/100) AS mermahijo,
							(((artp.costo*hc.canthijo)*(1+(hc.mermahijo/100)))+hc.vempaque+hc.vmanoo) AS costohijo,
							(hc.Rentabilidad/100) AS margenhijo,
							(CASE WHEN hc.tipo = 1 THEN 'Margen Hijo' ELSE 'Margen Padre' END) AS tipomargen,
							(CASE WHEN artp.precio1 = 0 THEN 0 ELSE
								(CASE WHEN hc.tipo = 0 THEN
									((((artp.costo*hc.canthijo)*(1+(hc.mermahijo/100)))+hc.vempaque+hc.vmanoo)/
									(100-ROUND(((artp.precio1-artp.costo)/artp.precio1*100), 2))*100)
									*(1+(artp.impuesto/100))
								ELSE
									((((artp.costo*hc.canthijo)*(1+(hc.mermahijo/100)))+hc.vempaque+hc.vmanoo)/
									(100-hc.Rentabilidad)*100)*(1+(artp.impuesto/100))
								END)
							END) AS pvphijo
						FROM
							(SELECT
								codigo_padre AS codpadre, artp.descripcion AS despadre,
								COALESCE((bkp.cantidad-bkp.comprometida-bkp.usada), 0) AS exipadre,
								ac.PorcInv_Padre AS porhijos, artp.impuesto AS impuesto,
								COALESCE((bkp.cantidad-bkp.comprometida-bkp.usada)*
									(ac.PorcInv_Padre/100), 0) AS disphijos,
								(CASE WHEN artp.precio1 = 0 THEN 0 ELSE
									ROUND(((artp.precio1-artp.costo)/artp.precio1*100), 2) END) margenpadre,
								artp.costo AS costopadre,
								(CASE WHEN artp.precio1 = 0 THEN 0
									ELSE (artp.precio1*(1+(artp.impuesto/100)))
								END) AS pvppadre,
								codigo_hijo AS codhijo, arth.descripcion AS deshijo,
								ac.PorcMerma AS mermahijo, ac.Cantidad AS canthijo, ac.ValorEmpaque AS vempaque,
								ac.ValorMO AS vmanoo,	ac.tipo, ac.Rentabilidad
							FROM BDES.dbo.DBArticulosCompuestos AS ac
							LEFT JOIN BDES.dbo.ESARTICULOS AS arth ON arth.codigo = ac.codigo_hijo
							LEFT JOIN BDES.dbo.ESARTICULOS AS artp ON artp.codigo = ac.codigo_padre
							LEFT JOIN BDES.dbo.BIKardexExistencias AS bkh ON bkh.articulo = ac.codigo_hijo
							LEFT JOIN BDES.dbo.BIKardexExistencias AS bkp ON bkp.articulo = ac.codigo_padre) AS hc
						LEFT JOIN BDES.dbo.ESARTICULOS AS artp ON artp.codigo = hc.codpadre";
				// Ejecutar connsulta en BBDD
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$fecha1 = date('d/m/Y', strtotime($fecha));
				require_once "../Classes/PHPExcel.php";
				require_once "../Classes/PHPExcel/Writer/Excel5.php"; 
				$objPHPExcel = new PHPExcel();
				// Set document properties
				$objPHPExcel->getProperties()
					->setCreator("Dashboard")
					->setLastModifiedBy("Dashboard")
					->setTitle("Reporte Articulos Compuestos CV ".$fecha1)
					->setSubject("Reporte Articulos Compuestos CV ".$fecha1)
					->setDescription("Reporte Articulos Compuestos CV ".$fecha1." generado usando el Dashboard.")
					->setKeywords("Office 2007 openxml php")
					->setCategory("Reporte Articulos Compuestos CV ".$fecha1);
				$objPHPExcel->setActiveSheetIndex(0);
				$icorr = date('dmy', strtotime($fecha));
				$objPHPExcel->getActiveSheet()
					->SetCellValue('A1',	'INFROMACIÓN DEL ARTÍCULO PADRE')
					->mergeCells('A1:I1')
					->getStyle('A1:I1')
					->getAlignment()
					->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
				$objPHPExcel->getActiveSheet()
					->SetCellValue('J1',	'INFROMACIÓN DEL ARTÍCULO HIJO')
					->mergeCells('J1:S1')
					->getStyle('J1:S1')
					->getAlignment()
					->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
				$objPHPExcel->getActiveSheet()
					->SetCellValue('A2', 'CÓDIGO')
					->SetCellValue('B2', 'DESCRIPCIÓN')
					->SetCellValue('C2', 'EXISTENCIA')
					->SetCellValue('D2', '%PARA HIJOS')
					->SetCellValue('E2', 'DISPONIBLE HIJOS')
					->SetCellValue('F2', 'COSTO')
					->SetCellValue('G2', 'MARGEN')
					->SetCellValue('H2', 'IMPUESTO')
					->SetCellValue('I2', 'PRECIO');
				$objPHPExcel->getActiveSheet()
					->SetCellValue('J2', 'CÓDIGO')
					->SetCellValue('K2', 'DESCRIPCIÓN')
					->SetCellValue('L2', 'CONVERSIÓN')
					->SetCellValue('M2', 'VALOR EMPAQUE')
					->SetCellValue('N2', 'VALOR MANO OBRA')
					->SetCellValue('O2', '%MERMA')
					->SetCellValue('P2', 'COSTO')
					->SetCellValue('Q2', 'MARGEN')
					->SetCellValue('R2', 'TIPO MARGEN')
					->SetCellValue('S2', 'PRECIO');
				$rowCount = 3;
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A'.$rowCount, $row['codpadre'])
						->SetCellValue('B'.$rowCount, $row['despadre'])
						->SetCellValue('C'.$rowCount, $row['exipadre'])
						->SetCellValue('D'.$rowCount, $row['porhijos'])
						->SetCellValue('E'.$rowCount, $row['disphijos'])
						->SetCellValue('F'.$rowCount, $row['costopadre'])
						->SetCellValue('G'.$rowCount, $row['margenpadre'])
						->SetCellValue('H'.$rowCount, $row['impuesto'])
						->SetCellValue('I'.$rowCount, $row['pvppadre'])
						->SetCellValue('J'.$rowCount, $row['codhijo'])
						->SetCellValue('K'.$rowCount, $row['deshijo'])
						->SetCellValue('L'.$rowCount, $row['canthijo'])
						->SetCellValue('M'.$rowCount, $row['vempaque'])
						->SetCellValue('N'.$rowCount, $row['vmanoo'])
						->SetCellValue('O'.$rowCount, $row['mermahijo'])
						->SetCellValue('P'.$rowCount, $row['costohijo'])
						->SetCellValue('Q'.$rowCount, $row['margenhijo'])
						->SetCellValue('R'.$rowCount, $row['tipomargen'])
						->SetCellValue('S'.$rowCount, $row['pvphijo']);
					$rowCount++;
				}
				$objPHPExcel->getActiveSheet()
					->getStyle('C3:C'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED3);
				$objPHPExcel->getActiveSheet()
					->getStyle('D3:D'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE_00);
				$objPHPExcel->getActiveSheet()
					->getStyle('E3:E'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED3);
				$objPHPExcel->getActiveSheet()
					->getStyle('F3:F'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
				$objPHPExcel->getActiveSheet()
					->getStyle('G3:H'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE_00);
				$objPHPExcel->getActiveSheet()
					->getStyle('I3:I'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
				$objPHPExcel->getActiveSheet()
					->getStyle('L3:L'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED3);
				$objPHPExcel->getActiveSheet()
					->getStyle('M3:N'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
				$objPHPExcel->getActiveSheet()
					->getStyle('O3:O'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE_00);
				$objPHPExcel->getActiveSheet()
					->getStyle('P3:P'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
				$objPHPExcel->getActiveSheet()
					->getStyle('Q3:Q'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE_00);
				$objPHPExcel->getActiveSheet()
					->getStyle('S3:S'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
				$objPHPExcel->getActiveSheet()->getStyle('A1:S2')->getFont()->setBold(true);
				$objPHPExcel->getActiveSheet()->getStyle('A1:I2')
					->getFill()
					->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
					->getStartColor()
					->setRGB('A2C8EB');
				$objPHPExcel->getActiveSheet()->getStyle('J1:S2')
					->getFill()
					->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
					->getStartColor()
					->setRGB('A2EBCF');
				$objPHPExcel->getActiveSheet()->freezePane('A3');
				$rowCount--;				
				$objPHPExcel->getActiveSheet()->setAutoFilter('A2:S'.$rowCount);
				foreach (range('A', $objPHPExcel->getActiveSheet()->getHighestDataColumn()) as $col) {
					$objPHPExcel
						->getActiveSheet()
						->getColumnDimension($col)
						->setAutoSize(true);
				}
				$objPHPExcel->getActiveSheet()->setSelectedCell('A3');
				// Rename worksheet
				$objPHPExcel->getActiveSheet()->setTitle('Articulos Compuestos');
				// Set active sheet index to the first sheet, so Excel opens this as the first sheet
				$objPHPExcel->setActiveSheetIndex(0);
				// Redirect output to a client’s web browser (Excel5)
				header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
				header('Content-Disposition: attachment;filename="ArtCompstos.xls"');
				header('Cache-Control: max-age=0');
				// If you're serving to IE 9, then the following may be needed
				header('Cache-Control: max-age=1');
				// If you're serving to IE over SSL, then the following may be needed
				header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
				header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
				header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
				header ('Pragma: public'); // HTTP/1.0
				$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
				$objWriter->save('../tmp/ArtCompstos_'.$icorr.'.xls');
				echo json_encode(array('enlace'=>'tmp/ArtCompstos_'.$icorr.'.xls', 'archivo'=>'ArtCompstos_'.$icorr.'.xls'));
				break;
			default:
				# code...
				break;
		}
		// Se cierra la conexion
		$connec = null;
	} catch (PDOException $e) {
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	}
?>