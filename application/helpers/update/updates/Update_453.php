<?php

namespace LimeSurvey\Helpers\Update;

class Update_453 extends DatabaseUpdateBase
{
    public function run()
    {

            $columnSchema = $oDB->getSchema()->getTable('{{archived_table_settings}}')->getColumn('attributes');
            if ($columnSchema === null) {
                $oDB->createCommand()->addColumn('{{archived_table_settings}}', 'attributes', 'text NULL');
            }
            $archivedTableSettings = Yii::app()->db->createCommand("SELECT * FROM {{archived_table_settings}}")->queryAll();
            foreach ($archivedTableSettings as $archivedTableSetting) {
                if ($archivedTableSetting['tbl_type'] === 'token') {
                    $oDB->createCommand()->update('{{archived_table_settings}}', ['attributes' => json_encode(['unknown'])], 'id = :id', ['id' => $archivedTableSetting['id']]);
                }
            }

    }
}