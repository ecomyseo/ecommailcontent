<?php
class EcomMailContentTools
{
    public static function discoverMailTemplates()
    {
        $templates = array();
        $roots = array(_PS_MAIL_DIR_);
        $theme = Context::getContext()->shop->theme;
        if ($theme && method_exists($theme, 'getName')) {
            $themeMail = _PS_ALL_THEMES_DIR_.$theme->getName().'/mails/';
            if (is_dir($themeMail)) $roots[] = $themeMail;
        }
        foreach (glob(_PS_MODULE_DIR_.'*/mails', GLOB_ONLYDIR) ?: array() as $dir) $roots[] = $dir.'/';
        foreach ($roots as $root) {
            if (!is_dir($root)) continue;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') continue;
                $name = $file->getBasename('.html');
                if ($name === '' || strpos($name, 'index') === 0) continue;
                if (!isset($templates[$name])) $templates[$name] = array('template'=>$name,'paths'=>array());
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
        if ($theme && method_exists($theme, 'getName')) $candidates[] = _PS_ALL_THEMES_DIR_.$theme->getName().'/mails/'.$iso.'/'.$template.'.html';
        $candidates[] = _PS_MAIL_DIR_.$iso.'/'.$template.'.html';
        foreach (glob(_PS_MODULE_DIR_.'*/mails/'.$iso.'/'.$template.'.html') ?: array() as $f) $candidates[] = $f;
        foreach ($candidates as $file) if (is_file($file)) return $file;
        return false;
    }

    public static function applyRuleToHtml($html, array $rule)
    {
        if ($html === '') {
            return $html;
        }

        $action = (string)$rule['action'];
        $position = (string)$rule['position'];
        $selector = trim((string)$rule['selector']);
        $content = trim((string)$rule['content_html']);
        if ($action === 'add' && $content === '' && !empty($rule['content_txt'])) {
            $content = nl2br(htmlspecialchars((string)$rule['content_txt'], ENT_QUOTES, 'UTF-8'));
        }

        if ($action === 'remove' && strpos($selector, 'TEXT::') === 0) {
            $needle = substr($selector, 6);
            return $needle === '' ? $html : str_replace($needle, '', $html);
        }

        if ($position === 'body_start' && $action === 'add') {
            return preg_replace('/<body([^>]*)>/i', '<body$1>'.$content, $html, 1);
        }
        if ($position === 'body_end' && $action === 'add') {
            return preg_replace('/<\/body>/i', $content.'</body>', $html, 1);
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        if (!$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            return $html;
        }
        $xpath = new DOMXPath($dom);

        // Zonas semánticas: se buscan en el HTML REAL de la plantilla/correo.
        // No dependen de clases inventadas como .addresses o .shipping.
        $preset = self::resolvePresetTarget($xpath, $position);
        if ($preset !== false && $action === 'add') {
            list($node, $where) = $preset;
            if ($node) {
                self::insertVisibleEmailBlock($dom, $node, $content, $where);
                $out = $dom->saveHTML();
                $out = preg_replace('/^<\?xml encoding="utf-8" \?>/i', '', $out);
                libxml_clear_errors();
                return $out;
            }
            // Si esa plantilla no contiene esa zona, NO insertamos en un sitio incorrecto.
            libxml_clear_errors();
            return $html;
        }

        if ($selector === '') {
            libxml_clear_errors();
            return $html;
        }

        $query = self::selectorToXpath($selector);
        if (!$query) {
            libxml_clear_errors();
            return $html;
        }
        $nodes = $xpath->query($query);
        if (!$nodes || !$nodes->length) {
            libxml_clear_errors();
            return $html;
        }

        $targets = array();
        foreach ($nodes as $n) {
            $targets[] = $n;
        }

        foreach ($targets as $node) {
            if ($action === 'remove') {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
                continue;
            }
            self::insertHtml($dom, $node, $content, $position);
        }

        $out = $dom->saveHTML();
        $out = preg_replace('/^<\?xml encoding="utf-8" \?>/i', '', $out);
        libxml_clear_errors();
        return $out;
    }

    private static function resolvePresetTarget(DOMXPath $xpath, $position)
    {
        /*
         * Estrategia única para TODOS los presets:
         * - "después de X" se ancla ANTES del primer bloque semántico siguiente.
         * - "antes de X" se ancla directamente al bloque X.
         *
         * Así no dependemos de adivinar qué TR/TABLE pertenece a la sección
         * anterior en los mails MJML/Modern de PrestaShop.
         */
        $anchors = array(
            // Después del logo = antes del saludo.
            'after_header' => array('before', array(
                'Hola ', 'Hello ', 'Bonjour ', 'Ciao ', 'Hallo '
            )),

            'before_order' => array('before', array(
                'Detalles del pedido', 'Order details', '{order_name}', 'PREVIEW_order_name'
            )),

            // Después de pedido = antes de Transporte.
            'after_order' => array('before', array(
                'Transportista:', 'Carrier:', 'Embalaje reciclado:', 'Recycled packaging:'
            )),

            'before_ship' => array('before', array(
                'Transportista:', 'Carrier:', 'Transporte', 'Shipping'
            )),

            // Después de transporte = antes de las direcciones.
            'after_ship' => array('before', array(
                'Dirección de entrega', 'Delivery address',
                '{delivery_block_html}', 'PREVIEW_delivery_block_html'
            )),

            'before_addr' => array('before', array(
                'Dirección de entrega', 'Delivery address',
                '{delivery_block_html}', 'PREVIEW_delivery_block_html'
            )),

            // Después de AMBAS direcciones = antes del bloque history.
            'after_addr' => array('before', array(
                '{history_url}', 'PREVIEW_history_url',
                'Follow your order and download your invoice',
                'Historial y detalles de mis pedidos'
            )),

            'before_guest' => array('before', array(
                '{guest_tracking_url}', 'PREVIEW_guest_tracking_url',
                'Si tiene una cuenta de invitado', 'If you have a guest account',
                'Seguimiento de pedido', 'Guest tracking'
            )),

            // Después del seguimiento de invitado = justo antes del footer.
            'after_guest' => array('footer_before', array(
                'Powered by PrestaShop', 'Powered by'
            )),

            // El pie se identifica por Powered by. Se inserta antes de la RAMA
            // completa del footer, no dentro de su texto.
            'before_footer' => array('footer_before', array(
                'Powered by PrestaShop', 'Powered by'
            )),
        );

        if (!isset($anchors[$position])) {
            return false;
        }

        list($where, $needles) = $anchors[$position];
        $candidate = self::findDeepestContaining($xpath, $needles);
        if (!$candidate) {
            return array(null, $where === 'footer_before' ? 'before' : $where);
        }

        if ($where === 'footer_before') {
            $block = self::semanticBranch($candidate, array(
                'Si tiene una cuenta de invitado',
                'If you have a guest account',
                'Seguimiento de pedido',
                'Guest tracking',
                '{guest_tracking_url}',
                'PREVIEW_guest_tracking_url'
            ));
            return array($block ?: self::nearestContentBlock($candidate), 'before');
        }

        return array(self::nearestContentBlock($candidate), $where);
    }

    /**
     * Sube desde $node hasta encontrar el primer ancestro cuyo padre ya contiene
     * texto de la sección anterior. Ese ancestro es la rama completa de la nueva
     * sección (por ejemplo, el footer completo).
     */
    private static function semanticBranch(DOMNode $node, array $previousMarkers)
    {
        $current = $node;
        while ($current && $current->parentNode) {
            $parent = $current->parentNode;
            $text = trim(preg_replace('/\s+/', ' ', $parent->textContent));
            foreach ($previousMarkers as $marker) {
                if ($marker !== '' && stripos($text, $marker) !== false) {
                    return $current instanceof DOMElement ? $current : null;
                }
            }
            if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'body') {
                break;
            }
            $current = $parent;
        }
        return null;
    }

    private static function findDeepestContaining(DOMXPath $xpath, array $needles)
    {
        foreach ($needles as $needle) {
            $literal = self::xpathLiteral($needle);
            $nodes = $xpath->query('//*[contains(normalize-space(string(.)), '.$literal.')]');
            if ($nodes && $nodes->length) {
                return $nodes->item($nodes->length - 1);
            }
        }
        return null;
    }

    private static function commonAncestor(DOMNode $a, DOMNode $b)
    {
        $ancestors = array();
        $n = $a;
        while ($n) {
            $ancestors[spl_object_hash($n)] = $n;
            $n = $n->parentNode;
        }
        $n = $b;
        while ($n) {
            $h = spl_object_hash($n);
            if (isset($ancestors[$h])) {
                return $ancestors[$h];
            }
            $n = $n->parentNode;
        }
        return null;
    }

    private static function ancestorByTag($node, $tag)
    {
        $tag = strtolower($tag);
        $n = $node;
        while ($n) {
            if ($n instanceof DOMElement && strtolower($n->tagName) === $tag) {
                return $n;
            }
            $n = $n->parentNode;
        }
        return null;
    }

    /**
     * Return the outer mail section containing $node.
     *
     * Modern PrestaShop emails are MJML compiled to nested tables. Looking for
     * the nearest TR is wrong because there are many nested TRs. We climb all
     * ancestors and keep the highest TR that is still inside BODY. That TR is
     * the section row of the generated email. If the theme uses DIV sections
     * instead of tables, use the highest DIV below BODY.
     */
    private static function nearestContentBlock(DOMNode $node)
    {
        $n = $node;
        $td = null;
        while ($n && !($n instanceof DOMElement && strtolower($n->tagName) === 'body')) {
            if ($n instanceof DOMElement) {
                $tag = strtolower($n->tagName);
                if ($tag === 'div') {
                    return $n;
                }
                if ($tag === 'td' && $td === null) {
                    $td = $n;
                }
            }
            $n = $n->parentNode;
        }
        return $td;
    }

    private static function topLevelMailSection(DOMNode $node)
    {
        $n = $node;
        $highestTr = null;
        $highestDiv = null;

        while ($n && !($n instanceof DOMElement && strtolower($n->tagName) === 'body')) {
            if ($n instanceof DOMElement) {
                $tag = strtolower($n->tagName);
                if ($tag === 'tr') {
                    $highestTr = $n;
                } elseif ($tag === 'div') {
                    $highestDiv = $n;
                }
            }
            $n = $n->parentNode;
        }

        /*
         * Do not return an outer layout TR if it contains most of the email.
         * Starting from the marker, select the highest TR whose next sibling
         * section exists. This gives us the actual section boundary.
         */
        if ($highestTr) {
            $candidate = $highestTr;
            // If this is the sole TR of an outer layout table, descend to the
            // deepest ancestor TR that has a TR sibling.
            $a = $node;
            $best = null;
            while ($a && $a !== $highestTr->parentNode) {
                if ($a instanceof DOMElement && strtolower($a->tagName) === 'tr') {
                    $prev = $a->previousSibling;
                    $next = $a->nextSibling;
                    while ($prev && !($prev instanceof DOMElement)) $prev = $prev->previousSibling;
                    while ($next && !($next instanceof DOMElement)) $next = $next->nextSibling;
                    if (($prev && strtolower($prev->tagName) === 'tr') ||
                        ($next && strtolower($next->tagName) === 'tr')) {
                        $best = $a;
                    }
                }
                $a = $a->parentNode;
            }
            if ($best) {
                return $best;
            }
            return $candidate;
        }

        return $highestDiv;
    }

    private static function nearestBlock(DOMNode $node, array $tags)
    {
        $current = $node;
        $fallback = null;
        $steps = 0;
        while ($current && $steps < 10) {
            if ($current instanceof DOMElement) {
                $tag = strtolower($current->tagName);
                if (in_array($tag, $tags, true)) {
                    // Para emails de tablas, TR es el límite más fiable: insertar
                    // después del TR evita meter contenido dentro de una celda.
                    if ($tag === 'tr') {
                        return $current;
                    }
                    if ($fallback === null) {
                        $fallback = $current;
                    }
                }
            }
            $current = $current->parentNode;
            $steps++;
        }
        return $fallback;
    }

    /**
     * Inserta contenido como bloque REAL y visible de email.
     * Se usa en after_addr porque el correo Modern tiene tablas/MJML anidados
     * y el HTML libre podía quedar fuera del flujo visual del preview.
     */
    private static function insertVisibleEmailBlock(DOMDocument $dom, DOMNode $node, $html, $where)
    {
        if (trim($html) === '' || !$node->parentNode) {
            return;
        }

        $wrapper = $dom->createElement('div');
        $wrapper->setAttribute('class', 'ecommailcontent-inserted');
        $wrapper->setAttribute(
            'style',
            'display:block !important; visibility:visible !important; opacity:1 !important; ' .
            'width:100% !important; box-sizing:border-box; margin:24px 0 !important; ' .
            'padding:0 !important; color:#000000 !important; font-size:16px !important; ' .
            'line-height:24px !important; text-align:left !important;'
        );

        $fragment = self::makeFragment($dom, $html);
        if (!$fragment) {
            $wrapper->appendChild($dom->createTextNode(strip_tags($html)));
        } else {
            $wrapper->appendChild($fragment);
        }

        if ($where === 'before') {
            $node->parentNode->insertBefore($wrapper, $node);
            return;
        }

        if ($where === 'after') {
            if ($node->nextSibling) {
                $node->parentNode->insertBefore($wrapper, $node->nextSibling);
            } else {
                $node->parentNode->appendChild($wrapper);
            }
            return;
        }

        $node->appendChild($wrapper);
    }

    private static function insertHtml(DOMDocument $dom, DOMNode $node, $html, $where)
    {
        if ($html === '') {
            return;
        }

        $fragment = self::makeFragment($dom, $html);
        if (!$fragment) {
            return;
        }

        if ($where === 'before' && $node->parentNode) {
            $node->parentNode->insertBefore($fragment, $node);
        } elseif ($where === 'after' && $node->parentNode) {
            if ($node->nextSibling) {
                $node->parentNode->insertBefore($fragment, $node->nextSibling);
            } else {
                $node->parentNode->appendChild($fragment);
            }
        } elseif ($where === 'prepend') {
            if ($node->firstChild) {
                $node->insertBefore($fragment, $node->firstChild);
            } else {
                $node->appendChild($fragment);
            }
        } elseif ($where === 'append') {
            $node->appendChild($fragment);
        }
    }

    private static function makeFragment(DOMDocument $dom, $html)
    {
        $tmp = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="utf-8" ?><div id="__emc_root__">'.$html.'</div>';
        if (!$tmp->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            return null;
        }

        $root = null;
        foreach ($tmp->getElementsByTagName('div') as $div) {
            if ($div->getAttribute('id') === '__emc_root__') {
                $root = $div;
                break;
            }
        }
        if (!$root) {
            return null;
        }

        $fragment = $dom->createDocumentFragment();
        $children = array();
        foreach ($root->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            $fragment->appendChild($dom->importNode($child, true));
        }
        return $fragment;
    }

    private static function xpathLiteral($value)
    {
        if (strpos($value, "'") === false) {
            return "'".$value."'";
        }
        if (strpos($value, '"') === false) {
            return '"'.$value.'"';
        }
        $parts = explode("'", $value);
        $out = array();
        foreach ($parts as $i => $part) {
            if ($i) {
                $out[] = '"\\\'"';
            }
            $out[] = "'".$part."'";
        }
        return 'concat('.implode(',', $out).')';
    }

    public static function applyRuleToText($txt,array $rule)
    {
        if ($rule['action']==='remove') return $txt;
        $content=trim((string)$rule['content_txt']); if($content==='') $content=trim(strip_tags((string)$rule['content_html'])); if($content==='') return $txt;
        if($rule['position']==='body_start') return $content."\n\n".$txt;
        return $txt."\n\n".$content;
    }

    private static function selectorToXpath($selector)
    {
        if(preg_match('/^#([A-Za-z0-9_-]+)$/',$selector,$m)) return '//*[@id="'.$m[1].'"]';
        if(preg_match('/^\.([A-Za-z0-9_-]+)$/',$selector,$m)) return '//*[contains(concat(" ", normalize-space(@class), " "), " '.$m[1].' ")]';
        if(preg_match('/^([A-Za-z][A-Za-z0-9]*)#([A-Za-z0-9_-]+)$/',$selector,$m)) return '//'.$m[1].'[@id="'.$m[2].'"]';
        if(preg_match('/^([A-Za-z][A-Za-z0-9]*)\.([A-Za-z0-9_-]+)$/',$selector,$m)) return '//'.$m[1].'[contains(concat(" ", normalize-space(@class), " "), " '.$m[2].' ")]';
        if(preg_match('/^[A-Za-z][A-Za-z0-9]*$/',$selector)) return '//'.$selector;
        return false;
    }
}
