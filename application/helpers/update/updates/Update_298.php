<?php

namespace LimeSurvey\Helpers\Update;

class Update_298 extends DatabaseUpdateBase
{
    public function run()
    {
            upgradeTemplateTables298($oDB);
    }
}