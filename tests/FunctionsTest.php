<?php
require_once dirname(__FILE__) . '/../functions.php';
/**
 * Unit tests for functions.php
 *
 * @author Christian Weiske <cweiske@php.net>
 */
class FunctionsTest extends PHPUnit_Framework_TestCase
{
    protected $tmpFile = null;
    public function __construct()
    {
        $this->tmpFile = sys_get_temp_dir() . '/tt-rss-unittest.dat';
    }

    public function tearDown()
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

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


    /**
     * Test url_is_html() on html with a doctype
     */
    public function testUrlIsHtmlNormalHtmlWithDoctype()
    {
        file_put_contents(
            $this->tmpFile, <<<HTM
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
HTM
        );
        $this->assertTrue(url_is_html($this->tmpFile));

        file_put_contents(
            $this->tmpFile, <<<HTM
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
HTM
        );
        $this->assertTrue(url_is_html($this->tmpFile));
    }

    /**
     * Test url_is_html() on html with a doctype and xml header
     */
    public function testUrlIsHtmlNormalHtmlWithDoctypeAndXml()
    {
        file_put_contents(
            $this->tmpFile, <<<HTM
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
HTM
        );
        $this->assertTrue(url_is_html($this->tmpFile));
    }

    /**
     * Test url_is_html() on html without a doctype
     */
    public function testUrlIsHtmlNormalHtmlWithoutDoctype()
    {
        file_put_contents(
            $this->tmpFile, <<<HTM
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
HTM
        );
        $this->assertTrue(url_is_html($this->tmpFile));
    }

    /**
     * Test url_is_html() on UPPERCASE HTML
     */
    public function testUrlIsHtmlNormalHtmlUppercase()
    {
        file_put_contents(
            $this->tmpFile, <<<HTM
<HTML XMLNS="http://www.w3.org/1999/xhtml" XML:LANG="en">
<HEAD>
HTM
        );
        $this->assertTrue(url_is_html($this->tmpFile));

        file_put_contents(
            $this->tmpFile, <<<HTM
<HTML>
<HEAD>
HTM
        );
        $this->assertTrue(url_is_html($this->tmpFile));
    }

    /**
     * Test url_is_html() on atom
     */
    public function testUrlIsHtmlAtom()
    {
        file_put_contents(
            $this->tmpFile, <<<HTM
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
 <title>Christians Tagebuch</title>
HTM
        );
        $this->assertFalse(url_is_html($this->tmpFile));
    }

    /**
     * Test url_is_html() on RSS
     */
    public function testUrlIsHtmlRss()
    {
        file_put_contents(
            $this->tmpFile, <<<HTM
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" media="screen" href="/~d/styles/rss2full.xsl"?><?xml-stylesheet type="text/css" media="screen" href="http://feeds.feedburner.com/~d/styles/itemcontent.css"?><rss xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:feedburner="http://rssnamespace.org/feedburner/ext/1.0" version="2.0">
  <channel>
    <title><![CDATA[Planet-PEAR]]></title>
HTM
        );
        $this->assertFalse(url_is_html($this->tmpFile));
    }
}

?>