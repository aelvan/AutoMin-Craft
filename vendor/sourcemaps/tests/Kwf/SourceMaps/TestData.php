<?php
/*
 * Test Contents based on https://github.com/mozilla/source-map test/source-map/util.js
 * Copyright 2011 Mozilla Foundation and contributors
 * Licensed under the New BSD license. See LICENSE or:
 * http://opensource.org/licenses/BSD-3-Clause
*/
class Kwf_SourceMaps_TestData
{

    // This is a test mapping which maps functions from two different files
    // (one.js and two.js) to a minified generated source.
    //
    // Here is one.js:
    //
    //   ONE.foo = function (bar) {
    //     return baz(bar);
    //   };
    //
    // Here is two.js:
    //
    //   TWO.inc = function (n) {
    //     return n + 1;
    //   };
    //
    // And here is the generated code (min.js):
    //
    //   ONE.foo=function(a){return baz(a);};
    //   TWO.inc=function(a){return a+1;};
    public static $testGeneratedCode = " ONE.foo=function(a){return baz(a);};\n TWO.inc=function(a){return a+1;};";
    public static $testMap = '{
        "version": 3,
        "file": "min.js",
        "names": ["bar", "baz", "n"],
        "sources": ["one.js", "two.js"],
        "sourceRoot": "/the/root",
        "mappings": "CAAC,IAAI,IAAM,SAAUA,GAClB,OAAOC,IAAID;CCDb,IAAI,IAAM,SAAUE,GAClB,OAAOA"
    }';

    public static $testSmallGeneratedCode1 = " ONE1.foo;";
    public static $testSmallMap1 = '{
        "version": 3,
        "file": "min1.js",
        "names": [],
        "sources": ["one1.js"],
        "sourceRoot": "/the/root",
        "mappings": "CAAC,IAAI"
    }';
    public static $testSmallGeneratedCode2 = " ONE2.foo;";
    public static $testSmallMap2 = '{
        "version": 3,
        "file": "min2.js",
        "names": [],
        "sources": ["one2.js"],
        "sourceRoot": "/the/root",
        "mappings": "CAAC,IAAI"
    }';

    public static $testMapWithSourcesContent = '{
        "version": 3,
        "file": "min.js",
        "names": ["bar", "baz", "n"],
        "sources": ["one.js", "two.js"],
        "sourcesContent": [
        " ONE.foo = function (bar) {
   return baz(bar);
 };",
        " TWO.inc = function (n) {
   return n + 1;
 };"
        ],
        "sourceRoot": "/the/root",
        "mappings": "CAAC,IAAI,IAAM,SAAUA,GAClB,OAAOC,IAAID;CCDb,IAAI,IAAM,SAAUE,GAClB,OAAOA"
    }';
    public static $emptyMap = '{
        "version": 3,
        "file": "min.js",
        "names": [],
        "sources": [],
        "mappings": ""
    }';
}
