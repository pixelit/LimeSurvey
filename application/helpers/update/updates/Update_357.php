<?php

namespace LimeSurvey\Helpers\Update;

class Update_357 extends DatabaseUpdateBase
{
    public function run()
    {
            //// IKI
            $oDB->createCommand()->renameColumn('{{surveys_groups}}', 'owner_uid', 'owner_id');
    }
}