<?php
/**
* NOTA SOBRE LA LICENCIA DE USO DEL SOFTWARE
* 
* El uso de este software está sujeto a las Condiciones de uso de software que
* se incluyen en el paquete en el documento "Aviso Legal.pdf". También puede
* obtener una copia en la siguiente url:
* http://www.redsys.es/wps/portal/redsys/publica/areadeserviciosweb/descargaDeDocumentacionYEjecutables
* 
* Redsys es titular de todos los derechos de propiedad intelectual e industrial
* del software.
* 
* Quedan expresamente prohibidas la reproducción, la distribución y la
* comunicación pública, incluida su modalidad de puesta a disposición con fines
* distintos a los descritos en las Condiciones de uso.
* 
* Redsys se reserva la posibilidad de ejercer las acciones legales que le
* correspondan para hacer valer sus derechos frente a cualquier infracción de
* los derechos de propiedad intelectual y/o industrial.
* 
* Redsys Servicios de Procesamiento, S.L., CIF B85955367
*/

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');
include(dirname(__FILE__).'/redsys.php');

if(!function_exists("escribirLog")) {
	require_once('apiRedsys/redsysLibrary.php');
}
if(!class_exists("RedsysAPI")) {
	require_once('apiRedsys/apiRedsysFinal.php');
}

//Tools::displayFileAsDeprecated();

$accesoDesde = "";

if (!empty($_POST)) {
	$accesoDesde = 'POST';
} else if (!empty($_GET)) {
	$accesoDesde = 'GET';
}
	$logActivo = Configuration::get('REDSYS_LOG');
	$idLog = generateIdLog();
escribirLog($idLog." -- "."Llegamos1",$logActivo);

