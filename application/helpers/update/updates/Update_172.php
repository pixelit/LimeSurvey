<?php

namespace LimeSurvey\Helpers\Update;

class Update_172 extends DatabaseUpdateBase
{
    public function run()
    {
            switch (Yii::app()->db->driverName) {
                case 'pgsql':
                    // Special treatment for Postgres as it is too dumb to convert a string to a number without explicit being told to do so ... seriously?
                    alterColumn('{{permissions}}', 'entity_id', "INTEGER USING (entity_id::integer)", false);
                    break;
                case 'sqlsrv':
                case 'dblib':
                case 'mssql':
                    try {
                        setTransactionBookmark();
                        $oDB->createCommand()->dropIndex('permissions_idx2', '{{permissions}}');
                    } catch (Exception $e) {
                        rollBackToTransactionBookmark();
                    };
                    try {
                        setTransactionBookmark();
                        $oDB->createCommand()->dropIndex('idxPermissions', '{{permissions}}');
                    } catch (Exception $e) {
                        rollBackToTransactionBookmark();
                    };
                    alterColumn('{{permissions}}', 'entity_id', "INTEGER", false);
                    $oDB->createCommand()->createIndex(
                        'permissions_idx2',
                        '{{permissions}}',
                        'entity_id,entity,permission,uid',
                        true
                    );
                    break;
                default:
                    alterColumn('{{permissions}}', 'entity_id', "INTEGER", false);
            }
    }
}