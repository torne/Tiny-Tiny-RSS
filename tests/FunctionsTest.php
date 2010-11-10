<?php
require_once dirname(__FILE__) . '/../functions.php';
/**
 * Unit tests for functions.php
 *
 * @author Christian Weiske <cweiske@php.net>
 */
class FunctionsTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test fix_url with feed:// urls
     */
    public function testFixUrlFeed()
    {
        $this->assertEquals('http://tt-rss.org/', fix_url('feed://tt-rss.org'));
        $this->assertEquals('http://tt-rss.org/', fix_url('feed://tt-rss.org/'));
    }

    /**
     * Test fix_url with non-http protocols
     */
    public function testFixUrlProtocols()
    {
        $this->assertEquals('https://tt-rss.org/', fix_url('https://tt-rss.org'));
        $this->assertEquals('ftp://tt-rss.org/', fix_url('ftp://tt-rss.org/'));
        $this->assertEquals(
            'reallylongprotocolisthat://tt-rss.org/', 
            fix_url('reallylongprotocolisthat://tt-rss.org')
        );
    }

    /**
     * Test fix_url with domain names only
     */
    public function testFixUrlDomainOnly()
    {
        $this->assertEquals('http://tt-rss.org/', fix_url('tt-rss.org'));
        $this->assertEquals('http://tt-rss.org/', fix_url('tt-rss.org/'));
        $this->assertEquals('http://tt-rss.org/', fix_url('http://tt-rss.org'));
        $this->assertEquals('http://tt-rss.org/', fix_url('http://tt-rss.org/'));
    }

    /**
     * Test fix_url with domain + paths
     */
    public function testFixUrlWithPaths()
    {
        $this->assertEquals('http://tt-rss.org/foo', fix_url('tt-rss.org/foo'));

        $this->assertEquals(
            'http://tt-rss.org/foo/bar/baz',
            fix_url('tt-rss.org/foo/bar/baz')
        );
        $this->assertEquals(
            'http://tt-rss.org/foo/bar/baz/',
            fix_url('tt-rss.org/foo/bar/baz/')
        );
    }
}

?>