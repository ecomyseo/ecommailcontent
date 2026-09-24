<?php
require_once dirname(__FILE__).'/../../classes/EcomMailContentRule.php';
require_once dirname(__FILE__).'/../../classes/EcomMailContentTools.php';

class AdminEcomMailContentController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'ecom_mail_content_rule';
        $this->className = 'EcomMailContentRule';
        $this->identifier = 'id_ecom_mail_content_rule';
        $this->lang = true;
        $this->addRowAction('edit');
        $this->addRowAction('delete');

        $templates = EcomMailContentTools::discoverMailTemplates();
        $templateOptions = array(
            array('id' => '*', 'name' => 'TODOS LOS EMAILS'),
        );
        foreach ($templates as $t) {
            $templateOptions[] = array('id' => $t['template'], 'name' => $t['template']);
        }

        $this->fields_list = array(
            'id_ecom_mail_content_rule' => array('title' => 'ID', 'class' => 'fixed-width-xs'),
            'template' => array('title' => 'Email / plantilla'),
            'action' => array('title' => 'Acción'),
            'position' => array('title' => 'Punto'),
            'selector' => array('title' => 'Selector'),
            'position_order' => array('title' => 'Orden', 'class' => 'fixed-width-sm'),
            'active' => array('title' => 'Activo', 'type' => 'bool', 'active' => 'status', 'class' => 'fixed-width-sm'),
        );

        $this->fields_form = array(
            'legend' => array('title' => 'Regla de contenido de email', 'icon' => 'icon-envelope'),
            'input' => array(
                array(
                    'type' => 'select', 'label' => 'Email / plantilla', 'name' => 'template[]', 'required' => true,
                    'multiple' => true,
                    'class' => 'chosen',
                    'options' => array('query' => $templateOptions, 'id' => 'id', 'name' => 'name'),
                    'desc' => 'Selecciona TODOS LOS EMAILS o varias plantillas concretas.'
                ),
                array(
                    'type' => 'select', 'label' => 'Acción', 'name' => 'action', 'required' => true,
                    'options' => array('query' => array(
                        array('id'=>'add','name'=>'Añadir contenido'),
                        array('id'=>'remove','name'=>'Quitar elemento'),
                    ), 'id'=>'id','name'=>'name')
                ),
                array(
                    'type' => 'select', 'label' => 'Dónde', 'name' => 'position', 'required' => true,
                    'options' => array('query' => array(
                        array('id'=>'after_header','name'=>'Después de la cabecera / logo'),
                        array('id'=>'before_order','name'=>'Antes de detalles del pedido'),
                        array('id'=>'after_order','name'=>'Después de detalles del pedido'),
                        array('id'=>'before_ship','name'=>'Antes del bloque de transporte'),
                        array('id'=>'after_ship','name'=>'Después del bloque de transporte'),
                        array('id'=>'before_addr','name'=>'Antes de las direcciones'),
                        array('id'=>'after_addr','name'=>'Después de las direcciones'),
                        array('id'=>'before_guest','name'=>'Antes de seguimiento de invitado'),
                        array('id'=>'after_guest','name'=>'Después de seguimiento de invitado'),
                        array('id'=>'before_footer','name'=>'Antes del pie del email'),
                        array('id'=>'before','name'=>'Antes del selector CSS personalizado'),
                        array('id'=>'after','name'=>'Después del selector CSS personalizado'),
                        array('id'=>'prepend','name'=>'Dentro del selector CSS, al inicio'),
                        array('id'=>'append','name'=>'Dentro del selector CSS, al final'),
                        array('id'=>'body_start','name'=>'Inicio del email'),
                        array('id'=>'body_end','name'=>'Final del email'),
                    ), 'id'=>'id','name'=>'name'),
                    'desc' => 'Para quitar, se elimina el elemento que coincida con el selector.'
                ),
                array(
                    'type'=>'text','label'=>'Qué elemento quitar / Selector CSS','name'=>'selector',
                    'desc'=>'Solo es necesario para las opciones de selector CSS personalizado o para QUITAR un elemento concreto. Las zonas con nombre se detectan automáticamente.'
                ),
                array(
                    'type'=>'text','label'=>'Texto o variable a quitar','name'=>'remove_text',
                    'desc'=>'Opcional. Para QUITAR texto sin depender de un selector. Ej.: {shop_name}, {order_name}, "Gracias por su compra".'
                ),
                array(
                    'type'=>'textarea','label'=>'Contenido HTML','name'=>'content_html','lang'=>true,'autoload_rte'=>true,'rows'=>8,
                    'desc'=>'Multiidioma. Solo se usa al añadir.'
                ),
                array(
                    'type'=>'textarea','label'=>'Contenido texto plano','name'=>'content_txt','lang'=>true,'rows'=>5,
                    'desc'=>'Multiidioma. Si Contenido HTML está vacío, este texto también se usará automáticamente en el email HTML.'
                ),
                array('type'=>'text','label'=>'Orden','name'=>'position_order','class'=>'fixed-width-sm'),
                array('type'=>'switch','label'=>'Activo','name'=>'active','is_bool'=>true,'values'=>array(
                    array('id'=>'active_on','value'=>1,'label'=>'Sí'),
                    array('id'=>'active_off','value'=>0,'label'=>'No'),
                )),
            ),
            'submit' => array('title' => 'Guardar')
        );

        parent::__construct();
    }


    public function postProcess()
    {
        if (Tools::isSubmit('submitAdd'.$this->table)) {
            $selected = Tools::getValue('template', array());
            if (!is_array($selected)) {
                $selected = array($selected);
            }
            $selected = array_values(array_unique(array_filter(array_map('strval', $selected))));
            if (in_array('*', $selected, true)) {
                $selected = array('*');
            }
            $_POST['template'] = implode(',', $selected);

            $removeText = trim((string)Tools::getValue('remove_text', ''));
            $selector = trim((string)Tools::getValue('selector', ''));
            if ($removeText !== '' && $selector === '') {
                $_POST['selector'] = 'TEXT::'.$removeText;
            }
        }
        return parent::postProcess();
    }

    public function initContent()
    {
        if (Tools::getValue('ajax') && Tools::getValue('action') === 'previewMail') {
            $this->ajaxProcessPreviewMail();
            return;
        }
        parent::initContent();

        if (!$this->display) {
            $templates = EcomMailContentTools::discoverMailTemplates();
            $langs = Language::getLanguages(false);
            $this->context->smarty->assign(array(
                'emc_templates' => $templates,
                'emc_languages' => $langs,
                'emc_preview_url' => self::$currentIndex.'&token='.$this->token.'&ajax=1&action=previewMail',
            ));
            $this->content .= $this->context->smarty->fetch(
                _PS_MODULE_DIR_.'ecommailcontent/views/templates/admin/preview.tpl'
            );
            $this->context->smarty->assign('content', $this->content);
        }
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addJS(_MODULE_DIR_.'ecommailcontent/views/js/admin-where.js');
    }

    public function renderForm()
    {
        $id = (int)Tools::getValue($this->identifier);
        if (!$id) {
            $this->fields_value['active'] = 1;
            $this->fields_value['position_order'] = 0;
            $this->fields_value['action'] = 'add';
            $this->fields_value['position'] = 'body_end';
            $this->fields_value['template[]'] = array();
            $this->fields_value['remove_text'] = '';
        } else {
            $obj = new EcomMailContentRule($id);
            $selected = array_filter(array_map('trim', explode(',', (string)$obj->template)));
            $this->fields_value['template[]'] = $selected;
            if (strpos((string)$obj->selector, 'TEXT::') === 0) {
                $this->fields_value['remove_text'] = substr((string)$obj->selector, 6);
                $this->fields_value['selector'] = '';
            }
        }
        return parent::renderForm();
    }

    public function ajaxProcessPreviewMail()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        $template = (string)Tools::getValue('template');
        $idLang = (int)Tools::getValue('id_lang');
        $lang = new Language($idLang);
        if (!Validate::isLoadedObject($lang) || !preg_match('/^[A-Za-z0-9_-]+$/', $template)) {
            die(json_encode(array('ok'=>false,'error'=>'Parámetros no válidos')));
        }

        $file = EcomMailContentTools::findTemplateFile($template, $lang->iso_code);
        if (!$file) {
            die(json_encode(array('ok'=>false,'error'=>'No se encontró el HTML para ese idioma.')));
        }

        $html = Tools::file_get_contents($file);

        /*
         * PREVIEW SEGURO:
         * Nunca introducir <span> dentro de {variables}, porque muchas variables viven
         * dentro de href/src/style y eso rompe el HTML del email.
         * Para el previo usamos valores ficticios válidos según el tipo de variable.
         */
        $shopUrl = $this->context->link->getPageLink('index', true, $idLang, null, false, (int)$this->context->shop->id);
        $shopName = (string)Configuration::get('PS_SHOP_NAME');

        $logo = Configuration::get('PS_LOGO_MAIL');
        if (!$logo) {
            $logo = Configuration::get('PS_LOGO');
        }
        $logoUrl = $logo ? rtrim($this->context->shop->getBaseURL(true), '/').'/img/'.$logo : '';

        $sampleVars = array(
            '{shop_url}' => $shopUrl,
            '{shop_name}' => $shopName,
            '{shop_logo}' => $logoUrl,
            '{firstname}' => 'Nombre',
            '{lastname}' => 'Apellidos',
            '{order_name}' => 'ABCDEF123',
            '{date}' => date('d/m/Y'),
            '{payment}' => 'Método de pago',
            '{carrier}' => 'Transportista',
            '{total_products}' => '120,00 €',
            '{total_discounts}' => '0,00 €',
            '{total_shipping}' => '9,95 €',
            '{total_tax_paid}' => '22,55 €',
            '{total_paid}' => '129,95 €',
            '{history_url}' => $this->context->link->getPageLink('history', true, $idLang),
            '{guest_tracking_url}' => $this->context->link->getPageLink('guest-tracking', true, $idLang),
            '{my_account_url}' => $this->context->link->getPageLink('my-account', true, $idLang),
            '{order_slip_url}' => $this->context->link->getPageLink('order-slip', true, $idLang),
            '{recycled_packaging_label}' => 'No',
            '{delivery_block_html}' => '<strong>Nombre Apellidos</strong><br>Dirección de entrega<br>28001 Madrid<br>España',
            '{invoice_block_html}' => '<strong>Nombre Apellidos</strong><br>Dirección de facturación<br>28001 Madrid<br>España',
            '{products}' => '<tr><td style="padding:8px">REF-001</td><td style="padding:8px">Producto de ejemplo</td><td style="padding:8px">120,00 €</td><td style="padding:8px">1</td><td style="padding:8px">120,00 €</td></tr>',
            '{discounts}' => '',
        );

        // Sustitución literal: NO se inserta ninguna etiqueta de resaltado.
        $html = strtr($html, $sampleVars);

        // Algunas plantillas pueden traer llaves codificadas como entidades.
        foreach ($sampleVars as $key => $value) {
            $encodedKey = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            if ($encodedKey !== $key) {
                $html = str_replace($encodedKey, $value, $html);
            }
        }

        // Variables que no conocemos: texto neutro, sin HTML, para no romper href/src/style.
        $html = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($m) {
            return 'PREVIEW_'.$m[1];
        }, $html);

        // Recursos absolutos de los mail themes (/mails/themes/...).
        $baseRoot = rtrim($this->context->shop->getBaseURL(true), '/').'/';
        $html = preg_replace_callback(
            '/(["\'])\/?mails\/themes\//i',
            function ($m) use ($baseRoot) {
                return $m[1].$baseRoot.'mails/themes/';
            },
            $html
        );

        // Base para cualquier otro recurso relativo.
        $baseTag = '<base href="'.htmlspecialchars($baseRoot, ENT_QUOTES, 'UTF-8').'">';
        if (stripos($html, '<head') !== false) {
            $html = preg_replace('/<head([^>]*)>/i', '<head$1>'.$baseTag, $html, 1);
        } else {
            $html = $baseTag.$html;
        }

        $rules = EcomMailContentRule::getRules($template, $idLang, (int)$this->context->shop->id);
        foreach ($rules as $rule) {
            $html = EcomMailContentTools::applyRuleToHtml($html, $rule);
        }

        die(json_encode(array(
            'ok'=>true,
            'html'=>$html,
            'file'=>$file,
            'preview_version'=>'1.0.8',
            'unresolved_critical'=>(bool)preg_match('/\{(?:shop_url|shop_logo|firstname|lastname|shop_name)\}/', $html)
        )));
    }
}
