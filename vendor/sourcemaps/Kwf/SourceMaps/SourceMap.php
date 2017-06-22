<?php
/**
 * Various Sourcemaps Utilities
 *
 * An instance of this class represents a source map plus the minimied file
 */
class Kwf_SourceMaps_SourceMap
{
    protected $_map;
    protected $_fileContents;
    protected $_mappings;
    protected $_mappingsChanged = false; //set to true if _mappings changed and _map['mappings'] is outdated
    protected $_mimeType;

    /**
     * @param string contents of the source map
     * @param string contents of the minified file
     */
    public function __construct($mapContents, $fileContents)
    {
        if (is_string($mapContents)) {
            $this->_map = json_decode($mapContents);
            if (!$this->_map) {
                throw new Exception("Failed parsing map: ".json_last_error());
            }
        } else {
            $this->_map = $mapContents;
        }
        if (!isset($this->_map->version)) {
            throw new Exception("Invalid Source Map");
        }
        if ($this->_map->version != 3) {
            throw new Exception("Unsupported Version");
        }
        $this->_fileContents = $fileContents;
    }

    public function setFile($file)
    {
        $this->_map->file = $file;
    }

    public function getFile()
    {
        return $this->_map->file;
    }

    public function setSourceRoot($sourceRoot)
    {
        $this->_map->sourceRoot = $sourceRoot;
    }

    public function getSourceRoot()
    {
        return $this->_map->sourceRoot;
    }

    public function addSource($source)
    {
        $this->_map->sources[] = $source;
    }

    public function setSources(array $sources)
    {
        $this->_map->sources = $sources;
    }

    public function getSources()
    {
        return $this->_map->sources;
    }

    public function setMimeType($v)
    {
        $this->_mimeType = $v;
    }

    public function getMimeType()
    {
        return $this->_mimeType;
    }

    /**
     * Create a new, empty sourcemap
     *
     * @param string contents of the minified file
     */
    public static function createEmptyMap($fileContents)
    {
        $map = (object) array(
            'version' => 3,
            'mappings' => '',
            'sources' => array(),
            'names' => array(),
        );
        return new self($map, $fileContents);
    }

    /**
     * Create a new sourcemap based on sourceMappingURL with inline base64 encoded data
     *
     * Example:
     * //# sourceMappingURL=data:application/json;base64,....
     *
     * @param string contents of the minified file including sourceMappingURL
     */
    public static function createFromInline($fileContents)
    {
        // '//# sourceMappingURL=data:application/json;charset:utf-8;base64,
        // '//# sourceMappingURL=data:application/json;base64,'
        $pos = strrpos($fileContents, "\n//# sourceMappingURL=");
        $isCss = false;
        if ($pos === false) {
            $pos = strrpos($fileContents, "\n/*# sourceMappingURL=");
            if ($pos === false) {
                throw new Exception("No sourceMappingURL found");
            }
            $isCss = true;
        }
        $url = substr($fileContents, $pos + 22);
        $url = rtrim($url);
        if ($isCss) {
            if (substr($url, -2) != '*/') {
                throw new Exception("sourceMappingURL isn't wrapped with closing */");
            }
            $url = substr($url, 0, -2); //remove "*/"
            $url = rtrim($url);
        }

        if (substr($url, 0, 29) == 'data:application/json;base64,') {
            $map = substr($url, 29);
        } else if (substr($url, 0, 29 + 14) == 'data:application/json;charset:utf-8;base64,') {
            $map = substr($url, 29 + 14);
        } else if (substr($url, 0, 29 + 14) == 'data:application/json;charset=utf-8;base64,') {
            $map = substr($url, 29 + 14);
        } else {
            throw new Exception("Unsupported sourceMappingURL");
        }
        $map = base64_decode($map);
        $map = json_decode($map);
        $fileContents = substr($fileContents, 0, $pos);
        $ret = new self($map, $fileContents);
        $ret->setMimeType($isCss ? 'text/css' : 'text/javascript');
        return $ret;
    }

    public static function hasInline($fileContents)
    {
        $pos = strrpos($fileContents, "\n//# sourceMappingURL=data:");
        if ($pos !== false) return true;
        $pos = strrpos($fileContents, "\n/*# sourceMappingURL=data:");
        if ($pos !== false) return true;

        return false;
    }

