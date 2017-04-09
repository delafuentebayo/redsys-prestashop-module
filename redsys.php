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
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
if(!function_exists("escribirLog")) {
	require_once('apiRedsys/redsysLibrary.php');
}
if(!class_exists("RedsysAPI")) {
	require_once('apiRedsys/apiRedsysFinal.php');
}


if (!defined('_CAN_LOAD_FILES_'))
	exit;

class Redsys extends PaymentModule
{
	private	$html = '';
	private $post_errors = array();

	public function __construct()
	{
		$this->name = 'redsys';
		$this->tab = 'payments_gateways';
		$this->version = '3.0.0';
		$this->author = 'REDSYS';

		$this->currencies = true;
		$this->currencies_mode = 'checkbox';
		// Array config con los datos de config.
		$config = Configuration::getMultiple(array('REDSYS_URLTPV', 'REDSYS_NOMBRE',
		'REDSYS_CODIGO','REDSYS_TIPOPAGO', 'REDSYS_TERMINAL', 'REDSYS_CLAVE256','REDSYS_TRANS','REDSYS_ERROR_PAGO','REDSYS_LOG','REDSYS_IDIOMAS_ESTADO'));
		// Establecer propiedades nediante los datos de config.
		$this->env = $config['REDSYS_URLTPV'];
		switch ($this->env)
		{
			case 1: //Real
				$this->urltpv = 'https://sis.redsys.es/sis/realizarPago/utf-8';
				break;
			case 2: //Pruebas t
				$this->urltpv = 'https://sis-t.redsys.es:25443/sis/realizarPago/utf-8';
				break;
			case 3: // Pruebas i
				$this->urltpv = 'https://sis-i.redsys.es:25443/sis/realizarPago/utf-8';
				break;
			case 4: //Pruebas d
				$this->urltpv = 'http://sis-d.redsys.es/sis/realizarPago/utf-8';
				break;
		}

		if (isset($config['REDSYS_NOMBRE']))
			$this->nombre = $config['REDSYS_NOMBRE'];
		if (isset($config['REDSYS_CODIGO']))
			$this->codigo = $config['REDSYS_CODIGO'];
		if (isset($config['REDSYS_TIPOPAGO']))
			$this->tipopago = $config['REDSYS_TIPOPAGO'];
		if (isset($config['REDSYS_TERMINAL']))
			$this->terminal = $config['REDSYS_TERMINAL'];
		if (isset($config['REDSYS_CLAVE256']))
			$this->clave256 = $config['REDSYS_CLAVE256'];
		if (isset($config['REDSYS_TRANS']))
			$this->trans = $config['REDSYS_TRANS'];
		if (isset($config['REDSYS_ERROR_PAGO']))
			$this->error_pago = $config['REDSYS_ERROR_PAGO'];
		if (isset($config['REDSYS_LOG']))
			$this->activar_log = $config['REDSYS_LOG'];
		if (isset($config['REDSYS_IDIOMAS_ESTADO']))
			$this->idiomas_estado = $config['REDSYS_IDIOMAS_ESTADO'];

		parent::__construct();

		$this->page = basename(__FILE__, '.php');
		$this->displayName = $this->l('Redsys');
		$this->description = $this->l('Aceptar pagos con tarjeta mediante Redsys');

		// Mostrar aviso si faltan datos de config.
		if (!isset($this->urltpv)
		|| !isset($this->nombre)
		|| !isset($this->codigo)
		|| !isset($this->tipopago)
		|| !isset($this->terminal)
		|| !isset($this->clave256)
		|| !isset($this->trans)
		|| !isset($this->error_pago)
		|| !isset($this->activar_log)
		|| !isset($this->idiomas_estado))

		$this->warning = $this->l('Faltan datos por configurar en el módulo de Redsys.');
	}

