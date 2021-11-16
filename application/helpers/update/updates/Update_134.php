<?php

namespace LimeSurvey\Helpers\Update;

class Update_134 extends DatabaseUpdateBase
{
    public function run()
    {
            // Add new tokens setting
            addColumn('{{surveys}}', 'usetokens', "string(1) NOT NULL default 'N'");
            addColumn('{{surveys}}', 'attributedescriptions', 'text');
            dropColumn('{{surveys}}', 'attribute1');
            dropColumn('{{surveys}}', 'attribute2');
            upgradeTokenTables134();
    }
}