    /**
     * Adds a mapping
     *
     * @param integer $generatedLine The line number in generated file
     * @param integer $generatedColumn The column number in generated file
     * @param integer $originalLine The line number in original file
     * @param integer $originalColumn The column number in original file
     * @param string $originalSource The original source file
     * @param string $originalName The original source name (optional)
     */
    public function addMapping($generatedLine, $generatedColumn, $originalLine, $originalColumn, $originalSource, $originalName = null)
    {
        if (!isset($this->_mappings)) {
            $this->getMappings();
        }
        $this->_mappings[] = array(
            'generatedLine' => $generatedLine,
            'generatedColumn' => $generatedColumn,
            'originalLine' => $originalLine,
            'originalColumn' => $originalColumn,
            'originalSource' => $originalSource,
            'originalName' => $originalName,
        );
        $this->_mappingsChanged = true;
    }


    /**
     * Generates the mappings string
     *
     * Parts based on https://github.com/oyejorge/less.php/blob/master/lib/Less/SourceMap/Generator.php
     * Apache License Version 2.0
     *
     * @return string
     */
    private function _generateMappings()
    {
        if (!isset($this->_mappings) && $this->_map->mappings) {
            //up to date, nothing to do
            return;
        }
        $this->_mappingsChanged = false;
        if (!count($this->_mappings)) {
            return '';
        }

        foreach ($this->_mappings as $m) {
            if ($m['originalSource'] && !in_array($m['originalSource'], $this->_map->sources)) {
                $this->_map->sources[] = $m['originalSource'];
            }
        }

        $this->_map->names = array();
        foreach ($this->_mappings as $m) {
            if ($m['originalName'] && !in_array($m['originalName'], $this->_map->names)) {
                $this->_map->names[] = $m['originalName'];
            }
        }

        // group mappings by generated line number.
        $groupedMap = $groupedMapEncoded = array();
        foreach ($this->_mappings as $m) {
            $groupedMap[$m['generatedLine']][] = $m;
        }
        ksort($groupedMap);

        $lastGeneratedLine = $lastOriginalSourceIndex = $lastOriginalNameIndex = $lastOriginalLine = $lastOriginalColumn = 0;

        foreach ($groupedMap as $lineNumber => $lineMap) {
            while (++$lastGeneratedLine < $lineNumber) {
                $groupedMapEncoded[] = ';';
            }

            $lineMapEncoded = array();
            $lastGeneratedColumn = 0;

            foreach ($lineMap as $m) {
                $mapEncoded = Kwf_SourceMaps_Base64VLQ::encode($m['generatedColumn'] - $lastGeneratedColumn);
                $lastGeneratedColumn = $m['generatedColumn'];

                // find the index
                if ($m['originalSource']) {
                    $index = array_search($m['originalSource'], $this->_map->sources);
                    $mapEncoded .= Kwf_SourceMaps_Base64VLQ::encode($index - $lastOriginalSourceIndex);
                    $lastOriginalSourceIndex = $index;

                    // lines are stored 0-based in SourceMap spec version 3
                    $mapEncoded .= Kwf_SourceMaps_Base64VLQ::encode($m['originalLine'] - 1 - $lastOriginalLine);
                    $lastOriginalLine = $m['originalLine'] - 1;

                    $mapEncoded .= Kwf_SourceMaps_Base64VLQ::encode($m['originalColumn'] - $lastOriginalColumn);
                    $lastOriginalColumn = $m['originalColumn'];

                    if ($m['originalName']) {
                        $index = array_search($m['originalName'], $this->_map->names);
                        $mapEncoded .= Kwf_SourceMaps_Base64VLQ::encode($index - $lastOriginalNameIndex);
                        $lastOriginalNameIndex = $index;
                    }
                }

                $lineMapEncoded[] = $mapEncoded;
            }

            $groupedMapEncoded[] = implode(',', $lineMapEncoded).';';
        }

        $this->_map->mappings = rtrim(implode($groupedMapEncoded), ';');
    }

