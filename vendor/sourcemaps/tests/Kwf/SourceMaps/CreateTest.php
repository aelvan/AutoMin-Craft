<?php
class Kwf_SourceMaps_CreateTest extends PHPUnit_Framework_TestCase
{
    public function testCreateEmpty()
    {
        $map = Kwf_SourceMaps_SourceMap::createEmptyMap('');
        $this->assertTrue(!!$map->getMapContents());
        $data = $map->getMapContentsData();
        $this->assertTrue(!!$data);
    }

    public function testCreateSimple()
    {
        $map = Kwf_SourceMaps_SourceMap::createEmptyMap(Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map->addMapping(1, 1, 1, 1, 'one.js');
        $map->addMapping(1, 5, 1, 5, 'one.js');
        $map->addMapping(1, 9, 1, 11, 'one.js');
        $map->addMapping(1, 18, 1, 21, 'one.js', 'bar');
        $map->addMapping(1, 21, 2, 3, 'one.js');
        $map->addMapping(1, 28, 2, 10, 'one.js', 'baz');
        $map->addMapping(1, 32, 2, 14, 'one.js', 'bar');

        $map->addMapping(2, 1, 1, 1, 'two.js');
        $map->addMapping(2, 5, 1, 5, 'two.js');
        $map->addMapping(2, 9, 1, 11, 'two.js');
        $map->addMapping(2, 18, 1, 21, 'two.js', 'n');
        $map->addMapping(2, 21, 2, 3, 'two.js');
        $map->addMapping(2, 28, 2, 10, 'two.js', 'n');
        $this->assertEquals(13, count($map->getMappings()));
        $data = $map->getMapContentsData();

        $testData = json_decode(Kwf_SourceMaps_TestData::$testMap);
        $this->assertEquals($data->mappings, $testData->mappings);
        $this->assertEquals($data->names, $testData->names);
        $this->assertEquals($data->sources, $testData->sources);
    }
}
