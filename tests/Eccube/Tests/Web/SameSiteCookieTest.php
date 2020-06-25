<?php

namespace Eccube\Tests\Web;

class SameSiteCookieTest extends AbstractWebTestCase
{
    public function setUp()
    {
        // parent::setUp() は, 各テストメソッドで行う
    }

    public function provideSession()
     {
         return array(
             array('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130', true),
             array('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1.1 Safari/605.1.15', false),
             array('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1 Safari/605.1.15', true),
             array('Mozilla/5.0 (iPhone; CPU iPhone OS 12_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0 EdgiOS/44.8.0 Mobile/15E148 Safari/605.1.15', false),
             array('Mozilla/5.0 (iPhone; CPU iPhone OS 13_1_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.1 Mobile/15E148 Safari/604.1', true)
         );
     }

    /**
     * @dataProvider provideSession
     */
    public function testSessionParams($userAgent, $shouldSendSameSiteNone)
    {
        $_SERVER['HTTP_USER_AGENT'] = $userAgent;
        parent::setUp();
        if (!$this->app['config']['force_ssl']) {
            $this->markTestSkipped('force_ssl required');
        }
        $this->client->request('GET', $this->app['url_generator']->generate('homepage'));
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $cookieParams = session_get_cookie_params();
        if ($shouldSendSameSiteNone) {
            if (PHP_VERSION_ID >= 70300) {
                $this->assertEquals('/', $cookieParams['path']);
                $this->assertEquals('none', $cookieParams['samesite']);
            } else {
                $this->assertEquals('/; SameSite=none', $cookieParams['path']);
            }
        } else {
            $this->assertEquals('/', $cookieParams['path']);
        }
    }
}
