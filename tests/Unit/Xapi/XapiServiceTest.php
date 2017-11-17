<?php

namespace Tests\Unit\Xapi;

use Biz\BaseTestCase;
use Biz\Xapi\Service\XapiService;

class XapiServiceTest extends BaseTestCase
{
    public function testCreateStatement()
    {
        $statement = $this->mockStatement();

        $createdStatement = $this->getXapiService()->createStatement($statement); 



    }

    private function mockStatement()
    {
        $statement = array(
            'user_id' => 2,
            'verb' => 'watch',
            'target_id' => 1,
            'target_type' => 'video',
            'occur_time' => time(),
        );

        return $statement;
    }

    /**
     * @return XapiService
     */
    protected function getXapiService()
    {
        return $this->createService('Xapi:XapiService');
    }
}