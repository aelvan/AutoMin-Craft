<?php
class Kwf_SourceMaps_StringReplaceTest extends PHPUnit_Framework_TestCase
{
    public function testStringReplace()
    {
        $map = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map->stringReplace('baz', 'asdfasdf');

             //0        1         2         3         4
             //1234567890123456789012345678901234567890123
        $s = " ONE.foo=function(a){return asdfasdf(a);};\n".
             " TWO.inc=function(a){return a+1;};";
        $this->assertEquals($map->getFileContents(), $s);

        $mappings = $map->getMappings();
        $this->assertEquals($mappings[5], array(
            'generatedLine' => 1,
            'generatedColumn' => 28, //must not change
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'originalName' => 'baz'
        ));
        $this->assertEquals($mappings[6], array(
            'generatedLine' => 1,
            'generatedColumn' => 32+5,  //this needs to be shifted
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 14,
            'originalName' => 'bar'
        ));

        //first of line 2
        $this->assertEquals($mappings[7], array(
            'generatedLine' => 2,
            'generatedColumn' => 1, //must not change
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 1,
            'originalName' => null
        ));
    }

    public function testStringReplaceSecondLine()
    {
        $map = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map->stringReplace('inc', 'increment');

             //0        1         2         3         4
             //1234567890123456789012345678901234567890123
            // TWO.inc=function(a){return a+1;};
        $s = " ONE.foo=function(a){return baz(a);};\n".
             " TWO.increment=function(a){return a+1;};";
        $this->assertEquals($map->getFileContents(), $s);

        $mappings = $map->getMappings();
        //last of line 1
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
            'generatedColumn' => 1, //don't change
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 1,
            'originalName' => null
        ));
        $this->assertEquals($mappings[8], array(
            'generatedLine' => 2,
            'generatedColumn' => 5, //don't change
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 5,
            'originalName' => null
        ));
        $this->assertEquals($mappings[9], array(
            'generatedLine' => 2,
            'generatedColumn' => 9+6,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 11,
            'originalName' => null
        ));
        $this->assertEquals($mappings[10], array(
            'generatedLine' => 2,
            'generatedColumn' => 18+6,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 21,
            'originalName' => 'n'
        ));
        $this->assertEquals($mappings[12], array(
            'generatedLine' => 2,
            'generatedColumn' => 28+6,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'originalName' => 'n'
        ));
    }

    public function testStringReplaceMultipleInOneLine()
    {
        $map = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map->stringReplace('a', 'xbbbbxxxxxxxx');

             //0        1         2         3         4
             //1234567890123456789012345678901234567890123
          //   ONE.foo=function(a){return baz(a);};
        $s = " ONE.foo=function(xbbbbxxxxxxxx){return bxbbbbxxxxxxxxz(xbbbbxxxxxxxx);};\n".
          //   TWO.inc=function(a){return a+1;};
             " TWO.inc=function(xbbbbxxxxxxxx){return xbbbbxxxxxxxx+1;};";
        $this->assertEquals($map->getFileContents(), $s);

        $mappings = $map->getMappings();
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
            'generatedColumn' => 21+12,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 3,
            'originalName' => null
        ));
        $this->assertEquals($mappings[5], array(
            'generatedLine' => 1,
            'generatedColumn' => 28+12,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'originalName' => 'baz'
        ));
        $this->assertEquals($mappings[6], array(
            'generatedLine' => 1,
            'generatedColumn' => 32+12+12,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 14,
            'originalName' => 'bar'
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
            'generatedColumn' => 21+12,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 2,
            'originalColumn' => 3,
            'originalName' => null
        ));
        $this->assertEquals($mappings[12], array(
            'generatedLine' => 2,
            'generatedColumn' => 28+12,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'originalName' => 'n'
        ));
    }

    public function testLeaveFirstMappingUntouched()
    {
        $map = Kwf_SourceMaps_SourceMap::createEmptyMap('(function($,window,document,undefined){});');
        $map->addMapping(1, 1, 1, 1, 'foo.js');
        $map->addMapping(1, 10, 1, 11, 'foo.js', '$');
        $map->addMapping(1, 12, 1, 14, 'foo.js', 'window');
        $map->addMapping(1, 19, 1, 22, 'foo.js', 'document');
        $map->addMapping(1, 28, 1, 32, 'foo.js', 'undefined');

        $map->stringReplace('(function($,window,document,undefined){', "var $=jQuery=require('jQuery');");
        $mappings = $map->getMappings();
        $this->assertEquals($mappings[0], array(
            'generatedLine' => 1,
            'generatedColumn' => 1,
            'originalSource' => 'foo.js',
            'originalLine' => 1,
            'originalColumn' => 1,
            'originalName' => null
        ));
    }

    public function testRemoveReplacedMapping()
    {
                                                       //0         1         2         3         4
                                                       //01234567890123456789012345678901234567890123456789
        $map = Kwf_SourceMaps_SourceMap::createEmptyMap('(function($,window,document,undefined){var x=1;});');
        $map->addMapping(1, 1, 1, 1, 'foo.js');
        $map->addMapping(1, 10, 1, 11, 'foo.js', '$');
        $map->addMapping(1, 12, 1, 14, 'foo.js', 'window');
        $map->addMapping(1, 19, 1, 22, 'foo.js', 'document');
        $map->addMapping(1, 28, 1, 32, 'foo.js', 'undefined');
        $map->addMapping(1, 43, 2, 5, 'foo.js', 'x');

        $map->stringReplace('(function($,window,document,undefined){', "var $=jQuery=require('jQuery');");
        $mappings = $map->getMappings();
        $this->assertEquals(2, count($mappings));
        $this->assertEquals($mappings[0], array(
            'generatedLine' => 1,
            'generatedColumn' => 1,
            'originalSource' => 'foo.js',
            'originalLine' => 1,
            'originalColumn' => 1,
            'originalName' => null
        ));

        $this->assertEquals($mappings[1], array(
            'generatedLine' => 1,
            'generatedColumn' => 43+31-39,
            'originalSource' => 'foo.js',
            'originalLine' => 2,
            'originalColumn' => 5,
            'originalName' => 'x'
        ));
    }
}
