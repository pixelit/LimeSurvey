<?php

namespace LimeSurvey\Helpers\Update;

class Update_409 extends DatabaseUpdateBase
{
    public function run()
    {

            $sEncrypted = 'N';
            $oDB->createCommand()->update(
                '{{participant_attribute_names}}',
                array('encrypted' => $sEncrypted),
                "core_attribute='Y'"
            );
    }
}