	public function install()
	{
		// Valores por defecto al instalar
		if (!parent::install()
			|| !Configuration::updateValue('REDSYS_URLTPV', '0')
			|| !Configuration::updateValue('REDSYS_NOMBRE', $this->l('Escriba el nombre de su tienda'))
			|| !Configuration::updateValue('REDSYS_TIPOPAGO', 'C')
			|| !Configuration::updateValue('REDSYS_TERMINAL', 1)
			|| !Configuration::updateValue('REDSYS_CLAVE256', $this->l('Escriba la clave de su tienda'))
			|| !Configuration::updateValue('REDSYS_TRANS', 0)
			|| !Configuration::updateValue('REDSYS_ERROR_PAGO', 'no')
			|| !Configuration::updateValue('REDSYS_LOG', 'no')
			|| !Configuration::updateValue('REDSYS_IDIOMAS_ESTADO', 'no')
			|| !$this->registerHook('paymentOptions')
			|| !$this->registerHook('paymentReturn'))
			return false;
		return true;
	}

	public function uninstall()
	{
		// Valores a quitar si desinstalamos
		if (!Configuration::deleteByName('REDSYS_URLTPV')
			|| !Configuration::deleteByName('REDSYS_NOMBRE')
			|| !Configuration::deleteByName('REDSYS_CODIGO')
			|| !Configuration::deleteByName('REDSYS_TIPOPAGO')
			|| !Configuration::deleteByName('REDSYS_TERMINAL')
			|| !Configuration::deleteByName('REDSYS_CLAVE256')
			|| !Configuration::deleteByName('REDSYS_TRANS')
			|| !Configuration::deleteByName('REDSYS_ERROR_PAGO')
			|| !Configuration::deleteByName('REDSYS_LOG')
			|| !Configuration::deleteByName('REDSYS_IDIOMAS_ESTADO')
			|| !parent::uninstall())
			return false;
		return true;
	}

	private function _postValidation()
	{
		// Si al enviar los datos del formulario de config. hay campos vacios, mostrar errores.
		if (Tools::isSubmit('btnSubmit'))
		{
			if (!Tools::getValue('nombre') || !checkNombreComecio(Tools::getValue('nombre')))
				$this->post_errors[] = $this->l('Se requiere el nombre del comercio o el valor indicado para el nombre del comercio no es correcto (Alfanumérico sin espacios).');
			if (!Tools::getValue('codigo') || !checkFuc(Tools::getValue('codigo')))
				$this->post_errors[] = $this->l('Se requiere el número de comercio (FUC) o el valor indicado para el número de comercio (FUC) no es correcto.');
			if (!Tools::getValue('clave256') || !checkFirma(Tools::getValue('clave256')))
				$this->post_errors[] = $this->l('Se requiere la Clave secreta de encriptación o el valor indicado para la Clave secreta de encriptación no es correcto.');
			if (!Tools::getValue('terminal') || !checkTerminal(Tools::getValue('terminal')))
				$this->post_errors[] = $this->l('Se requiere el Terminal del comercio o el valor indicado para el Terminal del comercio no es correcto.');
			if (Tools::getValue('trans')!=='0')
				$this->post_errors[] = $this->l('El valor indicado para el tipo de transacción no es correcto, debe ser 0.');
		}
	}

	private function _postProcess()
	{
		// Actualizar la config. en la BBDD
		if (Tools::isSubmit('btnSubmit'))
		{
			Configuration::updateValue('REDSYS_URLTPV', Tools::getValue('urltpv'));
			Configuration::updateValue('REDSYS_NOMBRE', Tools::getValue('nombre'));
			Configuration::updateValue('REDSYS_CODIGO', Tools::getValue('codigo'));
			Configuration::updateValue('REDSYS_TIPOPAGO', Tools::getValue('tipopago'));
			Configuration::updateValue('REDSYS_TERMINAL', Tools::getValue('terminal'));
			Configuration::updateValue('REDSYS_CLAVE256', Tools::getValue('clave256'));
			Configuration::updateValue('REDSYS_TRANS', Tools::getValue('trans'));
			Configuration::updateValue('REDSYS_ERROR_PAGO', Tools::getValue('error_pago'));
			Configuration::updateValue('REDSYS_LOG', Tools::getValue('activar_log'));
			Configuration::updateValue('REDSYS_IDIOMAS_ESTADO', Tools::getValue('idiomas_estado'));
		}
		$this->html .= $this->displayConfirmation($this->l('Configuración actualizada'));
	}

	
	private function _displayRedsys()
	{
		// lista de payments
		$this->html .= '<img src="../modules/redsys/img/redsys.png" style="float:left; margin-right:15px;"><b><br />'
		.$this->l('Este módulo le permite aceptar pagos con tarjeta.').'</b><br />'
		.$this->l('Si el cliente elije este modo de pago, podrá pagar de forma automática.').'<br /><br /><br />';
	}

