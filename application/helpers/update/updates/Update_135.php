<?php

namespace LimeSurvey\Helpers\Update;

class Update_135 extends DatabaseUpdateBase
{
    public function run()
    {
            alterColumn('{{question_attributes}}', 'value', 'text');
    }
}