if ($accesoDesde === 'POST' || $accesoDesde === 'GET') {
	escribirLog($idLog." -- "."Llegamos2",$logActivo);

	/** Recoger datos de respuesta **/
	$version     = Tools::getValue('Ds_SignatureVersion');
	$datos    = Tools::getValue('Ds_MerchantParameters');
	$firma_remota    = Tools::getValue('Ds_Signature');

	// Se crea Objeto
	$miObj = new RedsysAPI;
	
	/** Se decodifican los datos enviados y se carga el array de datos **/
	$decodec = $miObj->decodeMerchantParameters($datos);

	/** Clave **/
	$kc = Configuration::get('REDSYS_CLAVE256');
	
	/** Se calcula la firma **/
	$firma_local = $miObj->createMerchantSignatureNotif($kc,$datos);
	
	/** Extraer datos de la notificación **/
	$total     = $miObj->getParameter('Ds_Amount');
	$pedido    = $miObj->getParameter('Ds_Order');
	$codigo    = $miObj->getParameter('Ds_MerchantCode');
	$moneda    = $miObj->getParameter('Ds_Currency');
	$respuesta = $miObj->getParameter('Ds_Response');
	$id_trans = $miObj->getParameter('Ds_AuthorisationCode');
	
	/** Código de comercio **/
	$codigoOrig = Configuration::get('REDSYS_CODIGO');
	
	/** Pedidos Cancelados **/
	$error_pago = Configuration::get('REDSYS_ERROR_PAGO');
	
	/** Log de Errores **/

	$pedidoSecuencial = $pedido;
	$pedido = intval(substr($pedidoSecuencial, 0, 11));
	/** VALIDACIONES DE LIBRERÍA **/
	if ($firma_local === $firma_remota
		&& checkImporte($total)
		&& checkPedidoNum($pedido)
		&& checkFuc($codigo)
		&& checkMoneda($moneda)
		&& checkRespuesta($respuesta)) {
		if ($accesoDesde === 'POST') {

			/** Creamos los objetos para confirmar el pedido **/
			$context = Context::getContext();
			$cart = new Cart($pedido);
			$redsys = new redsys();

			/** Validamos Objeto carrito **/
			if ($cart->id_customer == 0
				|| $cart->id_address_delivery == 0
				|| $cart->id_address_invoice == 0
				|| !$redsys->active) {
				Tools::redirect('index.php?controller=order&step=1');
			}
			/** Validamos Objeto cliente **/
			$customer = new Customer((int)$cart->id_customer);
			
			/** Donet **/
			Context::getContext()->customer = $customer;
			$address = new Address((int)$cart->id_address_invoice);
			Context::getContext()->country = new Country((int)$address->id_country);
			Context::getContext()->customer = new Customer((int)$cart->id_customer);
			Context::getContext()->language = new Language((int)$cart->id_lang);
			Context::getContext()->currency = new Currency((int)$cart->id_currency);			
			
			if (!Validate::isLoadedObject($customer)) {
				Tools::redirect('index.php?controller=order&step=1');
			}

			/** VALIDACIONES DE DATOS y LIBRERÍA **/
			//Total
			$totalCart = $cart->getOrderTotal(true, Cart::BOTH);
			escribirLog($idLog." -- "."totalPre: " . $totalCart,$logActivo);
			$totalOrig = number_format($totalCart, 2, '', '');
			escribirLog($idLog." -- "."totalPost: " . $totalOrig,$logActivo);
			escribirLog($idLog." -- "."Total (" . $pedido . "): ".$totalOrig,$logActivo);
			
			
			// ID Moneda interno
			$currencyOrig = new Currency($cart->id_currency);
			// ISO Moneda
			$monedaOrig = $currencyOrig->iso_code_num;
			// DsResponse
			$respuesta = (int)$respuesta;

			if ($monedaOrig == $moneda && $totalOrig == $total && (int)$codigoOrig == (int)$codigo && $respuesta < 101 && checkAutCode($id_trans)) {
				/** Compra válida **/
				$mailvars['transaction_id'] = (int)$id_trans;
				$redsys->validateOrder($pedido, _PS_OS_PAYMENT_, $totalOrig/100, $redsys->displayName, null, $mailvars, (int)$cart->id_currency, false, $customer->secure_key);
				escribirLog($idLog." -- "."El pedido con ID de carrito " . $pedido . " es válido y se ha registrado correctamente.",$logActivo);
			} else {
				if (!($monedaOrig == $moneda)) {
					escribirLog($idLog." -- "."La moneda no coincide. ($monedaOrig : $moneda)",$logActivo);
				}
				if (!($totalOrig == $total)) {
					escribirLog($idLog." -- "."El importe total no coincide. ($totalOrig : $total)",$logActivo);
				}
				if (!((int)$codigoOrig == (int)$codigo)) {
					escribirLog($idLog." -- "."El código de comercio no coincide. ($codigoOrig : $codigo)",$logActivo);
				}
				if (!checkAutCode($id_trans)){
					escribirLog($idLog." -- "."Ds_AuthorisationCode inválido. ($id_trans)",$logActivo);
				}
				if ($error_pago=="no"){
					/** se anota el pedido como no pagado **/
					$redsys->validateOrder($pedido, _PS_OS_ERROR_, 0, $redsys->displayName, 'errores:'.$respuesta);
				}
				escribirLog($idLog." -- "."El pedido con ID de carrito " . $pedido . " es inválido.",$logActivo);
			}
		} else if ($accesoDesde === 'GET') {
			$respuesta = (int)$respuesta;
			if ($respuesta < 101) {
				/** Compra válida **/
				Tools::redirect('index.php?controller=order&step=1');
			} else {
				Tools::redirect('index.php?controller=order&step=1');
			}
		}
	} else {
		if ($accesoDesde === 'POST') {
			if (!($firma_local === $firma_remota)) {
				escribirLog($idLog." -- "."La firma no coincide.",$logActivo);
			}
			if (!checkImporte($total)){
				escribirLog($idLog." -- "."Ds_Amount inválido.",$logActivo);
			}
			if (!checkPedidoNum($pedido)){
				escribirLog($idLog." -- "."Ds_Order inválido.",$logActivo);
			}
			if (!checkFuc($codigo)){
				escribirLog($idLog." -- "."Ds_MerchantCode inválido.",$logActivo);
			}
			if (!checkMoneda($moneda)){
				escribirLog($idLog." -- "."Ds_Currency inválido.",$logActivo);
			}
			if (!checkRespuesta($respuesta)){
				escribirLog($idLog." -- "."Ds_Response inválido.",$logActivo);
			}
			if ($error_pago=="no"){
				/** se anota el pedido como no pagado **/
				$redsys->validateOrder($pedido, _PS_OS_ERROR_, 0, $redsys->displayName, 'errores:'.$respuesta);
			}
			escribirLog($idLog." -- "."Notificación: El pedido con ID de carrito " . $pedido . " es inválido.",$logActivo);
		} else if ($accesoDesde === 'GET') {
			Tools::redirect('index.php?controller=order&step=1');
		}
	}
}
?>