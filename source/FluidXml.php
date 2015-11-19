<?php

// Copyright (c) 2015, Daniele Orlando <fluidxml(at)danieleorlando.com>
// All rights reserved.
//
// Redistribution and use in source and binary forms, with or without modification,
// are permitted provided that the following conditions are met:
//
// 1. Redistributions of source code must retain the above copyright notice, this
//    list of conditions and the following disclaimer.
//
// 2. Redistributions in binary form must reproduce the above copyright notice,
//    this list of conditions and the following disclaimer in the documentation
//    and/or other materials provided with the distribution.
//
// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
// ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
// WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
// IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
// INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
// BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
// DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
// LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
// OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
// OF THE POSSIBILITY OF SUCH DAMAGE.


/**
 * FluidXML is a PHP library, under the Servo PHP framework umbrella,
 * specifically designed to manipulate XML documents with a concise
 * and fluent interface.
 *
 * It leverages XPath and the fluent programming technique to be fun
 * and effective.
 *
 * @author Daniele Orlando <fluidxml(at)danieleorlando.com>
 *
 * @license BSD-2-Clause
 * @license https://opensource.org/licenses/BSD-2-Clause
 */


/**
 * Constructs a new FluidXml instance.
 *
 * ```php
 * $xml = fluidxml();
 * // is the same of
 * $xml = new FluidXml();
 *
 * $xml = fluidxml([
 *
 *   'version'    => '1.0',
 *
 *   'encoding'   => 'UTF-8',
 *
 *   'stylesheet' => null,
 *
 *   'root'       => 'doc' ]);
 * ```
 *
 * @param array $arguments Options that influence the construction of the XML document.
 *
 * @return FluidXml A new FluidXml instance.
 */
function fluidxml(...$arguments)
{
        return new FluidXml(...$arguments);
}

////////////////////////////////////////////////////////////////////////////////

interface FluidInterface
{
        /**
         * Executes an XPath query.
         *
         * ```php
         * $xml = fluidxml();

         * $xml->query("/doc/book[@id='123']");
         *
         * // Relative queries are valid.
         * $xml->query("/doc")->query("book[@id='123']");
         * ```
         *
         * @param string $xpath The XPath to execute.
         *
         * @return FluidContext The context associated to the DOMNodeList.
         */
        public function query($xpath);

        /**
         * Append a new node as child of the current context.
         *
         * ```php
         * $xml = fluidxml();

         * $xml->appendChild('title', 'The Theory Of Everything');
         * $xml->appendChild([ 'author' => 'S. Hawking' ]);
         *
         * $xml->appendChild('chapters', true)->appendChild('chapter', ['id'=> 1]);
         *
         * ```
         *
         * @param string|array $child The child/children to add.
         * @param string $value The child text content.
         * @param bool $switchContext Whether to return the current context
         *                            or the context of the created node.
         *
         * @return FluidContext The context associated to the DOMNodeList.
         */
        public function appendChild($child, ...$optionals);
        public function prependSibling($sibling, ...$optionals);
        public function appendSibling($sibling, ...$optionals);
        public function appendXml($xml);
        public function appendText($text);
        public function appendCdata($cdata);
        public function setText($text);
        public function setAttribute(...$arguments);
        public function remove($xpath);
        // Aliases:
        public function add($child, ...$optionals);
        public function prepend($sibling, ...$optionals);
        public function insertSiblingBefore($sibling, ...$optionals);
        public function append($sibling, ...$optionals);
        public function insertSiblingAfter($sibling, ...$optionals);
        public function attr(...$arguments);
        public function text($text);
}

////////////////////////////////////////////////////////////////////////////////

// class FluidNamespace
// {
//         // TODO
// }

////////////////////////////////////////////////////////////////////////////////

class FluidContext implements FluidInterface, \ArrayAccess, \Iterator
{
        private $dom;
        private $namespace;
        private $nodes = [];
        private $seek = 0;

