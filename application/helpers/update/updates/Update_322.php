<?php

namespace LimeSurvey\Helpers\Update;

class Update_322 extends DatabaseUpdateBase
{
    public function run()
    {
            $oDB->createCommand()->createTable(
                '{{tutorials}}',
                [
                    'tid' => 'pk',
                    'name' => 'string(128)',
                    'description' => 'text',
                    'active' => 'integer DEFAULT 0',
                    'settings' => 'text',
                    'permission' => 'string(128) NOT NULL',
                    'permission_grade' => 'string(128) NOT NULL'
                ]
            );
            $oDB->createCommand()->createTable(
                '{{tutorial_entries}}',
                [
                    'teid' => 'pk',
                    'tid' => 'integer NOT NULL',
                    'title' => 'text',
                    'content' => 'text',
                    'settings' => 'text'
                ]
            );
    }
}