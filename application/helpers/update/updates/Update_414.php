<?php

namespace LimeSurvey\Helpers\Update;

class Update_414 extends DatabaseUpdateBase
{
    public function run()
    {
            $oDB->createCommand()->addColumn('{{users}}', 'lastLogin', "datetime NULL");
    }
}
