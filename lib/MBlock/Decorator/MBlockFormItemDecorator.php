<?php
/**
 * @author mail[at]joachim-doerr[dot]com Joachim Doerr
 * @package redaxo5
 * @license MIT
 */

namespace MBlock\Decorator;


use DOMDocument;
use DOMElement;
use MBlock\DTO\MBlockItem;

class MBlockFormItemDecorator
{
    use MBlockDOMTrait;

    /**
     * @param MBlockItem $item
     * @author Joachim Doerr
     */
    static public function decorateFormItem(MBlockItem $item)
    {
        $dom = $item->getForm();
        if ($dom instanceof \DOMDocument) {
            // find inputs
            if ($matches = $dom->getElementsByTagName('input')) {
                /** @var DOMElement $match */
                foreach ($matches as $match) {
                    if (!$match->hasAttribute('data-mblock')) {
                        // label for and id change
                        self::replaceForId($dom, $match, $item);
                        // replace attribute id
                        self::replaceName($match, $item);
                        // change checked or value by type
                        switch ($match->getAttribute('type')) {
                            case 'checkbox':
                            case 'radio':
                                // replace checked
                                self::replaceChecked($match, $item);
                                break;
                            default:
                                // replace value by json key
                                self::replaceValue($match, $item);
                        }
                        $match->setAttribute('data-mblock', true);
                    }
                }
            }

            // find textareas
            if ($matches = $dom->getElementsByTagName('textarea')) {
                /** @var DOMElement $match */
                foreach ($matches as $match) {
                    if (!$match->hasAttribute('data-mblock')) {
                        // label for and id change
                        self::replaceForId($dom, $match, $item);
                        // replace attribute id
                        self::replaceName($match, $item);
                        // replace value by json key
                        self::replaceValue($match, $item);
                        $match->setAttribute('data-mblock', true);
                    }
                }
            }

            // find selects
            if ($matches = $dom->getElementsByTagName('select')) {
                /** @var DOMElement $match */
                foreach ($matches as $match) {
                    if (!$match->hasAttribute('data-mblock')) {
                        // continue by media elements
                        if (strpos($match->getAttribute('id'), 'REX_MEDIA') !== false
                            or strpos($match->getAttribute('id'), 'REX_LINK') !== false) {
                            continue;
                        }
                        // label for and id change
                        self::replaceForId($dom, $match, $item);
                        // replace attribute id
                        self::replaceName($match, $item);
                        // replace selected data
                        self::replaceSelectedData($match, $item);
                        // replace value by json key
                        if ($match->hasChildNodes()) {
                            /** @var DOMElement $child */
                            foreach ($match->childNodes as $child) {
                                switch ($child->nodeName) {
                                    case 'optgroup':
                                        foreach ($child->childNodes as $nodeChild)
                                            self::replaceOptionSelect($match, $nodeChild, $item);
                                        break;
                                    default:
                                        if (isset($child->tagName)) {
                                            self::replaceOptionSelect($match, $child, $item);
                                            break;
                                        }
                                }
                            }
                        }
                        $match->setAttribute('data-mblock', true);
                    }
                }
            }
            // return the manipulated html output
            // return $dom->saveHTML();
        }
    }

    /**
     * @param DOMElement $element
     * @param MBlockItem $item
     * @author Joachim Doerr
     */
    protected static function replaceName(DOMElement $element, MBlockItem $item)
    {
        if (!is_null($item->getSubId())) {
            // replace attribute id
            preg_match('/\]\[\d+\]\[\d+\]\[/', $element->getAttribute('name'), $matches);
            if ($matches) $element->setAttribute('name', str_replace($matches[0], '][' . $item->getSubId() . '][' . $item->getItemId() . '][', $element->getAttribute('name')));
        } else {
            // replace attribute id
            preg_match('/\]\[\d+\]\[/', $element->getAttribute('name'), $matches);
            if ($matches) $element->setAttribute('name', str_replace($matches[0], '][' . $item->getItemId() . '][', $element->getAttribute('name')));
        }
    }

