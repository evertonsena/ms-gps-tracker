<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;

use App\Models\DataTk103b;
use App\Models\Gps;

class DataTk103bTest extends TestCase
{
    use DatabaseMigrations;

    function __construct()
    {
        parent::setUp();
    }

    public function testSetDataExpectedReturnClassInstance()
    {
        $modelTest = new DataTk103b();
        $return = $modelTest->setData($this->getDataFromGps());

        $this->assertInstanceOf(DataTk103b::class, $return,
            'Esperasse que retorne instancia da classe DataTk103b');
    }

    public function testSaveNotExistDataExpectedReturnFalse()
    {
        $modelTest = new DataTk103b();

        Log::shouldReceive('error')
            ->once()
            ->andReturn(true);

        $return = $modelTest->save();

        $this->assertFalse($return, 'Esperasse que retorne false apos ' .
            'salvar sem dados');
    }

    public function testSaveExistDataFailedSavedDataGpsExpectedReturnFalse()
    {
        $modelTest = (new DataTk103b())->setData($this->getDataFromGps());

        Log::shouldReceive('error')
            ->once()
            ->andReturn(true);

        $return = $modelTest->save();

        $this->markTestIncomplete(
            'Marcado como incompleto pois não consigo provocar um erro ao salvar no modelo. ' .
            'Mesmo usando mock não consegui forçar um return false no metodo saveDataGps()'
        );

        $this->assertFalse($return, 'Esperasse que retorne false apos ' .
            'falha ao salvar dados');
    }

    public function testSaveExistDataSuccesSavedDataGpsExpectedReturnTrue()
    {
        $modelTest = (new DataTk103b())->setData($this->getDataFromGps());

        $return = $modelTest->save();

        $this->assertTrue($return, 'Esperasse que retorne true apos ' .
            'salvar os dados');

        $this->seeInDatabase('gps', ['imei' => '359710049095095']);
    }

    public function testGetAlarmExpectedReturnAlarm()
    {
        $modelTest = (new DataTk103b())->setData($this->getDataFromGps());

        $this->assertEquals('Alarm', $modelTest->getAlarm());
    }

    public function testGetImeiExpectedReturnImei()
    {
        $modelTest = (new DataTk103b())->setData($this->getDataFromGps());

        $this->assertEquals('359710049095095', $modelTest->getImei());
    }

    private function getDataFromGps()
    {
        return [
            'imei:359710049095095',
            'Alarm',
            '151006012336',
            '',
            'F',
            '172337.000',
            'A',
            '5105.9792',
            'N',
            '11404.9599',
            'W',
            '0.01',
            '322.56',
            '',
            '0',
            '0',
            '',
            ''
        ];
    }
}