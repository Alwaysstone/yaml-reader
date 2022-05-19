<?php
namespace Alwaysstone\YAMLReader;

/**
 * YAML read/write and modify yaml fils
 *
 * wrap the symfony-yaml and implemento xpath style to manage single node of YAML file
 * rewrite yaml symfony emit function to obtain a more clean yaml output
 */
 



use \Symfony\Component\Yaml\Yaml;
use \Symfony\Component\Yaml\Exception\ParseException;

use Alwaysstone\YAMLReader\Utils;
use Alwaysstone\YAMLReader\YAMLReaderException;

/**
 * Main Class
 * 
 * depends on \Symfony\Component\Yaml
 * 
 * Almost all methods except the save one returns the class itself for chain actions
 */

class YAMLReader {
    private $yaml_loaded = false;
    private $yaml_obj = null;
    private $yaml_err = null;
    private $dom = null;

    /**
     * Costruttore
     * 
     * @param string|null $filename
     * @return YAMLReader
     */
    public function __construct(string|null $filename = null) {
        $this->yaml = null;
        if (!is_null($filename)) {
            $this->loadFile($filename);
        }
        return $this;
    }
    
    /**
     * True if YAMLReader is ready (yaml loaded and dom builded)
     * @return bool
     */
    
    public function isReady(): bool {
        return $this->yaml_loaded && is_null($this->yaml_err);
    }
        
    /**
     * load and parse a yml file
     * 
     * @param string $filename
     * @return YAMLReader
     * @throws YAMLReaderException
     */
    public function loadFile(string $filename): YAMLReader {
        $this->yaml = $filename;
        if (!file_exists($filename)) {
            throw new YAMLReaderException("File Not Found: ".$filename);
        }
        $this->loadString(file_get_contents($filename));
        return $this;
    }
    
    /**
     * load and parse a yml string
     *  
     * @param string $yaml
     * @return YAMLReader
     * @throws ParseException
     */
    public function loadString(string $yaml): YAMLReader {
        $this->dom  = null;
        try {
            $this->yaml_obj = Yaml::parse($yaml);
            $this->yaml_loaded = true;
            $this->yaml_err = null;
        } catch(ParseException $ex) {
            $this->yaml_loaded = false;
            $this->yaml_obj = null;
            $this->yaml_err = $ex;
            throw $ex;
        }
        $this->_populateDom();
        
        return $this;
    }

    /**
     * build dom
     * @internal
     */
    private function _populateDom() {
        $this->dom = new \DOMDocument();

        $root = $this->dom->appendChild(new \DOMElement('root'));
        $this->to_xml($this->dom, $root, $this->yaml_obj);
    }
    
    /**
     * normalize the pos attribute for move method
     * @internal
     * @param string|int|null $pos
     * @return array
     */
    private static function _normalizePos(string|int|null $pos): array {
        if (is_null($pos)) {
            $pos = -1;
        }
        $key = $boa = $pos;
        if (!is_numeric($pos)) {
            $keys = explode(":", $pos);
            if (count($keys) == 1) {
                $key = $pos;
                $boa = 'a';
            } else {
                $key = $keys[1];
                $boa = $keys[0];
            }
        }
        
        return [$pos, $key, $boa];
    }
    
    /**
     * move the FROM elemento to the TO destination at POS position
     * 
     * If POS [elementname] is not found it failbacks to -1 (very end of the destination)
     * 
     * @param string $from full path of the element to be moved
     * @param string $to full path of the destination
     * @param string|int|null $pos position where pase the element: 
     *                             - 0: at the very beginng of the target path
     *                             - -1: at the very end of the target path
     *                             - [elementname] or a:[elementname] after the element
     *                             - [elementname] or b:[elementname] juts before the element
     * @return YAMLReader
     * @throws YAMLReaderException
     */
    
