<?php
if (!defined('_PS_VERSION_')) { exit; }

require_once __DIR__ . '/classes/EcomMailContentRule.php';
require_once __DIR__ . '/classes/EcomMailContentTools.php';

class Ecommailcontent extends Module
{
    private static $mailContext = array();

    public function __construct()
    {
        $this->name = 'ecommailcontent';
        $this->tab = 'administration';
        $this->version = '1.0.5';
        $this->author = 'ecom y seo - Gustavo Martos';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '8.1.0', 'max' => _PS_VERSION_);
        parent::__construct();

        $this->displayName = $this->l('Contenido de emails por hooks');
        $this->description = $this->l('Añade o elimina contenido de emails sin modificar plantillas TPL/Twig/HTML.');
    }

    public function install()
    {
        return parent::install()
            && $this->installDb()
            && $this->installTab()
            && $this->registerHook('actionEmailSendBefore')
            && $this->registerHook('actionMailAlterMessageBeforeSend');
    }

    public function uninstall()
    {
        return $this->uninstallTab()
            && $this->uninstallDb()
            && parent::uninstall();
    }

    private function installDb()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'ecom_mail_content_rule` (
            `id_ecom_mail_content_rule` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
            `template` VARCHAR(191) NOT NULL,
            `action` VARCHAR(16) NOT NULL DEFAULT "add",
            `position` VARCHAR(24) NOT NULL DEFAULT "body_end",
            `selector` VARCHAR(255) NOT NULL DEFAULT "",
            `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `position_order` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_ecom_mail_content_rule`),
            KEY `idx_template_shop` (`template`, `id_shop`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4;';
        $sqlLang = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'ecom_mail_content_rule_lang` (
            `id_ecom_mail_content_rule` INT UNSIGNED NOT NULL,
            `id_lang` INT UNSIGNED NOT NULL,
            `content_html` MEDIUMTEXT NULL,
            `content_txt` MEDIUMTEXT NULL,
            PRIMARY KEY (`id_ecom_mail_content_rule`,`id_lang`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4;';
        return Db::getInstance()->execute($sql) && Db::getInstance()->execute($sqlLang);
    }

    private function uninstallDb()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'ecom_mail_content_rule_lang`')
            && Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'ecom_mail_content_rule`');
    }

    private function installTab()
    {
        if (Tab::getIdFromClassName('AdminEcomMailContent')) {
            return true;
        }
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminEcomMailContent';
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentThemes');
        if (!$tab->id_parent) {
            $tab->id_parent = 0;
        }
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int)$lang['id_lang']] = 'Contenido de emails';
        }
        return (bool)$tab->add();
    }

    private function uninstallTab()
    {
        $id = (int) Tab::getIdFromClassName('AdminEcomMailContent');
        if (!$id) return true;
        $tab = new Tab($id);
        return (bool)$tab->delete();
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminEcomMailContent'));
    }

    public function hookActionEmailSendBefore(array $params)
    {
        self::$mailContext = array(
            'template' => isset($params['template']) ? (string)$params['template'] : '',
            'id_lang' => isset($params['idLang']) ? (int)$params['idLang'] : (int)$this->context->language->id,
            'id_shop' => isset($params['idShop']) ? (int)$params['idShop'] : (int)$this->context->shop->id,
        );
        return true;
    }

    public function hookActionMailAlterMessageBeforeSend(array $params)
    {
        if (empty(self::$mailContext['template']) || empty($params['message'])) {
            return;
        }

        $message = $params['message'];
        $template = self::$mailContext['template'];
        $idLang = !empty($params['id_lang']) ? (int)$params['id_lang'] : (int)self::$mailContext['id_lang'];
        $idShop = (int)self::$mailContext['id_shop'];

        $rules = EcomMailContentRule::getRules($template, $idLang, $idShop);
        if (!$rules) return;

        if (method_exists($message, 'getHtmlBody') && method_exists($message, 'html')) {
            $html = (string)$message->getHtmlBody();
            foreach ($rules as $rule) {
                $html = EcomMailContentTools::applyRuleToHtml($html, $rule);
            }
            $message->html($html);
        }

        if (method_exists($message, 'getTextBody') && method_exists($message, 'text')) {
            $txt = (string)$message->getTextBody();
            foreach ($rules as $rule) {
                $txt = EcomMailContentTools::applyRuleToText($txt, $rule);
            }
            $message->text($txt);
        }
    }

    public static function applyRuleToHtml($html, array $rule)
    {
        if ($html === '') return $html;
        $action = $rule['action'];
        $position = $rule['position'];
        $selector = trim((string)$rule['selector']);
        $content = (string)$rule['content_html'];

        // Zonas reconocibles: se resuelven sobre el HTML renderizado, sin modificar plantillas.
        $presets = array(
            'after_header' => array('after', array('.header', '#header', 'header')),
            'before_order' => array('before', array('.order-details', '.order-detail', '#order-details')),
            'after_order' => array('after', array('.order-details', '.order-detail', '#order-details')),
            'before_ship' => array('before', array('.shipping', '.shipping-block', '#shipping')),
            'after_ship' => array('after', array('.shipping', '.shipping-block', '#shipping')),
            'before_addr' => array('before', array('.addresses', '.address-block', '#addresses')),
            'after_addr' => array('after', array('.addresses', '.address-block', '#addresses')),
            'before_guest' => array('before', array('.guest-tracking', '#guest-tracking')),
            'before_footer' => array('before', array('.footer', '#footer', 'footer')),
        );
        if (isset($presets[$position]) && $action === 'add') {
            list($presetPosition, $presetSelectors) = $presets[$position];
            foreach ($presetSelectors as $presetSelector) {
                $testRule = $rule;
                $testRule['position'] = $presetPosition;
                $testRule['selector'] = $presetSelector;
                $changed = $this->applyRuleToHtml($html, $testRule);
                if ($changed !== $html) {
                    return $changed;
                }
            }
            // Fallback seguro si el tema no expone esa zona.
            return preg_replace('/<\/body>/i', $content.'</body>', $html, 1);
        }

        // Quitar texto, variable o fragmento literal del HTML ya renderizado.
        if ($action === 'remove' && strpos($selector, 'TEXT::') === 0) {
            $removeText = substr($selector, 6);
            return $removeText === '' ? $html : str_replace($removeText, '', $html);
        }

        if ($position === 'body_start' && $action === 'add') {
            return preg_replace('/<body([^>]*)>/i', '<body$1>'.$content, $html, 1);
        }
        if ($position === 'body_end' && $action === 'add') {
            return preg_replace('/<\/body>/i', $content.'</body>', $html, 1);
        }

        if ($selector === '') return $html;

        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$loaded) {
            libxml_clear_errors();
            return $html;
        }
        $xpath = new DOMXPath($dom);
        $query = self::selectorToXpath($selector);
        if (!$query) return $html;
        $nodes = $xpath->query($query);
        if (!$nodes || !$nodes->length) return $html;

        $targets = array();
        foreach ($nodes as $n) $targets[] = $n;

        foreach ($targets as $node) {
            if ($action === 'remove') {
                if ($node->parentNode) $node->parentNode->removeChild($node);
                continue;
            }

            $fragment = $dom->createDocumentFragment();
            if ($content !== '' && @ $fragment->appendXML($content) === false) {
                $fragment = $dom->createDocumentFragment();
                $fragment->appendChild($dom->createTextNode(strip_tags($content)));
            }

            if ($position === 'before' && $node->parentNode) {
                $node->parentNode->insertBefore($fragment, $node);
            } elseif ($position === 'after' && $node->parentNode) {
                if ($node->nextSibling) $node->parentNode->insertBefore($fragment, $node->nextSibling);
                else $node->parentNode->appendChild($fragment);
            } elseif ($position === 'prepend') {
                if ($node->firstChild) $node->insertBefore($fragment, $node->firstChild);
                else $node->appendChild($fragment);
            } elseif ($position === 'append') {
                $node->appendChild($fragment);
            }
        }

        $out = $dom->saveHTML();
        $out = preg_replace('/^<\?xml encoding="utf-8" \?>/i', '', $out);
        libxml_clear_errors();
        return $out;
    }

    public static function applyRuleToText($txt, array $rule)
    {
        if ($rule['action'] === 'remove') {
            $selector = trim((string)$rule['selector']);
            if (strpos($selector, 'TEXT::') === 0) {
                $removeText = substr($selector, 6);
                return $removeText === '' ? $txt : str_replace($removeText, '', $txt);
            }
            return $txt;
        }
        $content = trim((string)$rule['content_txt']);
        if ($content === '') {
            $content = trim(strip_tags((string)$rule['content_html']));
        }
        if ($content === '') return $txt;

        if ($rule['position'] === 'body_start') return $content."\n\n".$txt;
        if ($rule['position'] === 'body_end') return $txt."\n\n".$content;

        // Para posiciones por selector CSS se añade al final del TXT: el TXT no tiene DOM/selectores.
        return $txt."\n\n".$content;
    }

    private static function selectorToXpath($selector)
    {
        // Selector deliberadamente limitado y seguro: #id, .class, tag, tag#id, tag.class
        if (preg_match('/^#([A-Za-z0-9_-]+)$/', $selector, $m)) {
            return '//*[@id="'.$m[1].'"]';
        }
        if (preg_match('/^\.([A-Za-z0-9_-]+)$/', $selector, $m)) {
            return '//*[contains(concat(" ", normalize-space(@class), " "), " '.$m[1].' ")]';
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9]*)#([A-Za-z0-9_-]+)$/', $selector, $m)) {
            return '//'.$m[1].'[@id="'.$m[2].'"]';
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9]*)\.([A-Za-z0-9_-]+)$/', $selector, $m)) {
            return '//'.$m[1].'[contains(concat(" ", normalize-space(@class), " "), " '.$m[2].' ")]';
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $selector)) {
            return '//'.$selector;
        }
        return false;
    }

    public static function discoverMailTemplates()
    {
        $templates = array();
        $roots = array(_PS_MAIL_DIR_);
        $theme = Context::getContext()->shop->theme;
        if ($theme && method_exists($theme, 'getName')) {
            $themeMail = _PS_ALL_THEMES_DIR_.$theme->getName().'/mails/';
            if (is_dir($themeMail)) $roots[] = $themeMail;
        }
        foreach (glob(_PS_MODULE_DIR_.'*/mails', GLOB_ONLYDIR) ?: array() as $dir) {
            $roots[] = $dir.'/';
        }

        foreach ($roots as $root) {
            if (!is_dir($root)) continue;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') continue;
                $name = $file->getBasename('.html');
                if ($name === '' || strpos($name, 'index') === 0) continue;
                if (!isset($templates[$name])) {
                    $templates[$name] = array('template' => $name, 'paths' => array());
                }
                $templates[$name]['paths'][] = $file->getPathname();
            }
        }
        ksort($templates);
        return array_values($templates);
    }

    public static function findTemplateFile($template, $iso)
    {
        $candidates = array();
        $theme = Context::getContext()->shop->theme;
        if ($theme && method_exists($theme, 'getName')) {
            $candidates[] = _PS_ALL_THEMES_DIR_.$theme->getName().'/mails/'.$iso.'/'.$template.'.html';
        }
        $candidates[] = _PS_MAIL_DIR_.$iso.'/'.$template.'.html';
        foreach (glob(_PS_MODULE_DIR_.'*/mails/'.$iso.'/'.$template.'.html') ?: array() as $f) {
            $candidates[] = $f;
        }
        foreach ($candidates as $file) {
            if (is_file($file)) return $file;
        }
        return false;
    }
}
