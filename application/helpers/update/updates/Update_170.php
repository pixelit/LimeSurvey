<?php

namespace LimeSurvey\Helpers\Update;

class Update_170 extends DatabaseUpdateBase
{
    public function run()
    {
            // renamed advanced attributes fields dropdown_dates_year_min/max
            $oDB->createCommand()->update(
                '{{question_attributes}}',
                array('attribute' => 'date_min'),
                "attribute='dropdown_dates_year_min'"
            );
            $oDB->createCommand()->update(
                '{{question_attributes}}',
                array('attribute' => 'date_max'),
                "attribute='dropdown_dates_year_max'"
            );
    }
}