    public function move (string $from, string $to, string|int|null $pos): YAMLReader {
        if ($this->isReady()) {
            list ($pos, $key, $boa) = self::_normalizePos($pos);

            $xpath = new \DOMXpath($this->dom);
            $source = $xpath->query("/root".$from, $this->dom);
            if ($source->count() == 0) {
                throw new YAMLReaderException("Source not found ".$from);
            }
            $elemToPaste = $source->item(0)->parentNode->removeChild($source->item(0));
            $dest   = $xpath->query("/root".$to, $this->dom);
            if ($dest->count() > 0) {
                $target = (!is_numeric($key))?$xpath->query("./".$key, $dest->item(0)):false;
                if (
                    $pos === -1 || 
                    is_null($target) || 
                    (
                        $target !== false && 
                        (
                            $target->count() == 0 || 
                            (
                                $boa == 'a' && 
                                is_null($target->item(0)->nextElementSibling)
                            )
                        )
                    ) 
                ) {
                    $dest->item(0)->appendChild($elemToPaste);
                } elseif ($pos === 0) {
                    $dest->item(0)->insertBefore($elemToPaste, $dest->item(0)->firstChild);
                } elseif ($boa == 'a') {
                    $dest->item(0)->insertBefore($elemToPaste, $target->item(0)->nextElementSibling);
                } else {
                    $dest->item(0)->insertBefore($elemToPaste, $target->item(0));
                }
            } else {
                throw new YAMLReaderException("Destination not found ".$to);
            }
        } else {
            throw new YAMLReaderException("YAMLReader is not ready");
        }
        return $this;
    }
    
    /**
     * Remove a element from the YAML
     * 
     * the element and all its childnodes will be deleted
     * 
     * @param string $path full path of element to delete
     * @return YAMLReader
     * @throws YAMLReaderException
     */
    public function delete (string $path): YAMLReader {
        if ($this->isReady()) {
            $xpath = new \DOMXpath($this->dom);
            $source = $xpath->query("/root".$path, $this->dom);
            $elemToPaste = false;
            if ($source->count() > 0) {
                $elemToPaste = $source->item(0)->parentNode->removeChild($source->item(0));
            }
        } else {
            throw new YAMLReaderException("YAMLReader is not ready");
        }
        return $this;
    }
    
    /**
     * Return the current position of specified path, almost for exclusive testing purpose
     * 
     * @param string $path full path
     * @return int
     * @throws YAMLReaderException
     */
    public function findPosition(string $path) {
        if ($this->isReady()) {
            $xpath = new \DOMXpath($this->dom);
            $source = $xpath->query("/root".$path, $this->dom);
            if ($source->count() > 0) {
                $siblings = $source->item(0)->parentNode->childNodes;
                $i = 0;
                foreach ($siblings as $node) {
                    if ($source->item(0)->isSameNode($node)) {
                        return $i;
                    }
                    $i++;
                }
            }
        } else {
            throw new YAMLReaderException("YAMLReader is not ready");
        }
        
        return -1;
    }
    
    /**
     * True if the path is present, almost for exclusive testing purpose
     * 
     * @param string $path full path
     * @return type
     * @throws YAMLReaderException
     */
    public function isPresent(string $path) {
        if ($this->isReady()) {
            $xpath = new \DOMXpath($this->dom);
            $source = $xpath->query("/root".$path, $this->dom);
            return $source->count() > 0;
        } else {
            throw new YAMLReaderException("YAMLReader is not ready");
        }
    }
    
    /**
     * Update destination path with the value
     * 
     * current version does not work with array or hiearchical structure, 
     * use "create" for that, after deleting the old node
     * 
     * @param string $path full path
     * @param string|int $value
     * @return YAMLReader
     * @throws YAMLReaderException
     */
    public function update (string $path, string|int $value): YAMLReader {
        if ($this->isReady()) {
            $xpath = new \DOMXpath($this->dom);
            $source = $xpath->query("/root".$path, $this->dom);

            if ($source->count() > 0) {
                $source->item(0)->nodeValue = $value;
            } else {
                throw new YAMLReaderException("Source not found ".$path);
            }
        } else {
            throw new YAMLReaderException("YAMLReader is not ready");
        }
        return $this;
    }
    
