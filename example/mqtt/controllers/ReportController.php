<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/8
 * Time: 3:44 PM
 */

namespace mqtt\controllers;

use immusen\mqtt\src\Controller;

class ReportController extends Controller
{
    /**
     * Verb: publish
     * Sensor report data to server...
     * @param $sn, Sensor SN
     * @param $payload ,Sensor data: e.g. 110.12345678,30.12345678,0,85
     * @return mixed
     */
    public function actionCoord($sn, $payload)
    {
        $record = time() . ',' . $payload . PHP_EOL;
        file_put_contents('/tmp/report_' . $sn, $record, FILE_APPEND);
    }

}