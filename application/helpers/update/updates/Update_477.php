<?php

namespace LimeSurvey\Helpers\Update;

class Update_477 extends DatabaseUpdateBase
{
    public function run()
    {

            // refactored controller ResponsesController (surveymenu_entry link changes to new controller rout)
            $oDB->createCommand()->update(
                '{{surveymenu_entries}}',
                [
                    'menu_link' => 'responses/browse',
                    'data'      => '{"render": {"isActive": true, "link": {"data": {"surveyId": ["survey", "sid"]}}}}'
                ],
                "name='responses'"
            );
    }
}
