<?php

namespace LimeSurvey\Helpers\Update;

class Update_142 extends DatabaseUpdateBase
{
    public function run()
    {
            upgradeQuestionAttributes142();
            $oDB->createCommand()->alterColumn('{{surveys}}', 'expires', "datetime");
            $oDB->createCommand()->alterColumn('{{surveys}}', 'startdate', "datetime");
            $oDB->createCommand()->update('{{question_attributes}}', array('value' => 0), "value='false'");
            $oDB->createCommand()->update('{{question_attributes}}', array('value' => 1), "value='true'");
    }
}