	private function _displayForm()
	{
		$tipopago = Tools::getValue('tipopago', $this->tipopago);
		$tipopago_a = ($tipopago == ' ') ? ' selected="selected" ' : '';
		$tipopago_b = ($tipopago == 'C') ? ' selected="selected" ' : '';
		$tipopago_c = ($tipopago == 'T') ? ' selected="selected" ' : '';
		
		// Opciones para el comportamiento en error en el pago
		$error_pago = Tools::getValue('error_pago', $this->error_pago);
		$error_pago_si = ($error_pago == 'si') ? ' checked="checked" ' : '';
		$error_pago_no = ($error_pago == 'no') ? ' checked="checked" ' : '';
		
		// Opciones para el comportamiento del log
		$activar_log = Tools::getValue('activar_log', $this->activar_log);
		$activar_log_si = ($activar_log == 'si') ? ' checked="checked" ' : '';
		$activar_log_no = ($activar_log == 'no') ? ' checked="checked" ' : '';
		
		// Opciones para activar los idiomas
		$idiomas_estado = Tools::getValue('idiomas_estado', $this->idiomas_estado);
		$idiomas_estado_si = ($idiomas_estado == 'si') ? ' checked="checked" ' : '';
		$idiomas_estado_no = ($idiomas_estado == 'no') ? ' checked="checked" ' : '';

		// Opciones entorno
		if (!Tools::getValue('urltpv'))
			$entorno = Tools::getValue('env', $this->env);
				else
					$entorno = Tools::getValue('urltpv');
		$entorno_real = ($entorno == 1) ? ' selected="selected" ' : '';
		$entorno_t = ($entorno == 2) ? ' selected="selected" ' : '';
		$entorno_i = ($entorno == 3) ? ' selected="selected" ' : '';
		$entorno_d = ($entorno == 4) ? ' selected="selected" ' : '';

		// Mostar formulario
		$this->html .= '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
			<fieldset>
			<legend><img src="../img/admin/contact.gif" />'.$this->l('Configuración del TPV').'</legend>
				<table border="0" width="680" cellpadding="0" cellspacing="0" id="form">
					<tr><td colspan="2">'.$this->l('Por favor completa los datos de configuración del comercio').'.<br /><br /></td></tr>
					<tr><td width="255" style="height: 35px;">'.$this->l('Entorno de Redsys').'</td><td><select name="urltpv"><option value="1"'.$entorno_real.'>'.$this->l('Real').'</option><option value="2"'.$entorno_t.'>'.$this->l('Pruebas en sis-t').'</option><option value="3"'.$entorno_i.'>'.$this->l('Pruebas en sis-i').'</option><option value="4"'.$entorno_d.'>'.$this->l('Pruebas en sis-d').'</option></select></td></tr>
					<tr><td width="255" style="height: 35px;">'.$this->l('Nombre del comercio').'</td><td><input type="text" name="nombre" value="'.htmlentities(Tools::getValue('nombre', $this->nombre), ENT_COMPAT, 'UTF-8').'" style="width: 200px;" /></td></tr>
					<tr><td width="255" style="height: 35px;">'.$this->l('Número de comercio (FUC)').'</td><td><input type="text" name="codigo" value="'.Tools::getValue('codigo', $this->codigo).'" style="width: 200px;" /></td></tr>
					<tr><td width="255" style="height: 35px;">'.$this->l('Tipos de pago permitidos').'</td><td><select name="tipopago" style="width: 120px;"><option value=" "'.$tipopago_a.'>Todos</option><option value="C"'.$tipopago_b.'>Solo con Tarjeta</option><option value="T"'.$tipopago_c.'>Tarjeta y Iupay</option></select></td></tr>
					<tr><td width="255" style="height: 35px;">'.$this->l('Clave secreta de encriptación (SHA-256)').'</td><td><input type="text" name="clave256" value="'.Tools::getValue('clave256', $this->clave256).'" style="width: 200px;" /></td></tr>
					<tr><td width="255" style="height: 35px;">'.$this->l('Número de terminal').'</td><td><input type="text" name="terminal" value="'.Tools::getValue('terminal', $this->terminal).'" style="width: 80px;" /></td></tr>
					<tr><td width="255" style="height: 35px;">'.$this->l('Tipo de transacción').'</td><td><input type="text" name="trans" value="'.Tools::getValue('trans', $this->trans).'" style="width: 80px;" /></td></tr>
		</td></tr>
				</table>
			</fieldset>
			<br>
			<fieldset>
			<legend><img src="../img/admin/cog.gif" />'.$this->l('Personalización').'</legend>
			<table border="0" width="680" cellpadding="0" cellspacing="0" id="form">
		<tr>
		<td colspan="2">'.$this->l('Por favor completa los datos adicionales').'.<br /><br /></td>
		</tr>
		<tr>
		<td width="340" style="height: 35px;">'.$this->l('En caso de error, permitir repetir el pedido').'</td>
			<td>
			<input type="radio" name="error_pago" id="error_pago_1" value="si" '.$error_pago_si.'/>
			<img src="../img/admin/enabled.gif" alt="'.$this->l('Activado').'" title="'.$this->l('Activado').'" />
			<input type="radio" name="error_pago" id="error_pago_0" value="no" '.$error_pago_no.'/>
			<img src="../img/admin/disabled.gif" alt="'.$this->l('Desactivado').'" title="'.$this->l('Desactivado').'" />
			</td>
		</tr>
		<tr>
		<td width="340" style="height: 35px;">'.$this->l('Activar trazas de log').'</td>
			<td>
			<input type="radio" name="activar_log" id="activar_log_1" value="si" '.$activar_log_si.'/>
			<img src="../img/admin/enabled.gif" alt="'.$this->l('Activado').'" title="'.$this->l('Activado').'" />
			<input type="radio" name="activar_log" id="activar_log_0" value="no" '.$activar_log_no.'/>
			<img src="../img/admin/disabled.gif" alt="'.$this->l('Desactivado').'" title="'.$this->l('Desactivado').'" />
			</td>
		</tr>
		<tr>
		<td width="340" style="height: 35px;">'.$this->l('Activar los idiomas en el TPV').'</td>
			<td>
			<input type="radio" name="idiomas_estado" id="idiomas_estado_si" value="si" '.$idiomas_estado_si.'/>
			<img src="../img/admin/enabled.gif" alt="'.$this->l('Activado').'" title="'.$this->l('Activado').'" />
			<input type="radio" name="idiomas_estado" id="idiomas_estado_no" value="no" '.$idiomas_estado_no.'/>
			<img src="../img/admin/disabled.gif" alt="'.$this->l('Desactivado').'" title="'.$this->l('Desactivado').'" />
			</td>
		</tr>
		</table>
			</fieldset>
			<br>
		<input class="button" name="btnSubmit" value="'.$this->l('Guardar configuración').'" type="submit" />
		</form>';
	}