    /**
     * Performant Source Map aware string replace
     *
     * @param string
     * @param string
     */
    public function stringReplace($string, $replace)
    {
        if ($this->_mappingsChanged) {
            $this->_generateMappings();
        }

        if (strpos("\n", $string)) {
            throw new Exception('string must not contain \n');
        }
        if ($replace != "" && strpos("\n", $replace)) {
            throw new Exception('replace must not contain \n');
        }

        $adjustOffsets = array();
        $pos = 0;
        $str = $this->_fileContents;
        $offset = 0;
        $lineOffset = 0;
        while (($pos = strpos($str, $string, $pos)) !== false) {
            $line = substr_count(substr($str, 0, $pos), "\n") + 1;
            if (!isset($adjustOffsets[$line])) {
                //first in line
                $lineOffset = 0;
            }
            $this->_fileContents = substr($this->_fileContents, 0, $pos + $offset).$replace.substr($this->_fileContents, $pos + $offset + strlen($string));
            $offset += strlen($replace) - strlen($string);
            $lineOffset += strlen($replace) - strlen($string);
            $column = $pos - strrpos(substr($str, 0, $pos), "\n") + 1; //strrpos can return false for first line which will subtract 0 (=false)
            $adjustOffsets[$line][] = array(
                'column' => $column,
                'absoluteOffset' => $offset,
                'lineOffset' => $lineOffset,
                'offset' => strlen($replace) - strlen($string),
                'replacedLength' => strlen($string)
            );
            $pos = $pos + strlen($string);
        }

        $mappings = $this->getMappings();
        $this->_mappingsChanged = true;
        $this->_mappings = array();
        foreach ($mappings as $mappingIndex=>$mapping) {
            if (isset($adjustOffsets[$mapping['generatedLine']])) {
                foreach (array_reverse($adjustOffsets[$mapping['generatedLine']], true) as $offsIndex=>$offs) {
                    if ($mapping['generatedColumn'] > $offs['column']) {
                        if ($mapping['generatedColumn'] < $offs['column']-1+$offs['replacedLength']) {
                            //mapping inside replaced test, remove
                            continue 2;
                        } else {
                            $mapping['generatedColumn'] += $offs['lineOffset'];
                            break;
                        }
                    }
                }
            }
            $this->_mappings[] = $mapping;
        }
    }

    /**
     * set/overwrite all mappings
     *
     * @param array $mappings must be in the same form as returned by getMappings
     *
     */
    public function setMappings(array $mappings)
    {
        $this->_mappings = $mappings;
        $this->_mappingsChanged = true;
        return $this;
    }

    /**
     * Return all mappings
     *
     * @return array with assoc array containing: generatedLine, generatedColumn, originalSource, originalLine, originalColumn, originalName
     */
    public function getMappings()
    {
        if (isset($this->_mappings)) {
            return $this->_mappings;
        }

        $this->_mappings = array();

        $generatedLine = 1;
        $previousGeneratedColumn = 0;
        $previousOriginalLine = 0;
        $previousOriginalColumn = 0;
        $previousSource = 0;
        $previousName = 0;

        $str = $this->_map->mappings;
        $end = strlen($str);
        $pos = 0;

        while ($pos < $end) {
            if ($str[$pos] === ';') {
                $generatedLine++;
                $pos++;
                $previousGeneratedColumn = 0;
            } else if ($str[$pos] === ',') {
                $pos++;
            } else {
                $mapping = array();
                $mapping['generatedLine'] = $generatedLine;

                // Generated column.
                $value = Kwf_SourceMaps_Base64VLQ::decodePos($str, $pos);
                $mapping['generatedColumn'] = $previousGeneratedColumn + $value;
                $previousGeneratedColumn = $mapping['generatedColumn'];

                if ($pos < $end && !($str[$pos]==',' || $str[$pos]==';')) {
                    // Original source.
                    $value = Kwf_SourceMaps_Base64VLQ::decodePos($str, $pos);
                    $mapping['originalSource'] = (isset($this->_map->sourceRoot) ? $this->_map->sourceRoot.'/' : '')
                                                  . $this->_map->sources[$previousSource + $value];
                    $previousSource += $value;
                    if ($pos >= $end || ($str[$pos]==',' || $str[$pos]==';')) {
                        throw new Exception('Found a source, but no line and column');
                    }

                    // Original line.
                    $value = Kwf_SourceMaps_Base64VLQ::decodePos($str, $pos);
                    $mapping['originalLine'] = $previousOriginalLine + $value;
                    $previousOriginalLine = $mapping['originalLine'];
                    // Lines are stored 0-based
                    $mapping['originalLine'] += 1;
                    if ($pos >= $end || ($str[$pos] == ',' || $str[$pos] == ';')) {
                        throw new Exception('Found a source and line, but no column');
                    }

                    // Original column.
                    $value = Kwf_SourceMaps_Base64VLQ::decodePos($str, $pos);
                    $mapping['originalColumn'] = $previousOriginalColumn + $value;
                    $previousOriginalColumn = $mapping['originalColumn'];

                    if ($pos < $end && !($str[$pos] == ',' || $str[$pos] == ';')) {
                        // Original name.
                        $value = Kwf_SourceMaps_Base64VLQ::decodePos($str, $pos);
                        $mapping['originalName'] = $this->_map->names[$previousName + $value];
                        $previousName += $value;
                    } else {
                        $mapping['originalName'] = null;
                    }
                }
                $this->_mappings[] = $mapping;
            }
        }
        return $this->_mappings;
    }