        public function __construct(\DOMDocument $dom, $context, $namespace = null)
        {
                $this->dom       = $dom;
                $this->namespace = $namespace;

                if (! \is_array($context)) {
                        $context = [ $context ];
                }

                foreach ($context as $n) {
                        if ($n instanceof \DOMNodeList) {
                                for ($i = 0, $l = $n->length; $i < $l; ++$i) {
                                        $this->nodes[] = $n->item($i);
                                }
                        } else if ($n instanceof \DOMNode) {
                                $this->nodes[] = $n;
                        } else if ($n instanceof FluidContext) {
                                $this->nodes = \array_merge($this->nodes, $n->asArray());
                        } else {
                                throw new \Exception('Node type not recognized.');
                        }
                }
        }

        public function asArray()
        {
                return $this->nodes;
        }

        // \ArrayAccess interface.
        public function offsetSet($offset, $value)
        {
                // if (\is_null($offset)) {
                //         $this->nodes[] = $value;
                // } else {
                //         $this->nodes[$offset] = $value;
                // }
                throw new \Exception('Setting a context element is not allowed.');
        }

        // \ArrayAccess interface.
        public function offsetExists($offset)
        {
                return isset($this->nodes[$offset]);
        }

        // \ArrayAccess interface.
        public function offsetUnset($offset)
        {
                // unset($this->nodes[$offset]);
                \array_splice($this->nodes, $offset, 1);
        }

        // \ArrayAccess interface.
        public function offsetGet($offset)
        {
                if (isset($this->nodes[$offset])) {
                        return $this->nodes[$offset];
                }

                return null;
        }

        // \Iterator interface.
        function rewind()
        {
                $this->seek = 0;
        }

        // \Iterator interface.
        function current()
        {
                return $this->nodes[$this->seek];
        }

        // \Iterator interface.
        function key()
        {
                return $this->seek;
        }

        // \Iterator interface.
        function next()
        {
                ++$this->seek;
        }

        // \Iterator interface.
        function valid()
        {
                return isset($this->nodes[$this->seek]);
        }

        public function length()
        {
                return \count($this->nodes);
        }

        public function query($xpath)
        {
                if (! \is_array($xpath)) {
                        $xpath = [ $xpath ];
                }

                $results = [];

                $domxp = new \DOMXPath($this->dom);

                foreach ($this->nodes as $n) {
                        foreach ($xpath as $x) {
                                // Returns a DOMNodeList.
                                $res = $domxp->query($x, $n);

                                // TODO: benchmark of for vs foreach.
                                // for ($i = 0, $l = $res->length; $i < $l; ++$i) {
                                //         $results[] = $res->item($i);
                                // }
                                foreach ($res as $r) {
                                        $results[] = $r;
                                }
                        }
                }

                // Performing over multiple sibling nodes a query that ascends
                // the xpath, relative (../..) or absolute (//), returns identical
                // matching results that must be collapsed in an unique result
                // otherwise a subsequent operation is performed multiple times.
                $unique_results = [];
                foreach ($results as $r) {
                        $found = false;

                        foreach ($unique_results as $u) {
                                if ($r === $u) {
                                        $found = true;
                                }
                        }

                        if (! $found) {
                                $unique_results[] = $r;
                        }
                }

                return $this->newContext($unique_results);
        }

        // appendChild($child, $value?, $attributes? = [], $switchContext? = false)
        public function appendChild($child, ...$optionals)
        {
                $fn = function($node, $newElement) {
                        return $node->appendChild($newElement);
                };

                return $this->insertNode($fn, $child, ...$optionals);
        }

        // Alias of appendChild.
        public function add($child, ...$optionals)
        {
                return $this->appendChild($child, ...$optionals);
        }

        public function prependSibling($sibling, ...$optionals)
        {
                $fn = function($node, $newElement) {
                        return $node->parentNode->insertBefore($newElement, $node);
                };

                return $this->insertNode($fn, $sibling, ...$optionals);
        }

