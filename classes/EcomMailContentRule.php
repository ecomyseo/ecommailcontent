<?php
class EcomMailContentRule extends ObjectModel
{
    public $id_ecom_mail_content_rule;
    public $id_shop;
    public $template;
    public $action;
    public $position;
    public $selector;
    public $active;
    public $position_order;
    public $content_html;
    public $content_txt;

    public static $definition = array(
        'table' => 'ecom_mail_content_rule',
        'primary' => 'id_ecom_mail_content_rule',
        'multilang' => true,
        'fields' => array(
            'id_shop' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'template' => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'required' => true, 'size' => 191),
            'action' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 16),
            'position' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 24),
            'selector' => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 255),
            'active' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'position_order' => array('type' => self::TYPE_INT, 'validate' => 'isInt'),
            'content_html' => array('type' => self::TYPE_HTML, 'lang' => true),
            'content_txt' => array('type' => self::TYPE_STRING, 'lang' => true),
        ),
    );

    public static function getRules($template, $idLang, $idShop)
    {
        $sql = 'SELECT r.*, rl.content_html, rl.content_txt
                FROM `'._DB_PREFIX_.'ecom_mail_content_rule` r
                LEFT JOIN `'._DB_PREFIX_.'ecom_mail_content_rule_lang` rl
                  ON rl.id_ecom_mail_content_rule=r.id_ecom_mail_content_rule
                 AND rl.id_lang='.(int)$idLang.'
                WHERE r.active=1
                  AND (
                        r.template="*"
                        OR FIND_IN_SET("'.pSQL($template).'", REPLACE(r.template, " ", "")) > 0
                  )
                  AND (r.id_shop=0 OR r.id_shop='.(int)$idShop.')
                ORDER BY r.position_order ASC, r.id_ecom_mail_content_rule ASC';
        return Db::getInstance()->executeS($sql);
    }
}