    /**
     * Returns the value found for the full path
     * 
     * Returns simple values, for more complex one, use extract
     * 
     * @param string $path
     * @return mixed
     * @throws YAMLReaderException
     */
    public function valueAt (string $path): mixed {
        if ($this->isReady()) {
            $xpath = new \DOMXpath($this->dom);
            $source = $xpath->query("/root".$path, $this->dom);

            if ($source->count() > 0) {
                return $source->item(0)->nodeValue;
            } else {
                throw new YAMLReaderException("Source not found ".$path);
            }
        } else {
            throw new YAMLReaderException("YAMLReader is not ready");
        }
        return null;
    }
    
    /**
     * Extract yaml branch from the specified path
     * 
     * Returns a new instance of YAMLReader with the data found in path
     * 
     * @param string $path
     * @return YAMLReader
     * @throws YAMLReaderException
     */
    public function extract (string $path): YAMLReader {
        if ($this->isReady()) {
            $xpath = new \DOMXpath($this->dom);
            $source = $xpath->query("/root".$path, $this->dom);
            $elemToPaste = false;
            if ($source->count() > 0) {
                $elemToPaste = $source->item(0);
            } else {
                throw new YAMLReaderException("Source not found ".$path);
            }

            
            $yamlArray = $this->XML2Array($this->dom->saveXML($elemToPaste));
            
            if (!empty($elemToPaste->nodeName)) {
                $yamlArray = [
                    $elemToPaste->nodeName => $yamlArray
                ];
            }
            
            $output = Utils::buildOutYAML($yamlArray);    
            
            $newYAMLR = new YAMLReader();
            $newYAMLR->loadString($output);
            
        } else {
            throw new YAMLReaderException("YAMLReader is not ready");
        }
        
        return $newYAMLR;
    }
    
    /**
     * @internal
     * @param string $xml
     * @return type
     */
    public function XML2Array(string $xml) {
        $xmltmp = simplexml_load_string($xml, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xmltmp);
        $array = json_decode($json,TRUE);
        return $array;
    }
    
    /**
     * Inject a full YAML piece at fullpath
     * 
     * @param string $fullpath the full path, example /italia/regioni/sardegna/province
     * @param mixed $data the data to be inject es ["abitanti": 1000, comuni: [] ]
     * @return YAMLReader
     * @throws YAMLReaderException
     */
    public function create(string $fullpath, mixed $data): YAMLReader {
        if ($this->isReady()) {
            $parent_path = substr($fullpath, 0, strrpos($fullpath, "/"));
            $nodename = substr($fullpath, strrpos($fullpath, "/")+1);
            $xpath = new \DOMXpath($this->dom);
            $dest = $xpath->query("/root".$parent_path, $this->dom);
            if ($dest->count() == 0) {
                return $this;
            }
            $elem = $this->dom->createElement($nodename);

            $this->to_xml($this->dom, $elem, $data);

            $dest->item(0)->appendChild($elem);
        } else {
            throw new YAMLReaderException("YAMLReader is not ready");
        }

        return $this;
    }
    
    /**
     * @internal
     * @param string $filename
     */
    private function _saveClean(string $filename) {
        $xml = simplexml_load_string($this->dom->saveXML(), "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        $array = json_decode($json,TRUE);
        
        $output = trim(Utils::buildOutYAML($array));
        file_put_contents($filename, $output);
    }

    /**
     * Save the yml after manipulation
     * 
     * @param string $filename
     * @return type
     * @throws YAMLReaderException
     */
    public function save(string $filename) {
        if ($this->isReady()) {
            return $this->_saveClean($filename);
        } else {
            throw new YAMLReaderException("YAMLReader is not ready");
        }    
            
    }
    
    /**
     * @internal
     */
    public function to_xml(\DOMDocument $thisdom, \DOMElement $element, array $data) {   
        
        foreach ($data as $key => $value) {
            $key = Utils::bonificaKey($key);
            if (is_array($value)) {
                $new_object = $element->appendChild($thisdom->createElement($key));
                $this->to_xml($thisdom, $new_object, $value);
            } else {
                $element->appendChild($thisdom->createElement($key, $value));
                
            }   
        }   
        
        
    }
    
}