        // Alias of prependSibling.
        public function prepend($sibling, ...$optionals)
        {
                return $this->prependSibling($sibling, ...$optionals);
        }

        // Alias of prependSibling.
        public function insertSiblingBefore($sibling, ...$optionals)
        {
                return $this->prependSibling($sibling, ...$optionals);
        }

        public function appendSibling($sibling, ...$optionals)
        {
                $fn = function($node, $newElement) {
                        /* if nextSibling is null, it is simply appended as last sibling. */
                        return $node->parentNode->insertBefore($newElement, $node->nextSibling);
                };

                return $this->insertNode($fn, $sibling, ...$optionals);
        }

        // Alias of appendSibling.
        public function append($sibling, ...$optionals)
        {
                return $this->appendSibling($sibling, ...$optionals);
        }

        // Alias of appendSibling.
        public function insertSiblingAfter($sibling, ...$optionals)
        {
                return $this->appendSibling($sibling, ...$optionals);
        }

        public function appendXml($xml)
        {
                $newDom = new \DOMDocument();
                // A way to import strings with multiple root nodes.
                $newDom->loadXML("<root>$xml</root>");

                $newDomXp = new \DOMXPath($newDom);
                $newNodes = $newDomXp->query('/root/*');

                foreach ($this->nodes as $n) {
                        foreach ($newNodes as $e) {
                                $n->appendChild($this->dom->importNode($e, true));
                        }
                }

                return $this;
        }

        // Arguments can be in the form of:
        // setAttribute($name, $value)
        // setAttribute(['name' => 'value', ...])
        public function setAttribute(...$arguments)
        {
                // Default case is:
                // [ 'name' => 'value', ... ]
                $attrs = $arguments[0];

                // If the first argument is not an array,
                // the user has passed two arguments:
                // 1. is the attribute name
                // 2. is the attribute value
                if (! \is_array($arguments[0])) {
                        $attrs = [$arguments[0] => $arguments[1]];
                }

                foreach ($this->nodes as $n) {
                        foreach ($attrs as $k => $v) {
                                // Algorithm 1:
                                $n->setAttribute($k, $v);

                                // Algorithm 2:
                                // $n->setAttributeNode(new \DOMAttr($k, $v));

                                // Algorithm 3:
                                // $n->appendChild(new \DOMAttr($k, $v));

                                // Algorithm 2 and 3 have a different behaviour
                                // from Algorithm 1.
                                // The attribute is still created or setted, but
                                // changing the value of an existing attribute
                                // changes even the order of that attribute
                                // in the attribute list.
                        }
                }

                return $this;
        }

        // Alias of setAttribute.
        public function attr(...$arguments)
        {
                return $this->setAttribute(...$arguments);
        }

        public function appendText($text)
        {
                foreach ($this->nodes as $n) {
                        $n->appendChild(new \DOMText($text));
                }

                return $this;
        }

        public function appendCdata($cdata)
        {
                foreach ($this->nodes as $n) {
                        $n->appendChild(new \DOMCDATASection($cdata));
                }

                return $this;
        }

        public function setText($text)
        {
                foreach ($this->nodes as $n) {
                        // Algorithm 1:
                        $n->nodeValue = $text;

                        // Algorithm 2:
                        // foreach ($n->childNodes as $c) {
                        //         $n->removeChild($c);
                        // }
                        // $n->appendChild(new \DOMText($text));

                        // Algorithm 3:
                        // foreach ($n->childNodes as $c) {
                        //         $n->replaceChild(new \DOMText($text), $c);
                        // }
                }

                return $this;
        }

        // Alias of setText.
        public function text($text)
        {
                return $this->setText($text);
        }

        public function remove($xpath)
        {
                // The function accepts a plain XPath string
                // or a specific context.
                $targets = $xpath;

                if (! $xpath instanceof FluidContext) {
                        $targets = $this->query($xpath);
                }

                foreach ($targets as $t) {
                        $t->parentNode->removeChild($t);
                }

                return $this;
        }