	public function getContent()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			$this->_postValidation();
			if (!count($this->post_errors))
				$this->_postProcess();
			else
				foreach ($this->post_errors as $err)
					$this->html .= $this->displayError($err);
		}
		else
			$this->html .= '<br />';
		$this->_displayRedsys();
		$this->_displayForm();
		return $this->html;
	}

	public function hookPaymentOptions($params)
	{
		if (!$this->active)
			return;
		if (!$this->checkCurrency($params['cart']))
			return;

		// Valor de compra
		$currency = new Currency($params['cart']->id_currency);
		$cantidad = number_format($params['cart']->getOrderTotal(true, Cart::BOTH), 2, '', '');
		$cantidad = (int)$cantidad;

		// El num. de pedido -> id_Carrito + el tiempo SS
		$orderId = $params['cart']->id;
		if(isset($_COOKIE["P".$orderId])) {
			$sec_pedido = $_COOKIE["P".$orderId];
		} else {
			$sec_pedido = -1;
		}
		$logActivo = "si";
		escribirLog(" - COOKIE: ".$_COOKIE["P".$orderId]."($orderId) - secPedido: $sec_pedido", $logActivo);
		if ($sec_pedido < 9) {
			setcookie("P".$orderId, ++$sec_pedido, time() + 86400); // 24 horas
		}
		$numpedido = str_pad($orderId.$sec_pedido, 12, "0", STR_PAD_LEFT); 
		try {
			// Desinstalación V.2.8.5
			escribirLog("DROP TABLE "._DB_PREFIX_."redsys", $logActivo);
			escribirLog("Tabla de la versión 2.8.5 eliminada.", $logActivo);
		} catch (Exception $e) {
			escribirLog("La tabla de la versión 2.8.5 no existe.", $logActivo);
		}		
		// Fuc
		$codigo = $this->codigo;
		// ISO Moneda
		$moneda = $currency->iso_code_num;
		// Tipo de Transacción
		$trans = $this->trans;

		//URL de Respuesta Online
		/*if (empty($_SERVER['HTTPS']))
		{
			$protocolo = 'http://';
			$urltienda = $protocolo.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'modules/redsys/validation.php';
		}
		else
		{
			$protocolo = 'https://';
			$urltienda = $protocolo.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'modules/redsys/validation.php';
		}*/
		$protocolo = 'http://';
		$urltienda = $protocolo.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'modules/redsys/validation.php';
		//Product Description
		$products = $params['cart']->getProducts();
		$productos = '';
		foreach ($products as $product)
			$productos .= $product['quantity'].' '.Tools::truncate($product['name'], 50).' ';			
		$productos = str_replace("%","&#37;",$productos);

			
		// Idiomas del TPV
		$idiomas_estado = $this->idiomas_estado;
		if ($idiomas_estado == 'si')
		{
			$idioma_web = Tools::substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);

			switch ($idioma_web)
			{
				case 'es':
				$idioma_tpv = '001';
				break;
				case 'en':
				$idioma_tpv = '002';
				break;
				case 'ca':
				$idioma_tpv = '003';
				break;
				case 'fr':
				$idioma_tpv = '004';
				break;
				case 'de':
				$idioma_tpv = '005';
				break;
				case 'nl':
				$idioma_tpv = '006';
				break;
				case 'it':
				$idioma_tpv = '007';
				break;
				case 'sv':
				$idioma_tpv = '008';
				break;
				case 'pt':
				$idioma_tpv = '009';
				break;
				case 'pl':
				$idioma_tpv = '011';
				break;
				case 'gl':
				$idioma_tpv = '012';
				break;
				case 'eu':
				$idioma_tpv = '013';
				break;
				default:
				$idioma_tpv = '002';
			}
		}
		else
			$idioma_tpv = '0';

		//Variable cliente
		$customer = new Customer($params['cart']->id_customer);
		$id_cart = (int)$params['cart']->id;		
		$miObj = new RedsysAPI;
		$miObj->setParameter("DS_MERCHANT_AMOUNT",$cantidad);
		$miObj->setParameter("DS_MERCHANT_ORDER",strval($numpedido));
		$miObj->setParameter("DS_MERCHANT_MERCHANTCODE",$codigo);
		$miObj->setParameter("DS_MERCHANT_CURRENCY",$moneda);
		$miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE",$trans);
		$miObj->setParameter("DS_MERCHANT_TERMINAL",$this->terminal);
		$miObj->setParameter("DS_MERCHANT_MERCHANTURL",$urltienda);
		//$miObj->setParameter("DS_MERCHANT_URLOK",$urltienda);
		$miObj->setParameter("DS_MERCHANT_URLOK",$protocolo.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$id_cart.'&id_module='.$this->id.'&id_order='.$this->currentOrder.'&key='.$customer->secure_key);
		$miObj->setParameter("DS_MERCHANT_URLKO",$protocolo.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'pedido');

		//ACTIVAR ESTE SI FRIENDLY_URL ES FALSE:$miObj->setParameter("DS_MERCHANT_URLKO",$protocolo.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'index.php?controller=order');
		$miObj->setParameter("Ds_Merchant_ConsumerLanguage",$idioma_tpv);
		$miObj->setParameter("Ds_Merchant_ProductDescription",$productos);
		//$miObj->setParameter("Ds_Merchant_Titular",$this->nombre);
		$miObj->setParameter("Ds_Merchant_Titular",$customer->firstname." ".$customer->lastname);
		$miObj->setParameter("Ds_Merchant_MerchantData",sha1($urltienda));
		$miObj->setParameter("Ds_Merchant_MerchantName",$this->nombre);
		//$miObj->setParameter("Ds_Merchant_MerchantName",$customer->firstname." ".$customer->lastname);
		$miObj->setParameter("Ds_Merchant_PayMethods",$this->tipopago);
		$miObj->setParameter("Ds_Merchant_Module","prestashop_redsys_".$this->version);
		//Datos de configuración
		$version = getVersionClave();
		//Clave del comercio que se extrae de la configuración del comercio
		// Se generan los parámetros de la petición
		$request = "";
		$paramsBase64 = $miObj->createMerchantParameters();
		$signatureMac = $miObj->createMerchantSignature($this->clave256);

		$this->smarty->assign(array(
			'urltpv' => $this->urltpv,
			'signatureVersion' => $version,
			'parameter' => $paramsBase64,
			'signature' => $signatureMac,
			'this_path' => $this->_path
		));
		
		$array_inputs = array(
			'Ds_SignatureVersion' => 'bbbb',
			'Ds_MerchantParameters' => $paramsBase64,
			'Ds_Signature' => $signatureMac,
		);
				$form_redsys = '<form id="payment-form" method="POST" action="'.$this->urltpv.'">
                              <input name="Ds_SignatureVersion" value="'.$version.'" type="hidden">
                              <input name="Ds_MerchantParameters" value="'.$paramsBase64.'" type="hidden">
                              <input name="Ds_Signature" value="'.$signatureMac.'" type="hidden">
            </form>';
		$newOption = new PaymentOption();
		$newOption->setCallToActionText($this->trans('Paga con Tarjeta', array(), 'Modules.Redsys.Shop'))

		->setLogo(_MODULE_DIR_.'redsys/img/redsys.png')
		->setAdditionalInformation($this->fetch('module:redsys/views/templates/hook/payment.tpl'))
		->setForm($form_redsys)
		->setAction($this->urltpv);
		$payment_options = [
            $newOption,
        ];
        return $payment_options;	
		//return $this->display(__FILE__, 'payment.tpl');
	}

	public function checkCurrency($cart)
	{
		$currency_order = new Currency($cart->id_currency);
		$currencies_module = $this->getCurrency($cart->id_currency);

		if (is_array($currencies_module))
			foreach ($currencies_module as $currency_module)
				if ($currency_order->id == $currency_module['id_currency'])
					return true;
		return false;
	}

	public function hookPaymentReturn($params)
	{
		if (!$this->active)
			return;

		$this->smarty->assign(array(
			'shop_name' => $this->context->shop->name,
			'total_to_pay' => Tools::displayPrice($params['order']->getOrdersTotalPaid(), new Currency($params['order']->id_currency), false),
			'status' => 'ok',
			'id_order' => $params['order']->reference,
			'this_path' => $this->_path
		));

		return $this->display(__FILE__, 'payment_return.tpl');
	}
}
?>