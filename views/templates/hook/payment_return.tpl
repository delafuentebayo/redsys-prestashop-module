{*
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
*}
<img src="{$this_path|escape:'htmlall'}img/redsys.png" /><br /><br />
{if $status == 'ok'}
	<p>
	{l s='Your order on %s is complete.' sprintf=[$shop_name] mod='redsys'}
		<br /><br />- {l s='Payment amount.' mod='redsys'} <span class="price"><strong>{$total_to_pay|escape:'htmlall'}</strong></span>
		<br /><br />- N# <span class="price"><strong>{$id_order|escape:'htmlall'}</strong></span>
		<br /><br />{l s='An email has been sent to you with this information.' mod='redsys'}
		<br /><br />{l s='For any questions or for further information, please contact our' mod='redsys'} <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department.' mod='redsys'}</a>.
	</p>
{else}
	<p class="warning">
		{l s='We have noticed that there is a problem with your order. If you think this is an error, you can contact our' mod='redsys'} 
		<a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department.' mod='redsys'}</a>.
	</p>
{/if}
