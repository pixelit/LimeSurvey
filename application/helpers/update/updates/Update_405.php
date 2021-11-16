<?php

namespace LimeSurvey\Helpers\Update;

class Update_405 extends DatabaseUpdateBase
{
    public function run()
    {
            $oDB->createCommand(
                "
                UPDATE
                    {{boxes}}
                SET ico = CASE
                    WHEN ico IN ('add', 'list', 'settings', 'shield', 'templates', 'label') THEN CONCAT('icon-', ico)
                    ELSE ico
                END
                "
            )->execute();
    }
}