    protected function _addLastExtension()
    {
        $previousGeneratedColumn = 0;
        $previousOriginalLine = 0;
        $previousOriginalColumn = 0;
        $previousSource = 0;
        $previousName = 0;
        $lineCount = 0;

        $str = $this->_map->mappings;
        $end = strlen($str);
        $pos = 0;

        while ($pos < $end) {

            if ($str[$pos] === ';') {
                $pos++;
                $previousGeneratedColumn = 0;
                $lineCount++;
            } else if ($str[$pos] === ',') {
                $pos++;
            } else {
                // Generated column.
                $previousGeneratedColumn += Kwf_SourceMaps_Base64VLQ::decodePos($str, $pos);

                if ($pos < $end && !($str[$pos]==',' || $str[$pos]==';')) {
                    // Original source.
                    $previousSource += Kwf_SourceMaps_Base64VLQ::decodePos($str, $pos);
                    if ($pos >= $end || ($str[$pos]==',' || $str[$pos]==';')) {
                        throw new Exception('Found a source, but no line and column');
                    }

                    // Original line.
                    $previousOriginalLine += Kwf_SourceMaps_Base64VLQ::decodePos($str, $pos);
                    if ($pos >= $end || ($str[$pos] == ',' || $str[$pos] == ';')) {
                        throw new Exception('Found a source and line, but no column');
                    }

                    // Original column.
                    $previousOriginalColumn += Kwf_SourceMaps_Base64VLQ::decodePos($str, $pos);

                    if ($pos < $end && !($str[$pos] == ',' || $str[$pos] == ';')) {
                        // Original name.
                        $previousName += Kwf_SourceMaps_Base64VLQ::decodePos($str, $pos);
                    }
                }
            }
        }
        if ($this->_map->mappings) { //only add if mapping is not empty
            $this->_map->{'_x_org_koala-framework_last'} = (object) array(
                'source' => $previousSource,
                'originalLine' => $previousOriginalLine,
                'originalColumn' => $previousOriginalColumn,
                'name' => $previousName,
            );
            /*
            commented out as it might lead to false positives if the last line doesn't contain a single mapping
            if (substr_count($this->_fileContents, "\n") != $lineCount) {
                throw new Exception("line count in mapping ($lineCount) doesn't match file (".substr_count($this->_fileContents, "\n").")");
            }
            */
        }
    }

