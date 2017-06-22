<?php
class Kwf_SourceMaps_SourcesTest extends PHPUnit_Framework_TestCase
{
    public function testRead()
    {
        $map = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $mappings = $map->getMappings();
        $sources = $map->getSources();
        $this->assertEquals(array('one.js', 'two.js'), $sources);
    }

    public function testAdd()
    {
        $map = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $mappings = $map->getMappings();
        $map->addSource('three.js');
        $sources = $map->getSources();
        $this->assertEquals(array('one.js', 'two.js', 'three.js'), $sources);

        $c = $map->getFileContentsInlineMap(false);
        $map = Kwf_SourceMaps_SourceMap::createFromInline($c);
        $this->assertEquals(array('one.js', 'two.js', 'three.js'), $sources);

        $map->addMapping(1, 1, 1, 1, 'one.js');
        $c = $map->getFileContentsInlineMap(false);
        $this->assertEquals(array('one.js', 'two.js', 'three.js'), $sources);
    }
}
