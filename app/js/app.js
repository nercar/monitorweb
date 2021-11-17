/**
 * apps.js
 * funciones especificas para obtener informacion de la BBDD sobre las ventas
 * al final funciones genericas para todos
 */

/**
 * funcion que carga en el div principal el contenido del dashboard
 */
function cargarcontenido(popcion_menu, menu_padre='') {
	setTimeout('$(document.body).removeClass("sidebar-open")',100);
	setTimeout('$(document.body).addClass("sidebar-collapse")',100);
	if (tomar_datos !== '') { tomar_datos.abort(); }
	
	$('.nav-link').removeClass('active menu-open');
	$('#' + popcion_menu).addClass('active');
	if(menu_padre!='') { $('#a_' + menu_padre).addClass('active'); }

	$('#contenido_ppal').load('app/db_' + popcion_menu + '.html?t=' + moment().format("HH:mm:ss"));
}

/**
 * Presenta una pantalla de cargando que permite cancelar la acción
 * @param  {string} acc show -> muestra la pantalla -- hide -> oculta la pantalla
 */
function cargando(acc){
	if(acc==='show'){
		$('.modal-backdrop').css('zIndex', 9998);
		$('#loading').modal('show');
	} else {
		$('.modal-backdrop').css('zIndex', 8888);
		$('#loading').modal('hide');
	}
}

/**
 * Presenta una pantalla de cargando que no permite cancelar la acción
 * @param  {string} acc show -> muestra la pantalla -- hide -> oculta la pantalla
 */
function cargando2(acc){
	if(acc==='show'){
		$('.modal-backdrop').css('zIndex', 9998);
		$('#loading2').modal('show');
	} else {
		$('.modal-backdrop').css('zIndex', 8888);
		$('#loading2').modal('hide');
	}
}

/**
 * Convierte la cadena en tipo titulo
 * @param  {string} str cadena a convertir cadena de ejemplo
 * @return {string} cadena convertida      Cadena De Ejemplo
 */
function capitalize(str) {
	if (typeof str !== 'string') return ''
	return str.toLowerCase().charAt(0).toUpperCase() + str.slice(1)
	// return str.toLowerCase().replace(/\b(\w)/g, s => s.toUpperCase());
}

/**
 * funcion solonumeros para limitar los inpuntbox a permitir solo numeros
 */
function soloNumeros(evt) {
	var e = evt || window.event;
	var key = e.keyCode || e.which;
	if (e.char == "'" || e.key == "'" ||
		e.char == "#" || e.key == "#" ||
		e.char == "$" || e.key == "$" ||
		e.char == "%" || e.key == "%" ||
		e.char == "&" || e.key == "&" ||
		e.char == "(" || e.key == "(" ||
		e.char == "." || e.key == "." ||
		e.char == "," || e.key == "," ||
		e.char == ">" || e.key == ">" ||
		e.char == ">" || e.key == ">" ||
		e.char == ":" || e.key == ":" ||
		e.char == "-" || e.key == "-" ||
		e.char == "*" || e.key == "*" ||
		e.char == "/" || e.key == "/" ||
		e.char == "+" || e.key == "+" )
		key = 0
	if (!e.shiftKey && !e.altKey && !e.ctrlKey &&
		// numbers   
		key >= 48 && key <= 57 ||
		// numbers pad
		key >= 96 && key <= 105 ||
		// Home and End
		key == 110 || key == 190 ||
		key == 35 || key == 36 ||
		// Backspace and Tab and Enter
		key == 8 || key == 9 || key == 13 ||
		// left and right arrows
		key == 37 || key == 39 ||
		// up and down arrows
		key == 38 || key == 40 ||
		// Del and Ins
		key == 46 || key == 116) {
		// input is VALID
	} else {
		// input is INVALID
		e.returnValue = false;
		if (e.preventDefault) e.preventDefault();
	}
};

function sinCarEspec(evt) {
	var e = evt || window.event;
	var key = e.keyCode || e.which;
	if (e.char == "'" || e.key == "'" ||
		e.char == "/" || e.key == "/" ||
		e.char == "\\" || e.key == "\\" )
		key = 0
	if (key > 0) {
		// input is VALID
	} else {
		// input is INVALID
		e.returnValue = false;
		if (e.preventDefault) e.preventDefault();
	}
};

function ascii_to_hexa(str)
{
	var arr1 = [];
	for (var n = 0, l = str.length; n < l; n ++) 
	{
		var hex = Number(str.charCodeAt(n)).toString(16);
		arr1.push(hex);
	}
	return arr1.join('');
}