        protected function newContext($context)
        {
                return new FluidContext($this->dom, $context, $this->namespace);
        }

        protected function insertNode($fn, $node, ...$optionals)
        {
                if (! \is_array($node)) {
                        $node = [ $node ];
                }

                $switchContext = false;
                $attributes = [];

                foreach ($optionals as $opt) {
                        if (\is_array($opt)) {
                                $attributes = $opt;
                        } else if (\is_bool($opt)){
                                $switchContext = $opt;
                        } else if (\is_string($opt)) {
                                $n = \array_pop($node);
                                $node[$n] = $opt;
                        } else {
                                throw new \Exception("Optional argument '{$opt}' not recognized.");
                        }
                }

                $newContext = [];

                $insertNode = function($parent, $name, $value = null) use (&$newContext, $fn) {
                        // The DOMElement instance must be different for every node,
                        // otherwise only one element is attached to the DOM.
                        $el = new \DOMElement($name, $value);
                        // $el = $this->dom->createElement($name, $value);
                        $newContext[] = $fn($parent, $el);

                        return $el;
                };

                $processNode = function($parent, $k, $v) use (&$processNode, $insertNode, $optionals) {
                        if (\is_string($k)) {
                                // The user has passed one of these two cases:
                                // - [ 'element' => 'Text content.' ]
                                // - [ 'element' => [...] ]

                                if (\is_array($v)) {
                                        // The user has passed a recursive structure:
                                        // [ 'element' => [...] ]

                                        $el = $insertNode($parent, $k);

                                        $this->newContext($el)->appendChild($v, ...$optionals);
                                } else {
                                        // The user has passed a node name and a node value:
                                        // [ 'element' => 'Text content.' ]

                                        $insertNode($parent, $k, $v);
                                }
                        } else {
                                // The user has passed one of these two cases:
                                // - [ 'element', ... ]
                                // - [ [...], [...], ... ]

                                if (\is_array($v)) {
                                        // The user has passed a wrapper array:
                                        // [ [...], ... ]

                                        foreach ($v as $kk => $vv) {
                                                $processNode($parent, $kk, $vv);
                                        }
                                } else {
                                        // The user has passed a node name without a node value:
                                        // [ 'element', ... ]

                                        $insertNode($parent, $v);
                                }
                        }
                };

                foreach ($this->nodes as $n) {
                        foreach ($node as $k => $v) {
                                $processNode($n, $k, $v);
                        }
                }

                $context = $this->newContext($newContext);

                // Setting the attributes is an help that the appendChild method
                // offers to the user and is the same of:
                // 1. appending a child switching the context
                // 2. setting the attributes over the new context.
                if ($attributes) {
                        $context->setAttribute($attributes);
                }

                if ($switchContext) {
                        return $context;
                }

                return $this;
        }
}

////////////////////////////////////////////////////////////////////////////////

class FluidXml implements FluidInterface
{
        private $dom;

        public function __construct($options = [])
        {
                $defaults = [ 'version'    => '1.0',
                              'encoding'   => 'UTF-8',
                              'stylesheet' => null,
                              'namespace'  => null,
                              'root'       => 'doc' ];

                $opts = \array_merge($defaults, $options);

                $this->dom = new \DOMDocument($opts['version'], $opts['encoding']);
                $this->dom->formatOutput       = true;
                $this->dom->preserveWhiteSpace = false;
                $this->dom->resolveExternals   = true;

                $this->namespace = $opts['namespace'];


                if ($opts['root']) {
                        $this->appendSibling($opts['root']);
                }

                if ($opts['stylesheet']) {
                        $stylesheet = new \DOMProcessingInstruction('xml-stylesheet',
                                                                    'type="text/xsl"'
                                                                    ." encoding=\"{$opts['encoding']}\""
                                                                    ." indent=\"yes\""
                                                                    ." href=\"{$opts['stylesheet']}\"");
                        $this->dom->insertBefore($stylesheet, $this->query('/*')[0]);
                }
        }