    /**
     * @param DOMElement $element
     * @param MBlockItem $item
     * @author Joachim Doerr
     */
    protected static function replaceValue(DOMElement $element, MBlockItem $item)
    {
        // get value key by name
        $matches = self::getName($element);

        // found
        if ($matches) {
            // node name switch
            switch ($element->nodeName) {
                default:
                case 'input':
                    if ($matches && array_key_exists($matches[1], $item->getVal())) $element->setAttribute('value', $item->getVal()[$matches[1]]);
                    break;
                case 'textarea':
                    if ($matches && array_key_exists($matches[1], $item->getVal())) {
                        $result = $item->getVal();
                        $id = uniqid(md5(rand(1000,9999)),true);
                        // node value cannot contains &
                        // so set a unique id there we replace later with the right value
                        $element->nodeValue = $id;

                        // add the id to the result value
                        $result[$matches[1]] = array('id'=>$id, 'value'=>$result[$matches[1]]);

                        // reset result
                        $item->setResult($result);
                    }
                    break;
            }
        }
    }

    /**
     * @param DOMElement $element
     * @param MBlockItem $item
     * @author Joachim Doerr
     */
    protected static function replaceSelectedData(DOMElement $element, MBlockItem $item)
    {
        // get value key by name
        $matches = self::getName($element);

        // found
        if ($matches) {
            // node name switch
            switch ($element->nodeName) {
                default:
                case 'select':
                    if ($matches && array_key_exists($matches[1], $item->getVal())) $element->setAttribute('data-selected', $item->getVal()[$matches[1]]);
                    break;
            }
        }
    }

    /**
     * @param DOMElement $element
     * @param MBlockItem $item
     * @author Joachim Doerr
     */
    protected static function replaceChecked(DOMElement $element, MBlockItem $item)
    {
        // get value key by name
        $matches = self::getName($element);

        // found
        if ($matches) {
            // unset select
            if ($element->getAttribute('checked')) {
                $element->removeAttribute('checked');
            }
            // set select by value = result
            if ($matches && array_key_exists($matches[1], $item->getVal()) && $item->getVal()[$matches[1]] == $element->getAttribute('value')) {
                $element->setAttribute('checked', 'checked');
            }
        }
    }

    /**
     * @param DOMElement $select
     * @param DOMElement $option
     * @param MBlockItem $item
     * @author Joachim Doerr
     */
    protected static function replaceOptionSelect(DOMElement $select, DOMElement $option, MBlockItem $item)
    {
        // get value key by name
        $matches = self::getName($select);

        if ($matches) {
            // unset select
            if ($option->hasAttribute('selected')) {
                $option->removeAttribute('selected');
            }

            // set select by value = result
            if ($matches && array_key_exists($matches[1], $item->getVal())) {

                if (is_array($item->getVal()[$matches[1]])) {
                    $values = $item->getVal()[$matches[1]];
                } else {
                    $values = explode(',',$item->getVal()[$matches[1]]);
                }

                foreach ($values as $value) {
                    if ($value == $option->getAttribute('value')) {
                        $option->setAttribute('selected', 'selected');
                    }
                }
            }
        }
    }

    /**
     * @param DOMDocument $dom
     * @param DOMElement $element
     * @param MBlockItem $item
     * @return bool
     * @author Joachim Doerr
     */
    protected static function replaceForId(DOMDocument $dom, DOMElement $element, MBlockItem $item)
    {
        // get input id
        if (!$elementId = $element->getAttribute('id')) return true;

        // ignore system elements
        if (strpos($elementId, 'REX_MEDIA') !== false
            or strpos($elementId, 'REX_LINK') !== false) {
            return false;
        }

        $id = preg_replace('/(_\d+){2}/i', '_' . $item->getItemId(), str_replace('-','_', $elementId));
        $element->setAttribute('id', $id);
        // find label with for
        $matches = $dom->getElementsByTagName('label');

        if ($matches) {
            /** @var DOMElement $match */
            foreach ($matches as $match) {
                $for = $match->getAttribute('for');
                if ($for == $elementId) {
                    $match->setAttribute('for', $id);
                }
            }
        }
        return true;
    }

    /**
     * @param DOMElement $element
     * @return mixed
     * @author Joachim Doerr
     */
    public static function getName(DOMElement $element)
    {
        preg_match('/^.*?\[(\w+)\]$/i', str_replace('[]','',$element->getAttribute('name')), $matches);
        return $matches;
    }
}