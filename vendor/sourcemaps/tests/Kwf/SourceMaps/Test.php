<?php
class Kwf_SourceMaps_Test extends PHPUnit_Framework_TestCase
{
    public function testBase64Vlq()
    {
        for ($i = -255; $i < 256; $i++) {
            $v = Kwf_SourceMaps_Base64VLQ::encode($i);
            $result = Kwf_SourceMaps_Base64VLQ::decode($v);
            $this->assertEquals($result, $i);
            $this->assertEquals($v, "");
        }
    }

    public function testEmptyMap()
    {
        $map = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$emptyMap, '');
        $mappings = $map->getMappings();
        $this->assertEquals(count($mappings), 0);
    }

    public function testGetMappings()
    {
        $map = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $mappings = $map->getMappings();
        $this->assertEquals(count($mappings), 13);
        $this->assertEquals($mappings[0], array(
            'generatedLine' => 1,
            'generatedColumn' => 1,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 1,
            'originalColumn' => 1,
            'originalName' => null
        ));
        $this->assertEquals($mappings[1], array(
            'generatedLine' => 1,
            'generatedColumn' => 5,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 1,
            'originalColumn' => 5,
            'originalName' => null
        ));
        $this->assertEquals($mappings[2], array(
            'generatedLine' => 1,
            'generatedColumn' => 9,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 1,
            'originalColumn' => 11,
            'originalName' => null
        ));
        $this->assertEquals($mappings[3], array(
            'generatedLine' => 1,
            'generatedColumn' => 18,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 1,
            'originalColumn' => 21,
            'originalName' => 'bar'
        ));
        $this->assertEquals($mappings[4], array(
            'generatedLine' => 1,
            'generatedColumn' => 21,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 3,
            'originalName' => null
        ));
        $this->assertEquals($mappings[5], array(
            'generatedLine' => 1,
            'generatedColumn' => 28,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'originalName' => 'baz'
        ));
        $this->assertEquals($mappings[6], array(
            'generatedLine' => 1,
            'generatedColumn' => 32,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 14,
            'originalName' => 'bar'
        ));


        $this->assertEquals($mappings[7], array(
            'generatedLine' => 2,
            'generatedColumn' => 1,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 1,
            'originalName' => null
        ));
        $this->assertEquals($mappings[8], array(
            'generatedLine' => 2,
            'generatedColumn' => 5,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 5,
            'originalName' => null
        ));
        $this->assertEquals($mappings[9], array(
            'generatedLine' => 2,
            'generatedColumn' => 9,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 11,
            'originalName' => null
        ));
        $this->assertEquals($mappings[10], array(
            'generatedLine' => 2,
            'generatedColumn' => 18,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 21,
            'originalName' => 'n'
        ));
        $this->assertEquals($mappings[11], array(
            'generatedLine' => 2,
            'generatedColumn' => 21,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 2,
            'originalColumn' => 3,
            'originalName' => null
        ));
        $this->assertEquals($mappings[12], array(
            'generatedLine' => 2,
            'generatedColumn' => 28,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'originalName' => 'n'
        ));
    }
}