        public function xml()
        {
                return $this->dom->saveXML();
        }

        public function dom()
        {
                return $this->dom;
        }

        public function query($xpath)
        {
                return $this->newContext($this->dom)->query($xpath);
        }

        public function appendChild($child, ...$optionals)
        {
                $context    = $this->newContext();
                $newContext = $context->appendChild($child, ...$optionals);

                return $this->chooseContext($context, $newContext);
        }

        // Alias of appendChild.
        public function add($child, ...$optionals)
        {
                return $this->appendChild($child, ...$optionals);
        }

        public function prependSibling($sibling, ...$optionals)
        {
                if ($this->query('/*')->length() === 0) {
                        // If the document doesn't have at least one root node,
                        // the sibling creation fails. In this case we replace
                        // the sibling creation with the creation of a generic node.
                        $context    = $this->newContext($this->dom);
                        $newContext = $context->appendChild($sibling, ...$optionals);
                } else {
                        $context    = $this->newContext();
                        $newContext = $context->prependSibling($sibling, ...$optionals);
                }

                return $this->chooseContext($context, $newContext);
        }

        // Alias of prependSibling.
        public function prepend($sibling, ...$optionals)
        {
                return $this->prependSibling($sibling, ...$optionals);
        }

        // Alias of prependSibling.
        public function insertSiblingBefore($sibling, ...$optionals)
        {
                return $this->prependSibling($sibling, ...$optionals);
        }

        public function appendSibling($sibling, ...$optionals)
        {
                if ($this->query('/*')->length() === 0) {
                        // If the document doesn't have at least one root node,
                        // the sibling creation fails. In this case we replace
                        // the sibling creation with the creation of a generic node.
                        $context    = $this->newContext($this->dom);
                        $newContext = $context->appendChild($sibling, ...$optionals);
                } else {
                        $context    = $this->newContext();
                        $newContext = $context->appendSibling($sibling, ...$optionals);
                }

                return $this->chooseContext($context, $newContext);
        }

        // Alias of appendSibling.
        public function append($sibling, ...$optionals)
        {
                return $this->appendSibling($sibling, ...$optionals);
        }

        // Alias of appendSibling.
        public function insertSiblingAfter($sibling, ...$optionals)
        {
                return $this->appendSibling($sibling, ...$optionals);
        }

        public function appendXml($xml, $asRoot = false)
        {
                if ($asRoot) {
                        $cx = $this->newContext($this->dom);
                } else {
                        $cx = $this->newContext();
                }

                $cx->appendXml($xml);

                return $this;
        }

        public function setAttribute(...$arguments)
        {
                $this->newContext()->setAttribute(...$arguments);

                return $this;
        }

        // Alias of setAttribute.
        public function attr(...$arguments)
        {
                return $this->setAttribute(...$arguments);
        }

        public function appendText($text)
        {
                $this->newContext()->appendText($text);

                return $this;
        }

        public function appendCdata($cdata)
        {
                $this->newContext()->appendCdata($cdata);

                return $this;
        }

        public function setText($text)
        {
                $this->newContext()->setText($text);

                return $this;
        }

        // Alias of setText.
        public function text($text)
        {
                return $this->setText($text);
        }

        public function remove($xpath)
        {
                $this->newContext()->remove($xpath);

                return $this;
        }

        protected function newContext($context = null)
        {
                if (! $context) {
                        $context = $this->dom->documentElement;
                }

                return new FluidContext($this->dom, $context, $this->namespace);
        }

        protected function chooseContext($helpContext, $newContext)
        {
                // If the two contextes are diffent, the user has requested
                // a switch of the context and we have to return it.
                if ($helpContext !== $newContext) {
                        return $newContext;
                }

                return $this;
        }
}
////////////////////////////////////////////////////////////////////////////////