    /**
     * Concat sourcemaps and keep mappings intact
     *
     * This is implemented very efficent by avoiding to parse the whole mappings string.
     */
    public function concat(Kwf_SourceMaps_SourceMap $other)
    {
        if ($this->_mappingsChanged) {
          $this->_generateMappings();
        }
        $missingLines = substr_count($this->_fileContents, "\n")-substr_count($this->_map->mappings, ";");
        if ($missingLines > 0) {
            $this->_map->mappings .= str_repeat(';', $missingLines);
        }
        if (!isset($this->_map->{'_x_org_koala-framework_last'})) {
            $this->_addLastExtension();
        }

        if (strlen($this->_fileContents) > 0) {
            if (substr($this->_fileContents, -1) != "\n") {
                $this->_fileContents .= "\n";
                $this->_map->mappings .= ';';
            }
        }

        $this->_fileContents .= $other->_fileContents;

        $data = $other->getMapContentsData();

        if ($this->_map->mappings) {
            $previousFileLast = $this->_map->{'_x_org_koala-framework_last'};
        } else {
            $previousFileLast = (object) array(
                'source' => 0,
                'originalLine' => 0,
                'originalColumn' => 0,
                'name' => 0,
            );
        }
        if (!$data->mappings) {
            $data->mappings = str_repeat(';', substr_count($other->_fileContents, "\n"));
            $data->{'_x_org_koala-framework_last'} = (object) array(
                'source' => -1,
                'originalLine' => $previousFileLast->originalLine,
                'originalColumn' => $previousFileLast->originalColumn,
                'name' => -1,
            );
        }
        $previousFileSourcesCount = count($this->_map->sources);
        $previousFileNamesCount = count($this->_map->names);
        if ($previousFileLast->source > $previousFileSourcesCount) {
            if ($previousFileSourcesCount != 0 && $previousFileLast->source != 0) {
                throw new Exception("Invalid last source, must not be higher than sources");
            }
        }

        if ($previousFileLast->name > $previousFileNamesCount) {
            if ($previousFileNamesCount != 0 && $previousFileLast->name != 0) {
                throw new Exception("Invalid last name, must not be higher than names");
            }
        }

        if ($data->sources) {
            foreach ($data->sources as $s) {
                $this->_map->sources[] = $s;
            }
        }
        if ($data->names) {
            foreach ($data->names as $n) {
                $this->_map->names[] = $n;
            }
        }

        $otherMappings = $data->mappings;

        $str = '';

        $otherMappingsEnd = strlen($otherMappings);
        $otherMappingsPos = 0;

        while ($otherMappingsPos < $otherMappingsEnd && $otherMappings[$otherMappingsPos] === ';') {
            $str .= $otherMappings[$otherMappingsPos];
            $otherMappingsPos++;
        }
        if ($otherMappingsPos < $otherMappingsEnd) {

            // Generated column.
            $str .= Kwf_SourceMaps_Base64VLQ::encode(Kwf_SourceMaps_Base64VLQ::decodePos($otherMappings, $otherMappingsPos));
            if ($otherMappingsPos < $otherMappingsEnd && !($otherMappings[$otherMappingsPos] == ',' || $otherMappings[$otherMappingsPos] == ';')) {

                // Original source.
                $value = Kwf_SourceMaps_Base64VLQ::decodePos($otherMappings, $otherMappingsPos);
                if ($previousFileSourcesCount) {
                    $absoluteValue = $value + $previousFileSourcesCount;
                    $value = $absoluteValue - $previousFileLast->source;
                }
                $str  .= Kwf_SourceMaps_Base64VLQ::encode($value);

                // Original line.
                $str  .= Kwf_SourceMaps_Base64VLQ::encode(Kwf_SourceMaps_Base64VLQ::decodePos($otherMappings, $otherMappingsPos) - $previousFileLast->originalLine);

                // Original column.
                $str  .= Kwf_SourceMaps_Base64VLQ::encode(Kwf_SourceMaps_Base64VLQ::decodePos($otherMappings, $otherMappingsPos) - $previousFileLast->originalColumn);

                // Original name.
                if ($otherMappingsPos < $otherMappingsEnd && !($otherMappings[$otherMappingsPos] == ',' || $otherMappings[$otherMappingsPos] == ';')) {
                    $value = Kwf_SourceMaps_Base64VLQ::decodePos($otherMappings, $otherMappingsPos);
                    if ($previousFileNamesCount) {
                        $absoluteValue = $value + $previousFileNamesCount;
                        $value = $absoluteValue - $previousFileLast->name;
                    }
                    $str .= Kwf_SourceMaps_Base64VLQ::encode($value);
                } else if (!count($data->names)) {
                    //file doesn't have names at all, we don't have to adjust that offset
                } else {
                    //loop thru mappings until we find a block with name
                    while ($otherMappingsPos < $otherMappingsEnd) {
                        if ($otherMappings[$otherMappingsPos] === ';') {
                            $str .= $otherMappings[$otherMappingsPos];
                            $otherMappingsPos++;
                        } else if ($otherMappings[$otherMappingsPos] === ',') {
                            $str .= $otherMappings[$otherMappingsPos];
                            $otherMappingsPos++;
                        } else {
                            // Generated column.
                            $str .= Kwf_SourceMaps_Base64VLQ::encode(Kwf_SourceMaps_Base64VLQ::decodePos($otherMappings, $otherMappingsPos));

                            if ($otherMappingsPos < $otherMappingsEnd && !($otherMappings[$otherMappingsPos] == ',' || $otherMappings[$otherMappingsPos] == ';')) {
                                // Original source.
                                $str .= Kwf_SourceMaps_Base64VLQ::encode(Kwf_SourceMaps_Base64VLQ::decodePos($otherMappings, $otherMappingsPos));

                                // Original line.
                                $str .= Kwf_SourceMaps_Base64VLQ::encode(Kwf_SourceMaps_Base64VLQ::decodePos($otherMappings, $otherMappingsPos));

                                // Original column.
                                $str .= Kwf_SourceMaps_Base64VLQ::encode(Kwf_SourceMaps_Base64VLQ::decodePos($otherMappings, $otherMappingsPos));

                                if ($otherMappingsPos < $otherMappingsEnd && !($otherMappings[$otherMappingsPos] == ',' || $otherMappings[$otherMappingsPos] == ';')) {
                                    // Original name.
                                    $value = Kwf_SourceMaps_Base64VLQ::decodePos($otherMappings, $otherMappingsPos);
                                    if ($previousFileNamesCount) {
                                        $absoluteValue = $value + $previousFileNamesCount;
                                        $value = $absoluteValue - $previousFileLast->name;
                                    }
                                    $str .= Kwf_SourceMaps_Base64VLQ::encode($value);
                                    break;
                                }
                            }
                        }
                    }
                }
            }

        }

        $this->_map->mappings .= $str.substr($otherMappings, $otherMappingsPos);

        $this->_map->{'_x_org_koala-framework_last'} = (object) array(
            'source' => $previousFileSourcesCount + $data->{'_x_org_koala-framework_last'}->source,
            'name' => $previousFileNamesCount + $data->{'_x_org_koala-framework_last'}->name,
            'originalLine' => $data->{'_x_org_koala-framework_last'}->originalLine,
            'originalColumn' => $data->{'_x_org_koala-framework_last'}->originalColumn
        );
    }

