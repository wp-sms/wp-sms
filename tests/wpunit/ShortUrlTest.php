<?php

class ShortUrlTest extends \Codeception\TestCase\WPTestCase
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function setUp(): void
    {
        // Before...
        parent::setUp();

        // Your set up methods here.
    }

    public function tearDown(): void
    {
        // Your tear down methods here.

        // Then...
        parent::tearDown();
    }

    public function testEmptyUrl()
    {
        $result = wp_sms_shorturl('');
        $this->assertEquals('', $result, 'Empty URL should return an empty string.');
    }

    public function testValidUrl()
    {
        $longUrl  = 'https://www.example.com/path/to/resource?query=string#fragment';
        $expected = 'https://www.example.com/path/to/resource?query=string#fragment';
        $result   = wp_sms_shorturl($longUrl);

        $this->assertEquals($expected, $result, 'The URL should be properly encoded and reconstructed.');
    }

    public function testInvalidUrl()
    {
        $longUrl = 'invalid-url';
        $result  = wp_sms_shorturl($longUrl);

        $this->assertEquals('invalid-url', $result, 'Invalid URL should return the original input.');
    }

    public function testUrlMissingComponents()
    {
        $longUrl  = 'http://example.com';
        $expected = 'http://example.com';
        $result   = wp_sms_shorturl($longUrl);

        $this->assertEquals($expected, $result, 'URL missing components should return the original input.');
    }

    public function testUrlWithSpace()
    {
        $longUrl  = 'https://www.example.com/path/to resource?query=string&another=parameter';
        $expected = 'https://www.example.com/path/to%20resource?query=string&another=parameter';
        $result   = wp_sms_shorturl($longUrl);

        $this->assertEquals($expected, $result, 'URL with spaces should be properly encoded.');
    }

    public function testUrlWithSpecialCharacters()
    {
        $longUrl  = 'https://www.example.com/path/to/resource?query=string&another=parameter with space';
        $expected = 'https://www.example.com/path/to/resource?query=string&another=parameter%20with%20space';
        $result   = wp_sms_shorturl($longUrl);

        $this->assertEquals($expected, $result, 'URL with special characters should be properly encoded.');
    }

    public function testUrlWithPort()
    {
        $longUrl  = 'https://www.example.com:8080/path?query=string';
        $expected = 'https://www.example.com:8080/path?query=string';
        $result   = wp_sms_shorturl($longUrl);

        $this->assertEquals($expected, $result, 'URL with port should be properly encoded and reconstructed.');
    }

    public function testUrlWithFragment()
    {
        $longUrl  = 'https://www.example.com/path?query=string#section';
        $expected = 'https://www.example.com/path?query=string#section';
        $result   = wp_sms_shorturl($longUrl);

        $this->assertEquals($expected, $result, 'URL with fragment should be properly encoded and reconstructed.');
    }

    public function testUrlWithUtf8Characters()
    {
        $longUrl  = 'https://www.example.com/شعرای-پارسی/سعدی?موضوع=شعر#بخش';
        $expected = 'https://www.example.com/%D8%B4%D8%B9%D8%B1%D8%A7%DB%8C-%D9%BE%D8%A7%D8%B1%D8%B3%DB%8C/%D8%B3%D8%B9%D8%AF%DB%8C?%D9%85%D9%88%D8%B6%D9%88%D8%B9=%D8%B4%D8%B9%D8%B1#بخش';
        $result   = wp_sms_shorturl($longUrl);

        $this->assertEquals($expected, $result, 'URL with UTF-8 Persian characters should be properly encoded.');
    }
}
