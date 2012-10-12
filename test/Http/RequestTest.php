<?php
namespace Test\Net\Http;

use Net\Http\Request;

class RequestTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp() {}

    /**
     * @dataProvider dataProviderForGetRequestString
     */
    public function testGetRequestString($test, $data)
    {
        $expected = $data['expected'];
        $actual = Request::factory('www.test.private')
                  ->setParams($data['params'])
                  ->getRequestString($data['path']);
        $this->assertEquals($expected, $actual);
    }

    public function dataProviderForGetRequestString()
    {
        $header = array();
        $header[] =  "GET / HTTP/1.0\r\n"
                    ."Host: www.test.private\r\n"
                    ."\r\n";
        $header[] =  "GET / HTTP/1.0\r\n"
                    ."Host: www.test.private\r\n"
                    ."\r\n"
                    ."param1=hoge";
        $i = 0;
        $dataset = array(
            array('Request / HTTP/1.0', array(
                'path'     => '/',
                'params'   => array(),
                'expected' => $header[$i++],
            )),
            array('Request / HTTP/1.0', array(
                'path'     => '/',
                'params'   => array('param1' => 'hoge'),
                'expected' => $header[$i++],
            ))
        );

        return $dataset;
    }
}