    /**
     * Returns the contents of the minified file
     */
    public function getFileContents()
    {
        return $this->_fileContents;
    }

    /**
     * Sets the contents of the minified file
     */
    public function setFileContents($fileContents)
    {
        $this->_fileContents = $fileContents;
        return $this;
    }

    /**
     * Returns the contents of the source map as string
     *
     * @return string
     */
    public function getMapContents($includeLastExtension = true)
    {
        if ($this->_mappingsChanged) {
          $this->_generateMappings();
        }
        if ($includeLastExtension && !isset($this->_map->{'_x_org_koala-framework_last'})) {
            $this->_addLastExtension();
        }
        return json_encode($this->_map);
    }

    /**
     * Returns the contents of the source map as object (that can be json_encoded)
     *
     * @return stdObject
     */
    public function getMapContentsData($includeLastExtension = true)
    {
        if ($this->_mappingsChanged) {
          $this->_generateMappings();
        }
        if ($includeLastExtension && !isset($this->_map->{'_x_org_koala-framework_last'})) {
            $this->_addLastExtension();
        }
        return $this->_map;
    }

    /**
     * Save the source map to a file
     *
     * @param string file name the source map should be saved to
     * @param string optional file name the minified file should be saved to
     */
    public function save($mapFileName, $fileFileName = null)
    {
        if ($fileFileName !== null) {
          file_put_contents($fileFileName, $this->_fileContents);
        }
        file_put_contents($mapFileName, $this->getMapContents());
    }

    /**
     * Returns the contents of the minimied file with source map data appended inline as data url
     *
     * @return string
     */
    public function getFileContentsInlineMap($includeLastExtension = true)
    {
        $ret = $this->_fileContents;
        if ($this->_mimeType == 'text/css') {
            $ret .= "\n/*# sourceMappingURL=data:application/json;base64,".base64_encode($this->getMapContents($includeLastExtension))." */\n";
        } else {
            $ret .= "\n//# sourceMappingURL=data:application/json;base64,".base64_encode($this->getMapContents($includeLastExtension))."\n";
        }
        return $ret;
    }
}
