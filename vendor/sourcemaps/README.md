
## Source Maps Utilities Php Library[![Build Status](https://travis-ci.org/koala-framework/sourcemaps.svg?branch=master)](https://travis-ci.org/koala-framework/sourcemaps)

### Features

* creating mappings
* reading mappings
* source map aware string replace for existing mapping
* concat maps (optimized for best performance)

### Requirements

* Php 5.2+

### Installation
Install using composer:

    composer require koala-framework/sourcemaps

### Example Usage

    //read
    $map = new Kwf_SourceMaps_SourceMap($mapContents, $minimiedFileContents);
    $map->getMappings()

    //create new map
    $map = Kwf_SourceMaps_SourceMap::createEmptyMap($minimiedFileContents);
    $map->addMapping(2, 3, 10, 12, 'foo.js'); //$generatedLine, $generatedColumn, $originalLine, $originalColumn, $originalSource, $originalName = null)
    $map->getMapContents();

    //merge two maps
    $map1->concat($map2);

    //perform string replacement
    $map->stringReplace('foo', 'bar');
